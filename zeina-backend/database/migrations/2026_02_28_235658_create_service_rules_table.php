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
            // أوقات السماح
            $table->integer('free_cancel_cutoff_hours')->default(0); // number of hour that customer can cancel the service
            $table->integer('reschedule_cutoff_hours')->default(0); // same cancel order ,the last time customer can edit in order
            $table->integer('lead_time_min_hours')->default(0); // min  time lead to prepare the service
            $table->integer('lead_time_max_days')->default(1); // max
            // سياسات الإلغاء المتأخر
            $table->enum('late_cancel_action', ['fee', 'block', 'manual'])->default('block');
            $table->enum('late_cancel_fee_type', ['percent', 'fixed'])->nullable();
            $table->decimal('late_cancel_fee_value', 10, 2)->nullable();
            // سياسات عدم الحضور والتعديلات
            $table->enum('no_show_policy', ['compensate', 'consumed'])->default('consumed');
            $table->integer('max_changes_per_booking')->default(0);
            
            // New UI Config Rules
            $table->boolean('allow_cancel')->default(true);
            $table->boolean('allow_reschedule')->default(true);
            $table->boolean('allow_same_day')->default(false);
            $table->integer('max_advance_booking_days')->default(0);
            
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
