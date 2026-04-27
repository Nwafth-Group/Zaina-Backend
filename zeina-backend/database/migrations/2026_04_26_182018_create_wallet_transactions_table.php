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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);// credit = إيداع (سهم أخضر) | debit = سحب أو خصم (سهم أحمر)
            $table->decimal('amount', 10, 2);
            $table->string('title'); 
            $table->string('reference_id')->nullable();//رقم مرجعي لربط الحركة بالحجز أو بعملية الدفع (اختياري بس مهم للبزنس)
 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
