<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A backload is a return load: a new trip offered to the same truck after a delivery,
    // so it does not drive back empty. It points to the trip it follows.
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('backload_of_id')->nullable()->after('truck_id')
                ->constrained('schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('backload_of_id');
        });
    }
};