<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_group_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_group_id')->constrained('transport_groups')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('worker_id')->constrained('workers')->onDelete('cascade');
            $table->integer('dropoff_sequence')->default(0);
            $table->enum('status', ['assigned', 'picked_up', 'dropped_off'])->default('assigned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['transport_group_id', 'worker_id', 'booking_id'], 'tg_worker_booking_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_group_workers');
    }
};
