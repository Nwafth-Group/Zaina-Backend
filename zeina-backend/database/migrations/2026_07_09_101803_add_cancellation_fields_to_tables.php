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
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('bookings', 'paid_points')) {
                $table->integer('paid_points')->default(0)->after('payment_method');
            }
            if (!Schema::hasColumn('bookings', 'refund_amount')) {
                $table->decimal('refund_amount', 10, 2)->default(0.00)->after('total_amount');
            }
            if (!Schema::hasColumn('bookings', 'refund_points')) {
                $table->integer('refund_points')->default(0)->after('refund_amount');
            }
            if (!Schema::hasColumn('bookings', 'refund_destination')) {
                $table->string('refund_destination')->nullable()->after('refund_points');
            }
            if (!Schema::hasColumn('bookings', 'refund_status')) {
                $table->string('refund_status')->nullable()->after('refund_destination');
            }
            if (!Schema::hasColumn('bookings', 'cancelled_by')) {
                $table->string('cancelled_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('bookings', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('bookings', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });

        Schema::table('booking_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_visits', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('booking_visits', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'paid_points',
                'refund_amount',
                'refund_points',
                'refund_destination',
                'refund_status',
                'cancelled_by',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });

        Schema::table('booking_visits', function (Blueprint $table) {
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
