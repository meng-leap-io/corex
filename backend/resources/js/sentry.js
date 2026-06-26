// Corex.dev Frontend Sentry Configuration
// Loaded via CDN or npm package depending on build setup

const SENTRY_DSN = document.querySelector('meta[name="sentry-dsn"]')?.getAttribute('content');
const APP_ENV = document.querySelector('meta[name="app-env"]')?.getAttribute('content') || 'production';
const APP_VERSION = document.querySelector('meta[name="app-version"]')?.getAttribute('content') || '1.0.0';
const USER_ID = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
const USER_EMAIL = document.querySelector('meta[name="user-email"]')?.getAttribute('content');

export function initSentry() {
  if (!SENTRY_DSN) {
    console.debug('[Sentry] disabled: no DSN configured');
    return;
  }

  // Dynamic import of Sentry browser SDK
  import('https://browser.sentry-cdn.com/7.119.0/bundle.es5.min.js')
    .then(({ Sentry }) => {
      Sentry.init({
        dsn: SENTRY_DSN,
        environment: APP_ENV,
        release: APP_VERSION,
        sampleRate: 1.0,
        tracesSampleRate: 0.25,
        replaysSessionSampleRate: 0.1,
        replaysOnErrorSampleRate: 1.0,
        attachStacktrace: true,
        autoSessionTracking: true,
        sendClientReports: true,
        integrations: [
          Sentry.browserTracingIntegration(),
          Sentry.replayIntegration({
            maskAllText: true,
            blockAllMedia: true,
          }),
        ],
        tracePropagationTargets: [
          'localhost',
          /^https:\/\/api\.corex\.dev/,
          /^https:\/\/corex\.dev/,
        ],
        beforeSend(event) {
          // Sanitize sensitive data
          if (event.request?.headers) {
            delete event.request.headers['Authorization'];
            delete event.request.headers['X-CSRF-TOKEN'];
          }
          return event;
        },
      });

      // Set user context if available
      if (USER_ID) {
        Sentry.setUser({
          id: USER_ID,
          email: USER_EMAIL || undefined,
        });
      }

      // Set tags
      Sentry.setTag('service', 'frontend');
      Sentry.setTag('platform', 'web');

      console.debug('[Sentry] initialized', { env: APP_ENV, version: APP_VERSION });
    })
    .catch((err) => {
      console.warn('[Sentry] failed to load:', err.message);
    });
}

// Convenience wrapper for capturing errors
export function captureError(error, context = {}) {
  import('https://browser.sentry-cdn.com/7.119.0/bundle.es5.min.js')
    .then(({ Sentry }) => {
      Sentry.withScope((scope) => {
        Object.entries(context).forEach(([key, value]) => {
          scope.setExtra(key, value);
        });
        Sentry.captureException(error);
      });
    })
    .catch(() => {});
}

// Performance monitoring helpers
export function startTransaction(name, op = 'ui.interaction') {
  import('https://browser.sentry-cdn.com/7.119.0/bundle.es5.min.js')
    .then(({ Sentry }) => {
      const transaction = Sentry.startTransaction({ name, op });
      Sentry.getCurrentHub().configureScope((scope) => scope.setSpan(transaction));
      return transaction;
    })
    .catch(() => null);
}
