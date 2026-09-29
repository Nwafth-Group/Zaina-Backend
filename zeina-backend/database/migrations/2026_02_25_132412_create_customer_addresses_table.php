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
            $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('formatted_address')->nullable();
            $table->string('city')->nullable();
            $table->string('governorate')->nullable();
            $table->string('country')->nullable();
            $table->string('label')->nullable();
            $table->string('street')->nullable();
            $table->string('building_name')->nullable();
            $table->string('building_no')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment_no')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone_number');
            $table->string('secondary_phone')->nullable();
            $table->enum('serviceable_status', ['serviceable', 'not_serviceable'])->default('serviceable');
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