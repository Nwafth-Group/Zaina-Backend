<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable()->after('gender');
            $table->string('vehicle_plate')->nullable()->after('vehicle_type');
            $table->unsignedInteger('vehicle_capacity')->default(5)->after('vehicle_plate');
        });

        Schema::table('transport_groups', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable()->after('driver_id');
            $table->string('vehicle_plate')->nullable()->after('vehicle_type');
            $table->unsignedInteger('vehicle_capacity')->nullable()->after('vehicle_plate');
        });
    }

    public function down(): void
    {
        Schema::table('transport_groups', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'vehicle_plate', 'vehicle_capacity']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'vehicle_plate', 'vehicle_capacity']);
        });
    }
};
