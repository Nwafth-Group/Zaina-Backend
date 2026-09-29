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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('service_code')->nullable()->unique();
            $table->enum('type', ['hourly', 'other'])->default('hourly');
            $table->json('worker_gender_options')->nullable();
            $table->string('pricing_type', 50)->default('per_service');
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->json('features')->nullable();
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->enum('status', ['active', 'inactive'])->default('active');
            
            // New Config Fields
            $table->json('available_visit_types')->nullable();
            $table->text('terms_ar')->nullable();
            $table->text('terms_en')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('default_duration')->nullable();
            $table->boolean('has_duration_range')->default(false);
            $table->integer('min_duration')->nullable();
            $table->integer('max_duration')->nullable();
            $table->integer('min_workers')->default(1);
            $table->integer('max_workers')->default(1);
            $table->integer('min_children')->nullable();
            $table->integer('max_children')->nullable();
            $table->integer('covered_persons_per_worker')->nullable()->default(1);
            $table->decimal('base_price', 10, 2)->default(0.00);

            $table->boolean('points_redemption_enabled')->default(false);
            $table->string('points_redemption_method')->default('fixed');
            $table->integer('points_required_fixed')->default(0);
            $table->integer('points_conversion_rate')->default(10);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};