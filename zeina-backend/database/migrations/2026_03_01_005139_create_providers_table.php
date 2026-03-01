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
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['individual', 'company'])->default('individual');
            $table->string('first_name', 50);
            $table->string('last_name', 50)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('phone_number', 20)->unique();
            // $table->string('document_id')->nullable();            
            $table->enum('status', ['pending', 'active', 'suspended', 'blacklisted'])->default('pending');
            $table->decimal('rating_average', 3, 2)->default(0.00);
            $table->integer('total_jobs_completed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
