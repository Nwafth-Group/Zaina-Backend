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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // كود الكوبون مثل: ZAINAH20
            $table->string('type'); // 'fixed' (مبلغ ثابت) أو 'percentage' (نسبة مئوية)
            $table->decimal('value', 8, 2); // قيمة الخصم (مثلاً 50.00 جنيه أو 15%)
            $table->decimal('max_discount', 8, 2)->nullable(); // أقصى مبلغ خصم ممكن في حال كان النوع نسبة مئوية
            $table->decimal('min_booking_amount', 8, 2)->default(0); // الحد الأدنى لقيمة الحجز لتفعيل الكوبون
            $table->integer('usage_limit')->nullable(); // إجمالي عدد مرات استخدام الكوبون في النظام ككل (null يعني غير محدود)
            $table->integer('user_usage_limit')->default(1); // حد الاستخدام لكل مستخدم (الافتراضي: يستخدمه العميل مرة واحدة فقط)
            $table->integer('used_count')->default(0); // كم مرة استُخدم الكوبون فعلياً في النظام
            $table->dateTime('start_date'); // تاريخ بدء الفعالية
            $table->dateTime('end_date'); // تاريخ انتهاء الصلاحية
            $table->boolean('is_for_all_users')->default(true); // هل الكوبون متاح للجميع أم لمستخدمين محددين؟
            $table->boolean('is_for_all_services')->default(true); // هل الكوبون يشمل كل الخدمات أم خدمات محددة؟
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
