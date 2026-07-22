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
        Schema::table('reward_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('reward_tasks', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('rule_category')->constrained('services')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('reward_tasks', 'service_id')) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            }
        });
    }
};
