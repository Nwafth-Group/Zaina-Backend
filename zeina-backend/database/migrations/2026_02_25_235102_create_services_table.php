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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->enum('type', ['hourly', 'moqema', 'both'])->default('hourly');
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            // $table->string('icon')->nullable(); store icon for service.
            $table->decimal('avg_rating')->default(0.0);
            $table->enum('status', ['draft', 'active', 'hidden', 'inactive'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
