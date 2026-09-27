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
            $table->string('actual_start_time', 20)->nullable()->after('end_time');
            $table->string('actual_end_time', 20)->nullable()->after('actual_start_time');
            $table->foreignId('started_by')->nullable()->after('actual_end_time')->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->after('started_by')->constrained('users')->nullOnDelete();
            $table->boolean('is_at_risk')->default(false)->after('completed_by');
            $table->text('at_risk_reason')->nullable()->after('is_at_risk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['started_by']);
            $table->dropForeign(['completed_by']);
            $table->dropColumn([
                'actual_start_time',
                'actual_end_time',
                'started_by',
                'completed_by',
                'is_at_risk',
                'at_risk_reason',
            ]);
        });
    }
};
