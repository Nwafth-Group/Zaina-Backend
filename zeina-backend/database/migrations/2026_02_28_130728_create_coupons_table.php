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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->unique();
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 8, 2);
            $table->integer('usage_limit')->nullable();
            $table->integer('user_usage_limit')->default(1);
            $table->integer('used_count')->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_for_all_users')->default(true);
            $table->boolean('is_for_all_services')->default(true);
            $table->boolean('is_for_all_cities')->default(true);
            $table->integer('completed_bookings_count')->default(0);
            $table->integer('min_workers')->nullable();
            $table->json('allowed_visit_types')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};