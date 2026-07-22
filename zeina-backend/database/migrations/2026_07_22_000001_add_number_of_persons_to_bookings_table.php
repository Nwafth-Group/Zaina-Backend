<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'number_of_persons')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->integer('number_of_persons')->nullable()->after('number_of_children');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'number_of_persons')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('number_of_persons');
            });
        }
    }
};
