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
        Schema::create('customer_addresses', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('area_id')->constrained('areas')->restrictOnDelete(); 
        $table->string('label')->nullable(); 
        $table->string('street')->nullable();
        $table->string('building')->nullable();
        $table->string('floor')->nullable();
        $table->decimal('latitude', 10, 8); // خط العرض
        $table->decimal('longitude', 11, 8); // خط الطول
        $table->enum('serviceable_status', ['serviceable', 'not_serviceable'])->default('serviceable');
        $table->string('unit')->nullable();
        $table->text('notes')->nullable();
        $table->boolean('is_default')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
