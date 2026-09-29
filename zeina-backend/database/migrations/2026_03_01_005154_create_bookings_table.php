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
            $table->string('booking_ref')->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('address_id')->constrained('customer_addresses');
            $table->foreignId('worker_id')->nullable()->constrained('workers');
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('actual_start_time', 20)->nullable();
            $table->string('actual_end_time', 20)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_at_risk')->default(false);
            $table->text('at_risk_reason')->nullable();
            $table->integer('duration_minutes');
            $table->string('visit_type')->default('single_visit');
            $table->integer('visit_number')->default(1);
            $table->string('payment_transaction_id')->nullable();
            $table->string('currency_code', 3)->default('EGP');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('worker_gender', 50)->nullable();
            $table->integer('number_of_workers')->default(1);
            $table->integer('number_of_children')->nullable();
            $table->integer('number_of_persons')->nullable();
            $table->json('child_age_groups')->nullable();
            $table->json('selected_options')->nullable();
            $table->string('payment_method')->nullable();
            $table->integer('paid_points')->default(0);
            $table->decimal('refund_amount', 10, 2)->default(0.00);
            $table->integer('refund_points')->default(0);
            $table->string('refund_destination')->nullable();
            $table->string('refund_status')->nullable();
            $table->decimal('price_subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['Upcoming','inService','completed','cancelled'])->default('Upcoming');
            $table->string('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->text('customer_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers');
            $table->enum('status', ['active', 'replaced', 'cancelled'])->default('active');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('booking_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('note')->nullable();
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
