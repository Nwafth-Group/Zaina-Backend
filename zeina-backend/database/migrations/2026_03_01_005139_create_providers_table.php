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
            $table->string('fullname', 50);
            $table->enum('gender',['Male','Female']);
            $table->string('phone_number', 20)->unique();
            $table->string('phone_code', 20)->nullable();
            $table->string('code', 20)->nullable();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->string('internal_notes', 1000)->nullable();
            $table->decimal('rating_average', 3, 2)->default(0.00);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullable();
            $table->softDeletes();
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
