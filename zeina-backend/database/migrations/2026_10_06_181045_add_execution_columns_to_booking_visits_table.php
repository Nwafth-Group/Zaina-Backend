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
        Schema::table('booking_visits', function (Blueprint $table) {
            $table->string('actual_start_time')->nullable()->after('end_time');
            $table->string('actual_end_time')->nullable()->after('actual_start_time');
            $table->foreignId('started_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->after('started_by')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_visits', function (Blueprint $table) {
            $table->dropForeign(['started_by']);
            $table->dropForeign(['completed_by']);
            $table->dropColumn(['actual_start_time', 'actual_end_time', 'started_by', 'completed_by']);
        });
    }
};
