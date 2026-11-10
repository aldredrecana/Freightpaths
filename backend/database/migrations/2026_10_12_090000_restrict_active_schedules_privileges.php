<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ALTER DEFAULT PRIVILEGES gives the app role write access to every new
     * "table", and PostgreSQL counts a view as one. The view is only for
     * reading, so the app role keeps SELECT and loses INSERT/UPDATE/DELETE.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'freightpath_app') THEN
                    REVOKE INSERT, UPDATE, DELETE ON active_schedules FROM freightpath_app;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'freightpath_app') THEN
                    GRANT INSERT, UPDATE, DELETE ON active_schedules TO freightpath_app;
                END IF;
            END
            $$;
        SQL);
    }
};
