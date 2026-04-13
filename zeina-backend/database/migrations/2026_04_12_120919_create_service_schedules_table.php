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
        Schema::create('service_schedules', function (Blueprint $table) {
        $table->id();
        $table->date('schedule_date')->nullable();
        $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
        $table->enum('day', ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
        $table->time('start_time'); // مثلاً 13:00:00 (1 PM)
        $table->time('end_time');   // مثلاً 16:00:00 (4 PM)
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_schedules');
    }
};
