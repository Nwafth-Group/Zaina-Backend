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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();  // الموقع الجغرافي والمنطقة
            $table->decimal('latitude', 10, 8); // من الخريطة
            $table->decimal('longitude', 11, 8); // من الخريطة
            // تفاصيل العنوان (حسب الفيجما)
            $table->string('label')->nullable(); // Home, Office
            $table->string('street')->nullable(); // إجباري
            $table->string('building_name')->nullable(); // Al Noor Building
            $table->string('building_no')->nullable(); // 24
            $table->string('floor')->nullable(); // 5
            $table->string('apartment_no')->nullable(); // 12
            // بيانات التواصل (حسب الفيجما)
            $table->string('contact_name')->nullable(); // Saeed Ali Al-Omar
            $table->string('phone_number'); // +21 1012345678
            $table->string('secondary_phone')->nullable();
            // إعدادات إضافية
            $table->enum('serviceable_status', ['serviceable', 'not_serviceable'])->default('serviceable');
            $table->text('notes')->nullable(); //(مثل: الرن على الجرس مرتين)
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
