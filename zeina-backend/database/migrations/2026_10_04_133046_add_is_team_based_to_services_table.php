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
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_team_based')->nullable()->default(false)->after('pricing_type');
        });

        \Illuminate\Support\Facades\DB::table('services')
            ->whereIn('id', [6, 7, 8, 9, 10, 11])
            ->orWhereIn('service_code', [
                'SOFA_CLEANING',
                'WATER_TANK_CLEANING',
                'STAIRS_SURFACES_CLEANING',
                'AC_CLEANING',
                'CARPET_CLEANING',
                'CAR_WASH',
            ])
            ->update(['is_team_based' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('is_team_based');
        });
    }
};
