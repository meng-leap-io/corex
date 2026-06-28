(function () {
    'use strict';

    const SupabaseRealtime = {
        instance: null,
        channels: {},
        subscriptions: {},
        offlineQueue: [],
        connected: false,
        reconnecting: false,
        reconnectAttempts: 0,
        maxReconnectAttempts: 10,
        reconnectDelay: 1000,
        heartbeatInterval: null,
        offlineQueueKey: null,

        config: {
            url: window.SUPABASE_URL || '',
            key: window.SUPABASE_ANON_KEY || '',
            projectRef: '',
            enabled: true,
        },

        init(config) {
            if (!config || !config.key) {
                console.debug('[SupabaseRealtime] disabled: missing config');
                return;
            }

            Object.assign(this.config, config);
            this.projectRef = config.projectRef || this.extractProjectRef(config.url);
            this.offlineQueueKey = 'supabase:realtime:queue:' + (window.USER_ID || 'anonymous');

            this.loadOfflineQueue();
            this.connect();
            this.setupBeforeUnload();
            this.setupOnlineListener();

            console.debug('[SupabaseRealtime] initialized', {
                project: this.projectRef,
                enabled: this.config.enabled,
            });
        },

        extractProjectRef(url) {
            try {
                const hostname = new URL(url).hostname;
                return hostname.split('.')[0];
            } catch (e) {
                return '';
            }
        },

        connect() {
            if (!this.config.enabled) return;
            if (this.instance) return;

            try {
                const wsUrl = `wss://${this.projectRef}.supabase.co/realtime/v1/websocket?apikey=${this.config.key}&vsn=1.0.0`;

                this.instance = new WebSocket(wsUrl);

                this.instance.onopen = () => {
                    console.debug('[SupabaseRealtime] connected');
                    this.connected = true;
                    this.reconnectAttempts = 0;
                    this.reconnectDelay = 1000;

                    this.startHeartbeat();
                    this.resubscribeAll();
                    this.flushOfflineQueue();
                    this.dispatchEvent('supabase:connected');
                };

                this.instance.onclose = (event) => {
                    console.debug('[SupabaseRealtime] disconnected', event.code, event.reason);
                    this.connected = false;
                    this.stopHeartbeat();
                    this.dispatchEvent('supabase:disconnected');
                    this.scheduleReconnect();
                };

                this.instance.onerror = (error) => {
                    console.warn('[SupabaseRealtime] error', error);
                    this.dispatchEvent('supabase:error', { error });
                };

                this.instance.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        this.handleMessage(data);
                    } catch (e) {
                        console.warn('[SupabaseRealtime] parse error', e);
                    }
                };
            } catch (e) {
                console.error('[SupabaseRealtime] connection error', e);
                this.scheduleReconnect();
            }
        },

        disconnect() {
            if (!this.instance) return;

            this.stopHeartbeat();
            this.instance.close(1000, 'client disconnect');
            this.instance = null;
            this.connected = false;
        },

        scheduleReconnect() {
            if (this.reconnecting) return;
            if (this.reconnectAttempts >= this.maxReconnectAttempts) {
                console.warn('[SupabaseRealtime] max reconnection attempts reached');
                return;
            }

            this.reconnecting = true;
            this.reconnectAttempts++;

            const delay = Math.min(
                this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1),
                30000,
            );

            console.debug(`[SupabaseRealtime] reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);

            setTimeout(() => {
                this.reconnecting = false;
                this.connect();
            }, delay);
        },

        subscribe(channel, event, callback) {
            if (!this.channels[channel]) {
                this.channels[channel] = { callbacks: {} };
            }

            if (!this.channels[channel].callbacks[event]) {
                this.channels[channel].callbacks[event] = [];
            }

            this.channels[channel].callbacks[event].push(callback);

            if (this.connected) {
                this.sendSubscribeMessage(channel, event);
            }

            const unsubscribe = () => {
                const events = this.channels[channel]?.callbacks[event];
                if (events) {
                    const idx = events.indexOf(callback);
                    if (idx > -1) events.splice(idx, 1);
                }
            };

            return unsubscribe;
        },

        unsubscribe(channel, event) {
            if (this.channels[channel]) {
                if (event) {
                    delete this.channels[channel].callbacks[event];
                } else {
                    delete this.channels[channel];
                }
            }

            if (this.connected) {
                this.sendUnsubscribeMessage(channel, event);
            }
        },

        broadcast(channel, event, payload) {
            const message = {
                type: 'broadcast',
                channel: channel,
                event: event,
                payload: payload,
            };

            if (this.connected) {
                this.sendRaw(message);
            } else {
                this.addToOfflineQueue(message);
            }
        },

        trackPresence(channel, user, metadata) {
            const message = {
                type: 'presence',
                channel: channel,
                user: user,
                metadata: metadata || {},
            };

            if (this.connected) {
                this.sendRaw(message);
            }
        },

        untrackPresence(channel, user) {
            if (this.connected) {
                this.sendRaw({
                    type: 'presence_leave',
                    channel: channel,
                    user: user,
                });
            }
        },

        handleMessage(data) {
            if (data.type === 'broadcast' && data.channel && data.event) {
                this.dispatchToCallbacks(data.channel, data.event, data.payload);
            }

            if (data.type === 'presence' && data.channel) {
                this.dispatchToCallbacks(data.channel, 'presence', data);
            }

            if (data.type === 'system') {
                console.debug('[SupabaseRealtime] system message', data);
            }
        },

        dispatchToCallbacks(channel, event, payload) {
            const channelSubs = this.channels[channel];
            if (!channelSubs) return;

            const eventCallbacks = channelSubs.callbacks[event];
            if (eventCallbacks) {
                eventCallbacks.forEach((cb) => {
                    try {
                        cb(payload);
                    } catch (e) {
                        console.warn('[SupabaseRealtime] callback error', e);
                    }
                });
            }

            const allCallbacks = channelSubs.callbacks['*'];
            if (allCallbacks) {
                allCallbacks.forEach((cb) => {
                    try {
                        cb({ channel, event, payload });
                    } catch (e) {
                        console.warn('[SupabaseRealtime] wildcard callback error', e);
                    }
                });
            }
        },

        sendSubscribeMessage(channel, event) {
            this.sendRaw({
                type: 'subscribe',
                channel: channel,
                event: event,
            });
        },

        sendUnsubscribeMessage(channel, event) {
            this.sendRaw({
                type: 'unsubscribe',
                channel: channel,
                event: event,
            });
        },

        sendRaw(message) {
            if (!this.connected || !this.instance) {
                this.addToOfflineQueue(message);
                return;
            }

            try {
                this.instance.send(JSON.stringify(message));
            } catch (e) {
                console.warn('[SupabaseRealtime] send error', e);
                this.addToOfflineQueue(message);
            }
        },

        resubscribeAll() {
            Object.keys(this.channels).forEach((channel) => {
                const channelSubs = this.channels[channel];
                Object.keys(channelSubs.callbacks).forEach((event) => {
                    this.sendSubscribeMessage(channel, event);
                });
            });
        },

        startHeartbeat() {
            this.stopHeartbeat();
            this.heartbeatInterval = setInterval(() => {
                if (this.connected) {
                    this.sendRaw({ type: 'heartbeat' });
                }
            }, 30000);
        },

        stopHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
                this.heartbeatInterval = null;
            }
        },

        addToOfflineQueue(message) {
            this.offlineQueue.push({
                ...message,
                _queuedAt: Date.now(),
                _retries: 0,
            });

            if (this.offlineQueue.length > 1000) {
                this.offlineQueue.shift();
            }

            this.saveOfflineQueue();
        },

        flushOfflineQueue() {
            if (this.offlineQueue.length === 0) return;

            const queue = [...this.offlineQueue];
            this.offlineQueue = [];
            this.saveOfflineQueue();

            queue.forEach((message) => {
                const { _queuedAt, _retries, ...cleanMessage } = message;
                this.sendRaw(cleanMessage);
            });

            console.debug(`[SupabaseRealtime] flushed ${queue.length} offline messages`);
        },

        loadOfflineQueue() {
            try {
                const stored = localStorage.getItem(this.offlineQueueKey);
                if (stored) {
                    this.offlineQueue = JSON.parse(stored);
                }
            } catch (e) {
                this.offlineQueue = [];
            }
        },

        saveOfflineQueue() {
            try {
                localStorage.setItem(this.offlineQueueKey, JSON.stringify(this.offlineQueue));
            } catch (e) {
                console.warn('[SupabaseRealtime] failed to save offline queue', e);
            }
        },

        setupBeforeUnload() {
            window.addEventListener('beforeunload', () => {
                this.disconnect();
            });
        },

        setupOnlineListener() {
            window.addEventListener('online', () => {
                console.debug('[SupabaseRealtime] browser online');
                if (!this.connected) {
                    this.connect();
                }
            });

            window.addEventListener('offline', () => {
                console.debug('[SupabaseRealtime] browser offline');
                this.connected = false;
            });
        },

        dispatchEvent(name, detail) {
            window.dispatchEvent(new CustomEvent(name, { detail }));
        },

        getStatus() {
            return {
                connected: this.connected,
                channels: Object.keys(this.channels).length,
                offlineQueue: this.offlineQueue.length,
                reconnectAttempts: this.reconnectAttempts,
                projectRef: this.projectRef,
            };
        },
    };

    window.SupabaseRealtime = SupabaseRealtime;
})();
