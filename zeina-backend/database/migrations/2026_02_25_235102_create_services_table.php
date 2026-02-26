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
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('type')->default('fixed'); // مثلاً: fixed (سعر ثابت) أو hourly (بالساعة)
            
            // ضوابط الوقت (بالدقائق كما طلبت في الـ BRD)
            $table->integer('default_duration_minutes')->default(120);
            $table->integer('min_duration_minutes')->default(60);
            $table->integer('max_duration_minutes')->default(480);
            
            // ضوابط السعر
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('price_per_hour', 10, 2)->nullable();
            $table->string('currency')->default('EGP'); // العملة الافتراضية
            
            $table->boolean('is_active')->default(true);
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
