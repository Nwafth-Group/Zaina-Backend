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
        Schema::table('workers', function (Blueprint $table) {
            $table->json('work_days')->nullable()->after('gender');
        });

        // Initialize existing workers to work all 7 days by default
        $allDays = json_encode(['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
        DB::table('workers')->whereNull('work_days')->update(['work_days' => $allDays]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('work_days');
        });
    }
};
