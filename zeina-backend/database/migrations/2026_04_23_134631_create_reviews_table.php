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
        Schema::create('reviews', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
        $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
        $table->morphs('reviewable'); 
        $table->tinyInteger('rating');
        $table->decimal('rating_percentage', 5, 2)->default(0.00);
        $table->text('comment')->nullable();
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('action_at')->nullable();
        $table->timestamps();
        $table->unique(['booking_id', 'reviewable_type', 'reviewable_id'], 'unique_booking_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
