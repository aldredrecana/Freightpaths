<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Problem reports from drivers on the road (breakdown, flat tire, accident...).
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->constrained();
            $table->string('type', 30);
            $table->text('note')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('accuracy')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 300)->nullable();
            $table->timestamps();

            $table->index(['resolved_at', 'reported_at']);
            $table->index('schedule_id');
        });

        // The database itself only accepts the known problem types.
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_type_check
            CHECK (type IN ('breakdown', 'flat_tire', 'accident', 'road_blocked', 'other'))");

        // On Supabase: same protection as the other tables (no access through the public API roles).
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'anon') THEN
                    ALTER TABLE incidents ENABLE ROW LEVEL SECURITY;
                    REVOKE ALL ON incidents FROM anon, authenticated;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};