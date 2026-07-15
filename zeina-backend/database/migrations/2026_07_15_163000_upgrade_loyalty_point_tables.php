<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update reward_tasks table
        Schema::table('reward_tasks', function (Blueprint $table) {
            $table->string('rule_category')->nullable()->after('status');
            $table->string('trigger_event')->nullable()->after('rule_category');
            $table->integer('required_booking_count')->nullable()->after('trigger_event');
            $table->string('frequency_type')->default('one_time')->after('required_booking_count');
            $table->json('eligible_services')->nullable()->after('frequency_type');
            $table->string('calculation_method')->nullable()->after('eligible_services');
            $table->integer('points_expiration_months')->nullable()->after('calculation_method');
        });

        // 2. Update point_settings table
        Schema::table('point_settings', function (Blueprint $table) {
            $table->string('value')->nullable()->after('reward_points');
        });

        // 3. Update services table
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('points_redemption_enabled')->default(false)->after('status');
            $table->string('points_redemption_method')->default('fixed')->after('points_redemption_enabled');
            $table->integer('points_required_fixed')->default(0)->after('points_redemption_method');
            $table->integer('points_conversion_rate')->default(10)->after('points_required_fixed');
        });

        // 4. Update/Recreate point_transactions table
        if (DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('point_transactions');
            Schema::create('point_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type');
                $table->integer('amount');
                $table->string('title_ar');
                $table->string('title_en');
                $table->nullableMorphs('reference');
                $table->integer('points_before')->default(0);
                $table->integer('points_after')->default(0);
                $table->string('reason')->nullable();
                $table->string('created_by')->nullable();
                $table->date('expiration_date')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('point_transactions', function (Blueprint $table) {
                $table->string('type')->change();
                $table->integer('points_before')->default(0)->after('amount');
                $table->integer('points_after')->default(0)->after('points_before');
                $table->string('reason')->nullable()->after('points_after');
                $table->string('created_by')->nullable()->after('reason');
                $table->date('expiration_date')->nullable()->after('created_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'rule_category', 'trigger_event', 'required_booking_count',
                'frequency_type', 'eligible_services', 'calculation_method', 'points_expiration_months',
            ]);
        });

        Schema::table('point_settings', function (Blueprint $table) {
            $table->dropColumn('value');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'points_redemption_enabled', 'points_redemption_method',
                'points_required_fixed', 'points_conversion_rate',
            ]);
        });

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropColumn(['points_before', 'points_after', 'reason', 'created_by', 'expiration_date']);
            if (DB::getDriverName() !== 'sqlite') {
                $table->enum('type', ['earn', 'redeem'])->change();
            }
        });
    }
};
