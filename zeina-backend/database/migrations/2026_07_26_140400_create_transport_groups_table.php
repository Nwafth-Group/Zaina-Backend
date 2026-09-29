<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_groups', function (Blueprint $table) {
            $table->id();
            $table->date('service_date');
            $table->string('governorate')->nullable();
            $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            $table->string('area_name')->nullable();
            $table->string('start_time');
            $table->foreignId('departure_point_id')->nullable()->constrained('departure_points')->nullOnDelete();
            $table->string('departure_time')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->unsignedInteger('vehicle_capacity')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['not_prepared', 'partially_prepared', 'ready_for_transport', 'departed', 'completed'])->default('not_prepared');
            $table->timestamps();
        });

        Schema::create('transport_group_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_group_id')->constrained('transport_groups')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->integer('dropoff_sequence')->default(0);
            $table->timestamps();

            $table->unique(['transport_group_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_group_bookings');
        Schema::dropIfExists('transport_groups');
    }
};
