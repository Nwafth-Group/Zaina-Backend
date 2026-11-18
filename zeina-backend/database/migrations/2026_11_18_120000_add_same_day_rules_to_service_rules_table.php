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
        Schema::table('service_rules', function (Blueprint $table) {
            $table->unsignedInteger('same_day_lead_time_hours')->default(3)->after('allow_same_day');
            $table->time('same_day_cutoff_time')->nullable()->default('10:00:00')->after('same_day_lead_time_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_rules', function (Blueprint $table) {
            $table->dropColumn(['same_day_lead_time_hours', 'same_day_cutoff_time']);
        });
    }
};
