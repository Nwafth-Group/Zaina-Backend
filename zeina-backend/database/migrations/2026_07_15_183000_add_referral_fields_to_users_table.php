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
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->unique()->after('status');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
        });

        // Generate unique referral codes for all existing users
        $users = DB::table('users')->whereNull('referral_code')->get();
        foreach ($users as $user) {
            $code = 'REF-' . strtoupper(bin2hex(random_bytes(3))); // 6 character random code
            while (DB::table('users')->where('referral_code', $code)->exists()) {
                $code = 'REF-' . strtoupper(bin2hex(random_bytes(3)));
            }
            DB::table('users')->where('id', $user->id)->update(['referral_code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropColumn(['referral_code', 'referred_by_id']);
        });
    }
};
