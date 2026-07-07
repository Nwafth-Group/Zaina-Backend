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
            if (!Schema::hasColumn('bookings', 'visit_type')) {
                $table->string('visit_type')->default('single_visit');
            }
            if (!Schema::hasColumn('bookings', 'visit_number')) {
                $table->integer('visit_number')->default(1);
            }
            if (!Schema::hasColumn('bookings', 'payment_transaction_id')) {
                $table->string('payment_transaction_id')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'currency_code')) {
                $table->string('currency_code', 3)->default('EGP');
            }
            if (!Schema::hasColumn('bookings', 'contract_id')) {
                $table->unsignedBigInteger('contract_id')->nullable();
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
                'visit_type',
                'visit_number',
                'payment_transaction_id',
                'currency_code',
                'contract_id'
            ]);
        });
    }
};
