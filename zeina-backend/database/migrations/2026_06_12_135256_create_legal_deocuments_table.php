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
        
        Schema::create('legal_documents', function (Blueprint $table) {
           $table->id();
            $table->enum('type', ['terms', 'privacy']); // تحديد نوع المستند
            $table->string('title_ar');
            $table->string('title_en');
            $table->text('content_ar');
            $table->text('content_en');
            $table->integer('sort_order')->default(0); // لترتيب ظهور البنود في التطبيق
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
