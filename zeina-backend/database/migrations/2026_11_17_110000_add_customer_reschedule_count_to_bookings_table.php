<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('customer_reschedule_count')->default(0)->after('status');
            $table->timestamp('rescheduled_at')->nullable()->after('customer_reschedule_count');
        });

        // Backfill customer_reschedule_count from existing booking_histories if any
        try {
            DB::statement("UPDATE bookings SET customer_reschedule_count = (
                SELECT COUNT(*) FROM booking_histories 
                WHERE booking_histories.booking_id = bookings.id 
                AND booking_histories.note LIKE 'Rescheduled by customer%'
            )");
        } catch (\Throwable $e) {
            // Ignore if table/records do not exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['customer_reschedule_count', 'rescheduled_at']);
        });
    }
};
