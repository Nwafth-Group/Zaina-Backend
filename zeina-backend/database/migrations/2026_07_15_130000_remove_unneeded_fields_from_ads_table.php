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
        Schema::table('ads', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['type', 'text_en', 'text_ar', 'link_type', 'link'] as $col) {
                if (Schema::hasColumn('ads', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->enum('type', ['general', 'coupon'])->default('general');
            $table->text('text_en')->nullable();
            $table->text('text_ar')->nullable();
            $table->enum('link_type', ['internal', 'external', 'none'])->default('none');
            $table->string('link')->nullable();
        });
    }
};
