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
        if (Schema::hasColumn('coupons', 'allowed_shifts')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('allowed_shifts');
            });
        }

        if (Schema::hasColumn('coupon_services', 'allowed_shifts')) {
            Schema::table('coupon_services', function (Blueprint $table) {
                $table->dropColumn('allowed_shifts');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->json('allowed_shifts')->nullable();
        });

        Schema::table('coupon_services', function (Blueprint $table) {
            $table->json('allowed_shifts')->nullable();
        });
    }
};
