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
            -- Update timestamps trigger (generic)
            CREATE OR REPLACE FUNCTION update_timestamp()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$;

            -- Update conversation stats on new message
            CREATE OR REPLACE FUNCTION update_conversation_on_message()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                UPDATE conversations
                SET
                    tokens_used = tokens_used + NEW.total_tokens,
                    total_cost = total_cost + NEW.cost,
                    updated_at = NOW()
                WHERE id = NEW.conversation_id;
                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS trg_message_after_insert ON messages;
            CREATE TRIGGER trg_message_after_insert
                AFTER INSERT ON messages
                FOR EACH ROW
                EXECUTE FUNCTION update_conversation_on_message();

            -- Update user api_usage on ai_usage_logs insert
            CREATE OR REPLACE FUNCTION update_user_usage_on_ai_log()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.user_id IS NOT NULL AND NEW.success THEN
                    UPDATE users
                    SET api_usage_current = api_usage_current + NEW.prompt_tokens + NEW.completion_tokens
                    WHERE id = NEW.user_id;
                END IF;
                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS trg_ai_usage_log_after_insert ON ai_usage_logs;
            CREATE TRIGGER trg_ai_usage_log_after_insert
                AFTER INSERT ON ai_usage_logs
                FOR EACH ROW
                EXECUTE FUNCTION update_user_usage_on_ai_log();

            -- Update project last_accessed_at on conversation or code_generation
            CREATE OR REPLACE FUNCTION update_project_last_accessed()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.project_id IS NOT NULL THEN
                    UPDATE projects
                    SET last_accessed_at = NOW()
                    WHERE id = NEW.project_id;
                END IF;
                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS trg_conversation_after_insert ON conversations;
            CREATE TRIGGER trg_conversation_after_insert
                AFTER INSERT ON conversations
                FOR EACH ROW
                EXECUTE FUNCTION update_project_last_accessed();

            DROP TRIGGER IF EXISTS trg_code_generation_after_insert ON code_generations;
            CREATE TRIGGER trg_code_generation_after_insert
                AFTER INSERT ON code_generations
                FOR EACH ROW
                EXECUTE FUNCTION update_project_last_accessed();

            -- Prevent overlapping active subscriptions
            CREATE OR REPLACE FUNCTION prevent_duplicate_active_subscription()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.status = \'active\' THEN
                    UPDATE subscriptions
                    SET status = \'cancelled\', cancelled_at = NOW()
                    WHERE user_id = NEW.user_id
                      AND id != NEW.id
                      AND status = \'active\';
                END IF;
                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS trg_subscription_before_insert ON subscriptions;
            CREATE TRIGGER trg_subscription_before_insert
                BEFORE INSERT ON subscriptions
                FOR EACH ROW
                EXECUTE FUNCTION prevent_duplicate_active_subscription();

            DROP TRIGGER IF EXISTS trg_subscription_before_update ON subscriptions;
            CREATE TRIGGER trg_subscription_before_update
                BEFORE UPDATE OF status ON subscriptions
                FOR EACH ROW
                WHEN (NEW.status = \'active\')
                EXECUTE FUNCTION prevent_duplicate_active_subscription();

            -- Auto-create profile on user creation
            CREATE OR REPLACE FUNCTION auto_create_profile()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                INSERT INTO profiles (id, user_id, created_at, updated_at)
                VALUES (gen_random_uuid(), NEW.id, NOW(), NOW())
                ON CONFLICT (user_id) DO NOTHING;
                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS trg_user_after_insert ON users;
            CREATE TRIGGER trg_user_after_insert
                AFTER INSERT ON users
                FOR EACH ROW
                EXECUTE FUNCTION auto_create_profile();

            -- Prevent team member limit from being exceeded
            CREATE OR REPLACE FUNCTION check_team_member_limit()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                max_members integer;
                current_count integer;
            BEGIN
                SELECT t.max_members INTO max_members FROM teams t WHERE t.id = NEW.team_id;
                SELECT COUNT(*) INTO current_count FROM team_user WHERE team_id = NEW.team_id;
                IF current_count >= max_members THEN
                    RAISE EXCEPTION \'Team member limit of % reached\', max_members;
                END IF;
                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS trg_team_user_before_insert ON team_user;
            CREATE TRIGGER trg_team_user_before_insert
                BEFORE INSERT ON team_user
                FOR EACH ROW
                EXECUTE FUNCTION check_team_member_limit();
        ');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql' && $driver !== 'supabase') {
            return;
        }

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_message_after_insert ON messages;
            DROP TRIGGER IF EXISTS trg_ai_usage_log_after_insert ON ai_usage_logs;
            DROP TRIGGER IF EXISTS trg_conversation_after_insert ON conversations;
            DROP TRIGGER IF EXISTS trg_code_generation_after_insert ON code_generations;
            DROP TRIGGER IF EXISTS trg_subscription_before_insert ON subscriptions;
            DROP TRIGGER IF EXISTS trg_subscription_before_update ON subscriptions;
            DROP TRIGGER IF EXISTS trg_user_after_insert ON users;
            DROP TRIGGER IF EXISTS trg_team_user_before_insert ON team_user;

            DROP FUNCTION IF EXISTS update_timestamp();
            DROP FUNCTION IF EXISTS update_conversation_on_message();
            DROP FUNCTION IF EXISTS update_user_usage_on_ai_log();
            DROP FUNCTION IF EXISTS update_project_last_accessed();
            DROP FUNCTION IF EXISTS prevent_duplicate_active_subscription();
            DROP FUNCTION IF EXISTS auto_create_profile();
            DROP FUNCTION IF EXISTS check_team_member_limit();
        ');
    }
};
