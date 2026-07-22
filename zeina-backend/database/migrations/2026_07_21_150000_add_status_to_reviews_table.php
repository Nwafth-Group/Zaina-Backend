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
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('comment');
            }
            if (!Schema::hasColumn('reviews', 'action_by')) {
                $table->foreignId('action_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('reviews', 'action_at')) {
                $table->timestamp('action_at')->nullable()->after('action_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'action_by')) {
                $table->dropForeign(['action_by']);
                $table->dropColumn('action_by');
            }
            if (Schema::hasColumn('reviews', 'action_at')) {
                $table->dropColumn('action_at');
            }
            if (Schema::hasColumn('reviews', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
