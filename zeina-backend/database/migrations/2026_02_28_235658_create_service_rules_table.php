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
        Schema::create('service_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->integer('free_cancel_cutoff_hours')->default(0); 
            $table->integer('reschedule_cutoff_hours')->default(0); 
            $table->boolean('allow_cancel')->default(true);
            $table->boolean('allow_reschedule')->default(true);
            $table->boolean('allow_same_day')->default(false);
            $table->integer('max_advance_booking_days')->default(0);
            $table->integer('arrival_buffer_minutes')->default(0);
            $table->integer('late_arrival_grace_minutes')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_rules');
    }
};
