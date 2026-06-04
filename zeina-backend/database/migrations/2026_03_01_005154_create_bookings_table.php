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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_ref')->unique(); // مثال: BKG-2026-001
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('address_id')->constrained('customer_addresses');
            $table->foreignId('provider_id')->nullable()->constrained('providers');// Nullable: لأن الحجز في البداية يكون بدون مزود، ثم يقوم النظام أو الموظف بتعيينه
            $table->foreignId('worker_id')->nullable()->constrained('workers');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons'); // ربط الحجز بالكوبون المستخدم (إن وجد)
            $table->date('booking_date'); // تاريخ الحجز
            $table->time('start_time');   // وقت بداية الحجز
            $table->time('end_time');     // وقت نهاية الحجز
            $table->integer('duration_minutes'); // نخليها كمعلومة مفيدة
            // $table->integer('number_of_workers')->default(1);
            // الأمور المالية (نسخة من السعر وقت الحجز لحمايته من تغيرات الأسعار مستقبلاً)
            $table->decimal('price_subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['Upcoming','inService','completed','cancelled'])->default('Upcoming');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->text('customer_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers');
            $table->foreignId('worker_id')->nullable()->constrained('workers');
            $table->foreignId('assigned_by')->nullable()->constrained('users'); 
            $table->enum('status', ['active', 'replaced', 'cancelled'])->default('active');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('booking_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('note')->nullable(); // سبب التغيير أو الإلغاء
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_histories');
        Schema::dropIfExists('booking_assignments');
        Schema::dropIfExists('bookings');
    }
};
