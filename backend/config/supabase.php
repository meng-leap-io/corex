<?php

declare(strict_types=1);
use App\Models\AiUsageLog;
use App\Models\ApiKey;
use App\Models\CodeGeneration;
use App\Models\Conversation;
use App\Models\File;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

return [
    'url' => env('SUPABASE_URL', 'https://iprhzagvffgpfihrmeqd.supabase.co'),

    'key' => env('SUPABASE_KEY', 'sb_publishable_DBnWTqXK0l2LhAVtYMenXg_2JhBx'),

    'jwt_secret' => env('SUPABASE_JWT_SECRET'),

    'service_key' => env('SUPABASE_SERVICE_KEY'),

    'database' => [
        'connection' => env('SUPABASE_DB_CONNECTION', 'supabase'),
        'host' => env('SUPABASE_DB_HOST', 'aws-0-us-east-1.pooler.supabase.com'),
        'port' => env('SUPABASE_DB_PORT', '5432'),
        'database' => env('SUPABASE_DB_DATABASE', 'postgres'),
        'username' => env('SUPABASE_DB_USERNAME', 'iprhzagvffgpfihrmeqd'),
        'password' => env('SUPABASE_DB_PASSWORD'),
        'sslmode' => env('SUPABASE_DB_SSLMODE', 'require'),
    ],

    'auth' => [
        'auto_confirm' => env('SUPABASE_AUTO_CONFIRM', false),
        'redirect_url' => env('SUPABASE_REDIRECT_URL', '/auth/callback'),
        'remember_days' => env('SUPABASE_REMEMBER_DAYS', 30),
        'providers' => [
            'google' => [
                'enabled' => true,
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            ],
        ],
    ],

    'storage' => [
        'bucket' => env('SUPABASE_STORAGE_BUCKET', 'app-files'),
        'public_url' => env('SUPABASE_URL').'/storage/v1/object/public',

        'public_buckets' => ['avatars', 'exports'],

        'buckets' => [
            'projects' => [
                'public' => false,
                'max_size' => 100 * 1024 * 1024,
                'mime_types' => [
                    'text/plain', 'text/html', 'text/css', 'text/javascript',
                    'application/json', 'application/xml',
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf',
                    'application/x-httpd-php',
                    'text/x-python', 'text/x-go', 'text/x-rust',
                ],
                'extensions' => [
                    'php', 'js', 'ts', 'vue', 'html', 'css', 'scss', 'less',
                    'json', 'xml', 'yaml', 'yml', 'md',
                    'py', 'go', 'rs', 'java', 'kt', 'swift',
                    'sql', 'sh', 'bat', 'ps1',
                    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
                    'pdf', 'txt', 'csv',
                ],
            ],
            'avatars' => [
                'public' => true,
                'max_size' => 2 * 1024 * 1024,
                'min_size' => 1024,
                'mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'max_width' => 1024,
                'max_height' => 1024,
            ],
            'documents' => [
                'public' => false,
                'max_size' => 50 * 1024 * 1024,
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain', 'text/csv', 'text/markdown',
                    'application/json',
                ],
                'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'md', 'json'],
            ],
            'exports' => [
                'public' => true,
                'max_size' => 500 * 1024 * 1024,
                'mime_types' => ['application/json', 'application/zip', 'application/x-tar', 'application/gzip'],
                'extensions' => ['json', 'zip', 'tar', 'gz', 'tgz'],
            ],
        ],

        'cache' => [
            'disk' => env('SUPABASE_CACHE_DISK', 'local'),
            'ttl' => env('SUPABASE_CACHE_TTL', 3600),
            'max_size' => env('SUPABASE_CACHE_MAX_SIZE', 500 * 1024 * 1024),
        ],

        'image_optimization' => [
            'enabled' => env('SUPABASE_IMAGE_OPTIMIZATION', true),
            'quality' => env('SUPABASE_IMAGE_QUALITY', 85),
            'max_width' => env('SUPABASE_IMAGE_MAX_WIDTH', 1920),
            'max_height' => env('SUPABASE_IMAGE_MAX_HEIGHT', 1920),
            'output_format' => 'webp',
        ],

        'sharing' => [
            'default_expires_in' => env('SUPABASE_SHARE_EXPIRES', 86400),
            'max_expires_in' => env('SUPABASE_SHARE_MAX_EXPIRES', 604800),
        ],
    ],

    'realtime' => [
        'enabled' => env('SUPABASE_REALTIME_ENABLED', true),
        'key' => env('SUPABASE_REALTIME_KEY', env('SUPABASE_KEY')),

        'channels' => [
            'chat' => [
                'pattern' => 'chat:{id}',
                'private' => true,
                'events' => ['message.sent', 'message.updated', 'message.deleted'],
            ],
            'project' => [
                'pattern' => 'project:{id}',
                'private' => false,
                'events' => ['project.updated', 'project.deleted', 'presence.updated'],
            ],
            'notifications' => [
                'pattern' => 'notifications:{id}',
                'private' => true,
                'events' => ['notification.created', 'notification.read'],
            ],
            'team' => [
                'pattern' => 'team:{id}',
                'private' => false,
                'events' => ['presence.updated', 'member.joined', 'member.left'],
            ],
            'user' => [
                'pattern' => 'user:{id}',
                'private' => true,
                'events' => ['user.updated', 'user.status'],
            ],
        ],

        'reconnect' => [
            'max_attempts' => env('SUPABASE_REALTIME_MAX_RECONNECT', 10),
            'initial_delay' => env('SUPABASE_REALTIME_RECONNECT_DELAY', 1000),
            'max_delay' => env('SUPABASE_REALTIME_MAX_RECONNECT_DELAY', 30000),
            'backoff_multiplier' => 1.5,
        ],

        'heartbeat' => [
            'interval' => env('SUPABASE_REALTIME_HEARTBEAT', 30),
        ],

        'offline_queue' => [
            'max_size' => env('SUPABASE_REALTIME_QUEUE_SIZE', 1000),
            'retry_attempts' => env('SUPABASE_REALTIME_QUEUE_RETRIES', 3),
        ],
    ],

    'rls' => [
        'enabled' => env('SUPABASE_RLS_ENABLED', true),

        'db_connection' => env('SUPABASE_RLS_CONNECTION', 'pgsql'),

        'connection' => [
            'pgsql' => env('SUPABASE_RLS_PGSQL', true),
            'supabase' => env('SUPABASE_RLS_SUPABASE', true),
            'sqlite' => env('SUPABASE_RLS_SQLITE', false),
        ],

        'tables' => [
            'users' => true,
            'profiles' => true,
            'projects' => true,
            'conversations' => true,
            'files' => true,
            'notifications' => true,
            'api_keys' => true,
            'subscriptions' => true,
            'ai_usage_logs' => true,
            'code_generations' => true,
            'teams' => true,
            'team_user' => true,
            'project_user' => true,
        ],

        'user_id_field' => [
            'users' => 'id',
            'profiles' => 'user_id',
            'projects' => 'user_id',
            'conversations' => 'user_id',
            'files' => 'user_id',
            'notifications' => 'user_id',
            'api_keys' => 'user_id',
            'subscriptions' => 'user_id',
            'ai_usage_logs' => 'user_id',
            'code_generations' => 'user_id',
            'teams' => 'owner_id',
        ],

        'roles' => [
            'admin' => 'admin',
            'user' => 'user',
            'moderator' => 'moderator',
            'anonymous' => 'anonymous',
        ],

        'admin_bypass' => env('SUPABASE_RLS_ADMIN_BYPASS', true),

        'policy_prefix' => env('SUPABASE_RLS_POLICY_PREFIX', 'corex_'),

        'session_vars' => [
            'user_id' => 'app.current_user_id',
            'user_role' => 'app.current_user_role',
            'user_email' => 'app.current_user_email',
            'ip_address' => 'app.ip_address',
        ],

        'cache' => [
            'enabled' => env('SUPABASE_RLS_CACHE_ENABLED', true),
            'ttl' => env('SUPABASE_RLS_CACHE_TTL', 300),
        ],

        'encryption' => [
            'enabled' => env('SUPABASE_ENCRYPTION_ENABLED', true),
            'api_key_value' => env('SUPABASE_ENCRYPT_API_KEYS', true),
            'provider_tokens' => env('SUPABASE_ENCRYPT_PROVIDER_TOKENS', true),
            'sensitive_settings' => env('SUPABASE_ENCRYPT_SETTINGS', true),
        ],
    ],

    'analytics' => [
        'enabled' => env('SUPABASE_ANALYTICS_ENABLED', true),

        'retention' => [
            'analytics_events' => env('ANALYTICS_EVENTS_RETENTION', 90),
            'feature_usage' => env('ANALYTICS_FEATURE_RETENTION', 90),
            'page_views' => env('ANALYTICS_PAGE_VIEWS_RETENTION', 90),
            'custom_metrics' => env('ANALYTICS_CUSTOM_METRICS_RETENTION', 30),
            'performance_snapshots' => env('ANALYTICS_SNAPSHOTS_RETENTION', 30),
        ],

        'aggregation' => [
            'schedule' => env('ANALYTICS_AGGREGATION_SCHEDULE', 'hourly'),
            'refresh_views' => env('ANALYTICS_REFRESH_VIEWS', true),
        ],

        'tracking' => [
            'page_views' => env('ANALYTICS_TRACK_PAGE_VIEWS', true),
            'feature_usage' => env('ANALYTICS_TRACK_FEATURE_USAGE', true),
            'performance_snapshots' => env('ANALYTICS_TRACK_PERFORMANCE', true),
        ],

        'realtime' => [
            'enabled' => env('ANALYTICS_REALTIME_ENABLED', true),
            'broadcast_interval' => env('ANALYTICS_BROADCAST_INTERVAL', 60),
        ],

        'alerts' => [
            'error_threshold' => env('ANALYTICS_ERROR_THRESHOLD', 50),
            'response_time_threshold_ms' => env('ANALYTICS_RESPONSE_TIME_THRESHOLD', 2000),
            'p95_threshold_ms' => env('ANALYTICS_P95_THRESHOLD', 5000),
        ],
    ],

    'sync' => [
        'enabled' => env('SUPABASE_SYNC_ENABLED', true),

        'table_sync_tracking' => env('SUPABASE_SYNC_TABLE', 'sync_tracker'),

        'batch_size' => env('SUPABASE_SYNC_BATCH_SIZE', 100),

        'retry_attempts' => env('SUPABASE_SYNC_RETRY_ATTEMPTS', 3),

        'auto_sync' => env('SUPABASE_AUTO_SYNC', true),

        'sync_interval' => env('SUPABASE_SYNC_INTERVAL', 30),

        'versioning' => [
            'enabled' => env('SUPABASE_SYNC_VERSIONING', true),
            'column' => 'sync_version',
            'increment_by' => 1,
        ],

        'conflict' => [
            'default_strategy' => env('SUPABASE_SYNC_CONFLICT_STRATEGY', 'last_write_wins'),
            'auto_resolve' => env('SUPABASE_SYNC_AUTO_RESOLVE', true),
            'max_auto_fields' => 5,
            'max_version_gap' => 10,
            'max_time_gap_hours' => 24,
        ],

        'queue' => [
            'driver' => env('SUPABASE_SYNC_QUEUE_DRIVER', 'redis'),
            'prefix' => env('SUPABASE_SYNC_QUEUE_PREFIX', 'sync_queue'),
            'max_size' => env('SUPABASE_SYNC_QUEUE_MAX_SIZE', 5000),
            'retry_backoff_base' => env('SUPABASE_SYNC_RETRY_BACKOFF', 5),
            'retry_backoff_max' => env('SUPABASE_SYNC_RETRY_BACKOFF_MAX', 300),
        ],

        'worker' => [
            'enabled' => env('SUPABASE_SYNC_WORKER_ENABLED', true),
            'batch_size' => env('SUPABASE_SYNC_WORKER_BATCH', 25),
            'poll_interval' => env('SUPABASE_SYNC_WORKER_POLL', 3),
            'max_jobs_per_run' => env('SUPABASE_SYNC_WORKER_MAX_JOBS', 500),
            'sleep_on_empty' => env('SUPABASE_SYNC_WORKER_SLEEP', 5),
        ],

        'snapshots' => [
            'enabled' => env('SUPABASE_SYNC_SNAPSHOTS', true),
            'max_per_record' => env('SUPABASE_SYNC_MAX_SNAPSHOTS', 10),
            'ttl_days' => env('SUPABASE_SYNC_SNAPSHOT_TTL', 30),
        ],

        'conflict_fields' => [
            'local_preferred' => [
                'id', 'created_at', 'updated_at', 'sync_version', 'user_id',
                'local_updated_at', 'cached_at',
            ],
            'remote_preferred' => [
                'id', 'supabase_id', 'supabase_updated_at',
            ],
            'numeric_merge' => [
                'token_count', 'message_count', 'tokens_prompt', 'tokens_completion',
                'total_cost', 'total_cost_usd', 'usage_count', 'api_usage_current',
                'api_usage_limit', 'size', 'views',
            ],
        ],

        'skip_diff_fields' => [
            'updated_at', 'created_at', 'sync_version', 'sync_resolved_at',
            'deleted_at', 'synced_at',
        ],

        'model_map' => [
            'users' => User::class,
            'projects' => Project::class,
            'conversations' => Conversation::class,
            'profiles' => Profile::class,
            'ai_usage_logs' => AiUsageLog::class,
            'code_generations' => CodeGeneration::class,
            'subscriptions' => Subscription::class,
            'api_keys' => ApiKey::class,
            'files' => File::class,
        ],
    ],
];
