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
            $table->string('title_ar');       // مثلاً: تابعنا على فيسبوك
            $table->string('title_en');       // Follow us on Facebook
            $table->string('subtitle_ar')->nullable(); // ابق على اطلاع بآخر العروض
            $table->string('subtitle_en')->nullable(); // Stay updated with our latest offers
            $table->integer('points'); 
            // زر الإجراء (Call to Action)
            $table->string('action_text_ar'); // تابعنا
            $table->string('action_text_en'); // Follow
            $table->enum('action_type', ['url', 'invite_friend', 'rate_service']);// نوع الإجراء عشان الموبايل يعرف كيف يتعامل معه
            $table->string('action_link')->nullable(); // رابط الفيسبوك لو كان النوع url
            $table->boolean('status')->default(true); // لتفعيل أو تعطيل المهمة
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
