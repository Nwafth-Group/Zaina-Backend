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
            if (!Schema::hasColumn('bookings', 'worker_gender')) {
                $table->string('worker_gender', 50)->nullable()->after('worker_id');
            }
            if (!Schema::hasColumn('bookings', 'number_of_workers')) {
                $table->integer('number_of_workers')->default(1)->after('worker_gender');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['worker_gender', 'number_of_workers']);
        });
    }
};
