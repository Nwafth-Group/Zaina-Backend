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
        Schema::create('reward_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar');
            $table->string('title_en');
            $table->string('subtitle_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->integer('points'); 
            $table->string('action_text_ar');
            $table->string('action_text_en');
            $table->enum('action_type', ['url', 'invite_friend', 'rate_service']);
            $table->string('action_link')->nullable();
            $table->boolean('status')->default(true);
            $table->string('rule_category')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->integer('required_booking_count')->nullable();
            $table->string('frequency_type')->default('one_time');
            $table->json('eligible_services')->nullable();
            $table->string('calculation_method')->nullable();
            $table->integer('points_expiration_months')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_tasks');
    }
};
