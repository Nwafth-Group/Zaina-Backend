<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->unique();
            $table->string('id_number')->nullable()->unique();
            $table->text('address')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
        });

        // Update status enum values in production (MySQL)
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE workers MODIFY COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'id_number', 'address', 'gender']);
        });
    }
};
