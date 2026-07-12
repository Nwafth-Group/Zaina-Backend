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
            $table->string('title_en')->nullable()->after('id');
            $table->string('title_ar')->nullable()->after('title_en');
            $table->enum('type', ['general', 'coupon'])->default('general')->after('title_ar');
            $table->text('text_en')->nullable()->after('type');
            $table->text('text_ar')->nullable()->after('text_en');
            $table->enum('link_type', ['internal', 'external', 'none'])->default('none')->after('text_ar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'title_ar', 'type', 'text_en', 'text_ar', 'link_type']);
        });
    }
};
