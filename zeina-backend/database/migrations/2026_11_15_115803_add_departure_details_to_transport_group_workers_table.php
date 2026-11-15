<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transport_group_workers', function (Blueprint $table) {
            $table->foreignId('departure_point_id')->nullable()->after('dropoff_sequence')->constrained('departure_points')->nullOnDelete();
            $table->string('departure_time')->nullable()->after('departure_point_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_group_workers', function (Blueprint $table) {
            $table->dropForeign(['departure_point_id']);
            $table->dropColumn(['departure_point_id', 'departure_time']);
        });
    }
};
