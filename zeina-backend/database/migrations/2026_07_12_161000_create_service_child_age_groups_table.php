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
        Schema::create('service_child_age_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('name_ar');
            $table->string('name_en');
            $table->integer('age_from');
            $table->integer('age_to');
            $table->string('calculation_type')->nullable();
            $table->integer('children_count')->default(1);
            $table->integer('required_worker_count')->default(1);
            $table->string('note')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_child_age_groups');
    }
};
