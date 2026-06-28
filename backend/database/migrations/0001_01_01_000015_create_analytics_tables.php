<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('category', 50)->nullable();
            $table->string('label', 255)->nullable();
            $table->float('value')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('session_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'event_type', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['category', 'event_type']);
            $table->index('session_id');
            $table->index('created_at');
        });

        Schema::create('feature_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature', 100);
            $table->string('action', 100);
            $table->jsonb('context')->default('{}');
            $table->boolean('success')->default(true);
            $table->float('duration_ms')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'feature', 'created_at']);
            $table->index(['feature', 'action']);
            $table->index('created_at');
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 500);
            $table->string('route', 255)->nullable();
            $table->string('method', 10)->default('GET');
            $table->integer('status_code')->default(200);
            $table->float('duration_ms')->nullable();
            $table->float('query_time_ms')->nullable();
            $table->integer('memory_bytes')->nullable();
            $table->jsonb('query_log')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referer', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['path', 'created_at']);
            $table->index('status_code');
            $table->index('created_at');
            $table->index('duration_ms');
        });

        Schema::create('custom_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('metric_key', 255);
            $table->string('metric_type', 50)->default('gauge');
            $table->float('value');
            $table->jsonb('tags')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->string('source', 100)->nullable();
            $table->timestampTz('recorded_at')->useCurrent();

            $table->index('metric_key');
            $table->index(['metric_key', 'recorded_at']);
            $table->index('recorded_at');
        });

        Schema::create('performance_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->float('cpu_load')->nullable();
            $table->float('memory_used_mb')->nullable();
            $table->float('memory_total_mb')->nullable();
            $table->float('disk_used_mb')->nullable();
            $table->float('disk_total_mb')->nullable();
            $table->float('network_in_mb')->nullable();
            $table->float('network_out_mb')->nullable();
            $table->integer('active_connections')->nullable();
            $table->integer('queue_size')->nullable();
            $table->float('request_rate_per_min')->nullable();
            $table->float('avg_response_time_ms')->nullable();
            $table->float('p95_response_time_ms')->nullable();
            $table->float('p99_response_time_ms')->nullable();
            $table->integer('error_count_5m')->nullable();
            $table->jsonb('services')->nullable();
            $table->jsonb('extra')->nullable();
            $table->timestampTz('recorded_at')->useCurrent();

            $table->index('recorded_at');
        });

        $connection = DB::connection()->getDriverName();
        if ($connection === 'pgsql' || $connection === 'supabase') {
            DB::unprepared('
                CREATE MATERIALIZED VIEW IF NOT EXISTS mv_daily_metrics AS
                SELECT
                    ae.created_at::date AS date,
                    ae.event_type,
                    ae.category,
                    COUNT(*) AS event_count,
                    COUNT(DISTINCT ae.user_id) AS unique_users,
                    AVG(ae.value) AS avg_value,
                    SUM(ae.value) AS total_value
                FROM analytics_events ae
                GROUP BY ae.created_at::date, ae.event_type, ae.category
                WITH DATA;

                CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_daily_metrics_date
                ON mv_daily_metrics (date, event_type, category);

                CREATE MATERIALIZED VIEW IF NOT EXISTS mv_feature_usage_daily AS
                SELECT
                    fu.created_at::date AS date,
                    fu.feature,
                    fu.action,
                    COUNT(*) AS usage_count,
                    COUNT(DISTINCT fu.user_id) AS unique_users,
                    AVG(fu.duration_ms) AS avg_duration_ms,
                    COUNT(*) FILTER (WHERE fu.success = false) AS error_count
                FROM feature_usage fu
                GROUP BY fu.created_at::date, fu.feature, fu.action
                WITH DATA;

                CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_feature_usage_daily
                ON mv_feature_usage_daily (date, feature, action);
            ');
        }
    }

    public function down(): void
    {
        $connection = DB::connection()->getDriverName();
        if ($connection === 'pgsql' || $connection === 'supabase') {
            DB::unprepared('
                DROP MATERIALIZED VIEW IF EXISTS mv_feature_usage_daily;
                DROP MATERIALIZED VIEW IF EXISTS mv_daily_metrics;
            ');
        }

        Schema::dropIfExists('performance_snapshots');
        Schema::dropIfExists('custom_metrics');
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('feature_usage');
        Schema::dropIfExists('analytics_events');
    }
};
