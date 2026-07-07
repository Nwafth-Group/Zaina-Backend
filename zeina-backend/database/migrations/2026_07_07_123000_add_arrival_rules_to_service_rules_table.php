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
            if (!Schema::hasColumn('service_rules', 'arrival_buffer_minutes')) {
                $table->integer('arrival_buffer_minutes')->default(0);
            }
            if (!Schema::hasColumn('service_rules', 'late_arrival_grace_minutes')) {
                $table->integer('late_arrival_grace_minutes')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_rules', function (Blueprint $table) {
            $table->dropColumn([
                'arrival_buffer_minutes',
                'late_arrival_grace_minutes'
            ]);
        });
    }
};
