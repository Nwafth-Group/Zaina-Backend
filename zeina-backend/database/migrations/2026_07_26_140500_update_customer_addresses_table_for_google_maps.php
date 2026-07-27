<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->foreign('service_zone_id')->references('id')->on('service_zones')->nullOnDelete();
            $table->string('formatted_address')->nullable()->after('longitude');
            $table->string('city')->nullable()->after('formatted_address');
            $table->string('governorate')->nullable()->after('city');
            $table->string('country')->nullable()->after('governorate');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropForeign(['service_zone_id']);
            $table->dropColumn(['service_zone_id', 'formatted_address', 'city', 'governorate', 'country']);
        });
    }
};
