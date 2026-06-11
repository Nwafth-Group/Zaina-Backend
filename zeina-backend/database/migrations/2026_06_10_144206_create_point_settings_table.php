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
        Schema::create('point_settings', function (Blueprint $table) {
           $table->id();
           $table->string('key')->unique(); // مثلاً: 'service_cycle_rules'
           $table->string('action_type')->default('complete_bookings'); // نوع التصرف المربح
           $table->integer('target_steps')->default(3); // كم خدمة مطلوب ينجزها؟
           $table->integer('reward_points')->default(100); // كم نقطة بيكسب بس يخلص؟
           $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_settings');
    }
};
