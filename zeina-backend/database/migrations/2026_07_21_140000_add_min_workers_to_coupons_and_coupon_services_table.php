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
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'min_workers')) {
                $table->integer('min_workers')->nullable()->after('completed_bookings_count');
            }
        });

        Schema::table('coupon_services', function (Blueprint $table) {
            if (!Schema::hasColumn('coupon_services', 'min_workers')) {
                $table->integer('min_workers')->nullable()->after('allowed_visit_types');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'min_workers')) {
                $table->dropColumn('min_workers');
            }
        });

        Schema::table('coupon_services', function (Blueprint $table) {
            if (Schema::hasColumn('coupon_services', 'min_workers')) {
                $table->dropColumn('min_workers');
            }
        });
    }
};
