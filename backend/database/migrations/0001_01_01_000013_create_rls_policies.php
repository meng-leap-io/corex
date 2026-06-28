<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection !== 'pgsql' && $connection !== 'supabase') {
            return;
        }

        DB::unprepared('-- RLS Helper Functions

CREATE SCHEMA IF NOT EXISTS app;

CREATE OR REPLACE FUNCTION app.current_user_id()
RETURNS uuid
LANGUAGE plpgsql
STABLE
PARALLEL SAFE
AS $$
BEGIN
  RETURN NULLIF(current_setting(\'app.current_user_id\', true), \'\')::uuid;
END;
$$;

CREATE OR REPLACE FUNCTION app.current_user_role()
RETURNS text
LANGUAGE plpgsql
STABLE
PARALLEL SAFE
AS $$
BEGIN
  RETURN COALESCE(
    NULLIF(current_setting(\'app.current_user_role\', true), \'\'),
    \'anonymous\'
  );
END;
$$;

CREATE OR REPLACE FUNCTION app.is_admin()
RETURNS boolean
LANGUAGE plpgsql
STABLE
PARALLEL SAFE
AS $$
BEGIN
  RETURN app.current_user_role() = \'admin\';
END;
$$;

CREATE OR REPLACE FUNCTION app.is_team_member(project_id uuid)
RETURNS boolean
LANGUAGE plpgsql
STABLE
PARALLEL SAFE
AS $$
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM project_user pu
    WHERE pu.project_id = $1
      AND pu.user_id = app.current_user_id()
    UNION
    SELECT 1 FROM project_team pt
    JOIN team_user tu ON tu.team_id = pt.team_id
    WHERE pt.project_id = $1
      AND tu.user_id = app.current_user_id()
  );
END;
$$;

CREATE OR REPLACE FUNCTION app.user_has_team_access(project_id uuid, min_role text DEFAULT \'member\')
RETURNS boolean
LANGUAGE plpgsql
STABLE
PARALLEL SAFE
AS $$
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM project_user pu
    WHERE pu.project_id = $1
      AND pu.user_id = app.current_user_id()
      AND CASE
        WHEN min_role = \'admin\' THEN pu.role IN (\'admin\', \'owner\')
        WHEN min_role = \'editor\' THEN pu.role IN (\'admin\', \'owner\', \'editor\')
        ELSE true
      END
    UNION
    SELECT 1 FROM project_team pt
    JOIN team_user tu ON tu.team_id = pt.team_id
    WHERE pt.project_id = $1
      AND tu.user_id = app.current_user_id()
      AND CASE
        WHEN min_role = \'admin\' THEN tu.role IN (\'admin\', \'owner\')
        WHEN min_role = \'editor\' THEN tu.role IN (\'admin\', \'owner\', \'editor\')
        ELSE true
      END
  );
END;
$$;
');

        // ── users ──────────────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE users FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS users_own_row ON users;
DROP POLICY IF EXISTS users_admin_all ON users;
DROP POLICY IF EXISTS users_public_read ON users;

CREATE POLICY users_own_row ON users
  FOR ALL
  USING (id = app.current_user_id())
  WITH CHECK (id = app.current_user_id());

CREATE POLICY users_admin_all ON users
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── profiles ───────────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE profiles FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS profiles_own ON profiles;
DROP POLICY IF EXISTS profiles_admin_all ON profiles;
DROP POLICY IF EXISTS profiles_public_read ON profiles;

CREATE POLICY profiles_own ON profiles
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY profiles_admin_all ON profiles
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── projects ───────────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE projects ENABLE ROW LEVEL SECURITY;
ALTER TABLE projects FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS projects_own ON projects;
DROP POLICY IF EXISTS projects_team_access ON projects;
DROP POLICY IF EXISTS projects_admin_all ON projects;
DROP POLICY IF EXISTS projects_public_read ON projects;

CREATE POLICY projects_own ON projects
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY projects_team_access ON projects
  FOR ALL
  USING (app.user_has_team_access(id))
  WITH CHECK (app.user_has_team_access(id));

CREATE POLICY projects_admin_all ON projects
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());

CREATE POLICY projects_public_read ON projects
  FOR SELECT
  USING (is_public = true);
');

        // ── conversations ──────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE conversations ENABLE ROW LEVEL SECURITY;
ALTER TABLE conversations FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS conversations_own ON conversations;
DROP POLICY IF EXISTS conversations_team_access ON conversations;
DROP POLICY IF EXISTS conversations_admin_all ON conversations;

CREATE POLICY conversations_own ON conversations
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY conversations_team_access ON conversations
  FOR ALL
  USING (
    project_id IS NOT NULL
    AND app.user_has_team_access(project_id)
  )
  WITH CHECK (
    project_id IS NOT NULL
    AND app.user_has_team_access(project_id)
  );

CREATE POLICY conversations_admin_all ON conversations
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── files ──────────────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE files ENABLE ROW LEVEL SECURITY;
ALTER TABLE files FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS files_own ON files;
DROP POLICY IF EXISTS files_team_access ON files;
DROP POLICY IF EXISTS files_admin_all ON files;

CREATE POLICY files_own ON files
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY files_team_access ON files
  FOR ALL
  USING (
    project_id IS NOT NULL
    AND app.user_has_team_access(project_id)
  )
  WITH CHECK (
    project_id IS NOT NULL
    AND app.user_has_team_access(project_id)
  );

CREATE POLICY files_admin_all ON files
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── notifications ──────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS notifications_own ON notifications;
DROP POLICY IF EXISTS notifications_admin_all ON notifications;

CREATE POLICY notifications_own ON notifications
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY notifications_admin_all ON notifications
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── api_keys ───────────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE api_keys ENABLE ROW LEVEL SECURITY;
ALTER TABLE api_keys FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS api_keys_own ON api_keys;
DROP POLICY IF EXISTS api_keys_admin_all ON api_keys;

CREATE POLICY api_keys_own ON api_keys
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY api_keys_admin_all ON api_keys
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── subscriptions ──────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE subscriptions FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS subscriptions_own ON subscriptions;
DROP POLICY IF EXISTS subscriptions_admin_all ON subscriptions;

CREATE POLICY subscriptions_own ON subscriptions
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY subscriptions_admin_all ON subscriptions
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── ai_usage_logs ──────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE ai_usage_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE ai_usage_logs FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS ai_usage_logs_own ON ai_usage_logs;
DROP POLICY IF EXISTS ai_usage_logs_admin_all ON ai_usage_logs;

CREATE POLICY ai_usage_logs_own ON ai_usage_logs
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY ai_usage_logs_admin_all ON ai_usage_logs
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── code_generations ──────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE code_generations ENABLE ROW LEVEL SECURITY;
ALTER TABLE code_generations FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS code_generations_own ON code_generations;
DROP POLICY IF EXISTS code_generations_team_access ON code_generations;
DROP POLICY IF EXISTS code_generations_admin_all ON code_generations;

CREATE POLICY code_generations_own ON code_generations
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY code_generations_team_access ON code_generations
  FOR ALL
  USING (
    project_id IS NOT NULL
    AND app.user_has_team_access(project_id)
  )
  WITH CHECK (
    project_id IS NOT NULL
    AND app.user_has_team_access(project_id)
  );

CREATE POLICY code_generations_admin_all ON code_generations
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── teams (RLS for team table itself) ──────────────────────────────────
        DB::unprepared('
ALTER TABLE teams ENABLE ROW LEVEL SECURITY;
ALTER TABLE teams FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS teams_own ON teams;
DROP POLICY IF EXISTS teams_member_access ON teams;
DROP POLICY IF EXISTS teams_admin_all ON teams;

CREATE POLICY teams_own ON teams
  FOR ALL
  USING (owner_id = app.current_user_id())
  WITH CHECK (owner_id = app.current_user_id());

CREATE POLICY teams_member_access ON teams
  FOR ALL
  USING (
    EXISTS (
      SELECT 1 FROM team_user
      WHERE team_user.team_id = teams.id
        AND team_user.user_id = app.current_user_id()
    )
  );

CREATE POLICY teams_admin_all ON teams
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── team_user ──────────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE team_user ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_user FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS team_user_self ON team_user;
DROP POLICY IF EXISTS team_user_team_admin ON team_user;
DROP POLICY IF EXISTS team_user_admin_all ON team_user;

CREATE POLICY team_user_self ON team_user
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY team_user_team_admin ON team_user
  FOR ALL
  USING (
    EXISTS (
      SELECT 1 FROM team_user tu
      WHERE tu.team_id = team_user.team_id
        AND tu.user_id = app.current_user_id()
        AND tu.role IN (\'admin\', \'owner\')
    )
  );

CREATE POLICY team_user_admin_all ON team_user
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── project_user ───────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE project_user ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS project_user_self ON project_user;
DROP POLICY IF EXISTS project_user_owner ON project_user;
DROP POLICY IF EXISTS project_user_admin_all ON project_user;

CREATE POLICY project_user_self ON project_user
  FOR ALL
  USING (user_id = app.current_user_id())
  WITH CHECK (user_id = app.current_user_id());

CREATE POLICY project_user_owner ON project_user
  FOR ALL
  USING (
    EXISTS (
      SELECT 1 FROM projects
      WHERE projects.id = project_user.project_id
        AND projects.user_id = app.current_user_id()
    )
  );

CREATE POLICY project_user_admin_all ON project_user
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');

        // ── project_team ───────────────────────────────────────────────────────
        DB::unprepared('
ALTER TABLE project_team ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS project_team_owner ON project_team;
DROP POLICY IF EXISTS project_team_admin_all ON project_team;

CREATE POLICY project_team_owner ON project_team
  FOR ALL
  USING (
    EXISTS (
      SELECT 1 FROM projects
      WHERE projects.id = project_team.project_id
        AND projects.user_id = app.current_user_id()
    )
  );

CREATE POLICY project_team_admin_all ON project_team
  FOR ALL
  USING (app.is_admin())
  WITH CHECK (app.is_admin());
');
    }

    public function down(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection !== 'pgsql' && $connection !== 'supabase') {
            return;
        }

        $tables = [
            'users', 'profiles', 'projects', 'conversations', 'files',
            'notifications', 'api_keys', 'subscriptions', 'ai_usage_logs',
            'code_generations', 'teams', 'team_user', 'project_user', 'project_team',
        ];

        foreach ($tables as $table) {
            DB::unprepared("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
        }
    }
};
