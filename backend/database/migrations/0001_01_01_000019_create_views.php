<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql' && $driver !== 'supabase') {
            return;
        }

        DB::unprepared('
            -- User usage summary view
            CREATE OR REPLACE VIEW user_usage_summary AS
            SELECT
                u.id AS user_id,
                u.name AS user_name,
                u.email AS user_email,
                u.plan,
                u.role,
                u.api_usage_limit,
                u.api_usage_current,
                ROUND((u.api_usage_current::numeric / NULLIF(u.api_usage_limit, 0)) * 100, 1) AS usage_percent,
                u.created_at AS registered_at,
                (SELECT COUNT(*) FROM projects p WHERE p.user_id = u.id AND p.deleted_at IS NULL) AS project_count,
                (SELECT COUNT(*) FROM conversations c WHERE c.user_id = u.id) AS conversation_count,
                (SELECT COUNT(*) FROM ai_usage_logs a WHERE a.user_id = u.id AND a.created_at >= NOW() - INTERVAL \'30 days\') AS ai_calls_30d,
                (SELECT COALESCE(SUM(a.cost), 0) FROM ai_usage_logs a WHERE a.user_id = u.id AND a.created_at >= NOW() - INTERVAL \'30 days\') AS ai_cost_30d,
                (SELECT COUNT(*) FROM messages m WHERE m.user_id = u.id AND m.created_at >= NOW() - INTERVAL \'30 days\') AS messages_30d,
                u.deleted_at IS NOT NULL AS is_deleted
            FROM users u;

            -- Project overview with stats
            CREATE OR REPLACE VIEW project_overview AS
            SELECT
                p.id AS project_id,
                p.user_id,
                u.name AS owner_name,
                p.name AS project_name,
                p.slug,
                p.language,
                p.framework,
                p.status,
                p.visibility,
                p.is_public,
                p.created_at,
                p.last_accessed_at,
                (SELECT COUNT(*) FROM conversations c WHERE c.project_id = p.id) AS conversation_count,
                (SELECT COUNT(*) FROM messages m JOIN conversations c ON m.conversation_id = c.id WHERE c.project_id = p.id) AS message_count,
                (SELECT COUNT(*) FROM code_generations cg WHERE cg.project_id = p.id) AS code_gen_count,
                (SELECT COUNT(*) FROM files f WHERE f.project_id = p.id AND f.deleted_at IS NULL) AS file_count,
                (SELECT COALESCE(SUM(f.size), 0) FROM files f WHERE f.project_id = p.id AND f.deleted_at IS NULL) AS total_file_size,
                (SELECT COUNT(*) FROM project_user pu WHERE pu.project_id = p.id) AS shared_user_count,
                (SELECT COUNT(*) FROM project_team pt WHERE pt.project_id = p.id) AS team_count,
                (SELECT COALESCE(SUM(c.tokens_used), 0) FROM conversations c WHERE c.project_id = p.id) AS total_tokens_used,
                (SELECT COALESCE(SUM(c.total_cost), 0) FROM conversations c WHERE c.project_id = p.id) AS total_cost
            FROM projects p
            JOIN users u ON u.id = p.user_id;

            -- Team analytics view
            CREATE OR REPLACE VIEW team_analytics AS
            SELECT
                t.id AS team_id,
                t.name AS team_name,
                t.slug,
                t.plan,
                t.max_members,
                t.created_at,
                u.name AS owner_name,
                (SELECT COUNT(*) FROM team_user tu WHERE tu.team_id = t.id) AS member_count,
                (SELECT COUNT(*) FROM project_team pt WHERE pt.team_id = t.id) AS project_count,
                (SELECT COUNT(*) FROM project_team pt JOIN projects p ON p.id = pt.project_id WHERE pt.team_id = t.id AND p.deleted_at IS NULL) AS active_projects,
                (SELECT COALESCE(SUM(p.total_tokens_used), 0) FROM project_overview p WHERE p.project_id IN (SELECT pt2.project_id FROM project_team pt2 WHERE pt2.team_id = t.id)) AS total_tokens_used,
                (SELECT COALESCE(SUM(p.total_cost), 0) FROM project_overview p WHERE p.project_id IN (SELECT pt2.project_id FROM project_team pt2 WHERE pt2.team_id = t.id)) AS total_cost
            FROM teams t
            JOIN users u ON u.id = t.owner_id;

            -- Conversation stats view
            CREATE OR REPLACE VIEW conversation_stats AS
            SELECT
                c.id AS conversation_id,
                c.user_id,
                u.name AS user_name,
                c.project_id,
                p.name AS project_name,
                c.title,
                c.model_used,
                c.tokens_used,
                c.total_cost,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS message_count,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.role = \'user\') AS user_message_count,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.role = \'assistant\') AS assistant_message_count,
                c.created_at,
                (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id) AS last_message_at,
                EXTRACT(EPOCH FROM ((SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id) - c.created_at)) AS duration_seconds
            FROM conversations c
            JOIN users u ON u.id = c.user_id
            LEFT JOIN projects p ON p.id = c.project_id;

            -- AI usage daily summary view
            CREATE OR REPLACE VIEW ai_usage_daily AS
            SELECT
                created_at::date AS date,
                provider,
                model,
                COUNT(*) AS total_calls,
                COUNT(DISTINCT user_id) AS unique_users,
                SUM(prompt_tokens) AS total_prompt_tokens,
                SUM(completion_tokens) AS total_completion_tokens,
                SUM(cost) AS total_cost,
                AVG(duration)::integer AS avg_duration_ms,
                COUNT(*) FILTER (WHERE success = false) AS error_count,
                ROUND(
                    (COUNT(*) FILTER (WHERE success = false)::numeric / NULLIF(COUNT(*), 0)) * 100, 2
                ) AS error_rate_percent
            FROM ai_usage_logs
            GROUP BY created_at::date, provider, model
            ORDER BY date DESC;

            -- Storage usage view
            CREATE OR REPLACE VIEW storage_usage AS
            SELECT
                f.user_id,
                u.name AS user_name,
                f.bucket,
                COUNT(*) AS file_count,
                COALESCE(SUM(f.size), 0) AS total_size_bytes,
                ROUND(COALESCE(SUM(f.size), 0)::numeric / (1024 * 1024), 2) AS total_size_mb,
                MIN(f.created_at) AS first_file_at,
                MAX(f.created_at) AS last_file_at
            FROM files f
            JOIN users u ON u.id = f.user_id
            WHERE f.deleted_at IS NULL
            GROUP BY f.user_id, u.name, f.bucket;
        ');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql' && $driver !== 'supabase') {
            return;
        }

        DB::unprepared('
            DROP VIEW IF EXISTS user_usage_summary;
            DROP VIEW IF EXISTS project_overview;
            DROP VIEW IF EXISTS team_analytics;
            DROP VIEW IF EXISTS conversation_stats;
            DROP VIEW IF EXISTS ai_usage_daily;
            DROP VIEW IF EXISTS storage_usage;
        ');
    }
};
