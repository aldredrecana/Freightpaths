<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Proof of delivery: a photo of the delivered goods and the receiver's signature.
    // The pictures are kept in the database (as base64 text), so they survive redeploys
    // without a separate file storage service.
    public function up(): void
    {
        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('photo');      // JPEG, base64
            $table->text('signature');  // PNG, base64
            $table->string('received_by', 100);
            $table->string('note', 300)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'anon') THEN
                    ALTER TABLE delivery_proofs ENABLE ROW LEVEL SECURITY;
                    REVOKE ALL ON delivery_proofs FROM anon, authenticated;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_proofs');
    }
};