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
        Schema::table('company_infos', function (Blueprint $table) {
            $table->string('google_play_link')->nullable()->after('linkedin_link');
            $table->string('app_store_link')->nullable()->after('google_play_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_infos', function (Blueprint $table) {
            $table->dropColumn(['google_play_link', 'app_store_link']);
        });
    }
};
