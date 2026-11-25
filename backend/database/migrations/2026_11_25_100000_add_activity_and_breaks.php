<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 1. What the phone's Activity Recognition said with each GPS point (still, walking...).
    // 2. Breaks the driver starts and ends with the "Take a break" button.
    public function up(): void
    {
        Schema::table('location_pings', function (Blueprint $table) {
            $table->string('activity', 10)->nullable();
            $table->smallInteger('activity_confidence')->nullable();
        });

        DB::statement("ALTER TABLE location_pings ADD CONSTRAINT location_pings_activity_check
            CHECK (activity IS NULL OR activity IN ('still', 'walking', 'running', 'driving', 'cycling', 'unknown'))");

        Schema::create('driver_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['schedule_id', 'started_at']);
        });

        DB::statement('ALTER TABLE driver_breaks ADD CONSTRAINT driver_breaks_order_check
            CHECK (ended_at IS NULL OR ended_at >= started_at)');

        // On Supabase: same protection as the other tables (no access through the public API roles).
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'anon') THEN
                    ALTER TABLE driver_breaks ENABLE ROW LEVEL SECURITY;
                    REVOKE ALL ON driver_breaks FROM anon, authenticated;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_breaks');

        DB::statement('ALTER TABLE location_pings DROP CONSTRAINT IF EXISTS location_pings_activity_check');
        Schema::table('location_pings', function (Blueprint $table) {
            $table->dropColumn(['activity', 'activity_confidence']);
        });
    }
};