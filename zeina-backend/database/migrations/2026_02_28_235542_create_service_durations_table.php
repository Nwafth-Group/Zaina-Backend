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
        Schema::create('service_durations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            //add time line for service 
            $table->json('allowed_durations')->nullable(); // when type hourly
            $table->integer('min_duration_hours')->nullable(); // when type hourly
            $table->json('allowed_contract_durations')->nullable(); //when type moqema 
            $table->integer('min_contract_months')->nullable();// when type moqema
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_durations');
    }
};
