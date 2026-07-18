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
            if (Schema::hasColumn('reward_tasks', 'trigger_event')) {
                $table->dropColumn('trigger_event');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_tasks', function (Blueprint $table) {
            $table->string('trigger_event')->nullable()->after('rule_category');
        });
    }
};
