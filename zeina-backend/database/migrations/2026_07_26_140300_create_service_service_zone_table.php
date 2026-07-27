<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_service_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('service_zone_id')->constrained('service_zones')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['service_id', 'service_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_service_zones');
    }
};
