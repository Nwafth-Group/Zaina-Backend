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
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('coupons', 'is_for_all_cities')) {
                $table->boolean('is_for_all_cities')->default(true)->after('is_for_all_services');
            }
            if (!Schema::hasColumn('coupons', 'completed_bookings_count')) {
                $table->integer('completed_bookings_count')->default(0)->after('is_for_all_cities');
            }
            if (!Schema::hasColumn('coupons', 'allowed_visit_types')) {
                $table->json('allowed_visit_types')->nullable()->after('completed_bookings_count');
            }
            if (!Schema::hasColumn('coupons', 'allowed_shifts')) {
                $table->json('allowed_shifts')->nullable()->after('allowed_visit_types');
            }
        });

        // Set name to code for any existing coupons
        DB::table('coupons')->update(['name' => DB::raw('code')]);

        try {
            Schema::table('coupons', function (Blueprint $table) {
                $table->unique('name');
            });
        } catch (\Exception $e) {
            // Ignore if index already exists
        }

        Schema::table('coupon_services', function (Blueprint $table) {
            if (!Schema::hasColumn('coupon_services', 'allowed_visit_types')) {
                $table->json('allowed_visit_types')->nullable()->after('service_id');
            }
            if (!Schema::hasColumn('coupon_services', 'allowed_shifts')) {
                $table->json('allowed_shifts')->nullable()->after('allowed_visit_types');
            }
        });




    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_service_zones');
        Schema::dropIfExists('coupon_cities');

        Schema::table('coupon_services', function (Blueprint $table) {
            $table->dropColumn(['allowed_visit_types', 'allowed_shifts']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            try {
                $table->dropUnique(['coupons_name_unique']);
            } catch (\Exception $e) {}
            $table->dropColumn([
                'name',
                'is_for_all_cities',
                'completed_bookings_count',
                'allowed_visit_types',
                'allowed_shifts',
            ]);
        });
    }
};
