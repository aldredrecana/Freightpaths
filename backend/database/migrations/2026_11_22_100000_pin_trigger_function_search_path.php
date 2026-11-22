<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Security hardening (Supabase Security Advisor: "Function Search Path Mutable").
// Fixing the search_path means these trigger functions always use our own "public" tables,
// so nobody can trick them with a same-named table in another schema.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('ALTER FUNCTION public.protect_finished_trips() SET search_path = public, pg_temp');
        DB::unprepared('ALTER FUNCTION public.block_log_updates() SET search_path = public, pg_temp');
    }

    public function down(): void
    {
        DB::unprepared('ALTER FUNCTION public.protect_finished_trips() RESET search_path');
        DB::unprepared('ALTER FUNCTION public.block_log_updates() RESET search_path');
    }
};