<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        // جلب جميع المستخدمين اللي نوعهم عميل (أو جلب الجميع إذا لم يكن لديك حقل نوع)
        $users = User::where('type', 'customer')->get();

        if ($users->isEmpty()) {
            $users = User::all(); // fallback في حال ما عندك حقل type
        }

        foreach ($users as $user) {
            // تنظيف البيانات القديمة عشان لو شغلنا السيدر أكثر من مرة ما تتضاعف الفلوس
            $user->walletTransactions()->delete();
            $balance = 0;

            // 1. حركة من 20 يوم (لاختبار فلتر Last 30 Days) - إيداع (سهم أخضر)
            $amount1 = 500.00;
            WalletTransaction::create([
                'user_id'      => $user->id,
                'type'         => 'credit',
                'amount'       => $amount1,
                'title'        => 'Wallet Top Up',
                'created_at'   => Carbon::now()->subDays(20),
                'updated_at'   => Carbon::now()->subDays(20),
            ]);
            $balance += $amount1;

            // 2. حركة من 5 أيام (لاختبار فلتر Last 7 Days) - سحب/دفع (سهم أحمر)
            $amount2 = 130.00;
            WalletTransaction::create([
                'user_id'      => $user->id,
                'type'         => 'debit',
                'amount'       => $amount2,
                'title'        => 'Payment - Home Cleaning',
                'reference_id' => 'HC-' . rand(100000, 999999),
                'created_at'   => Carbon::now()->subDays(5),
                'updated_at'   => Carbon::now()->subDays(5),
            ]);
            $balance -= $amount2;

            // 3. حركة من يومين (لاختبار فلتر Last 7 Days) - استرجاع (سهم أخضر)
            $amount3 = 130.00;
            WalletTransaction::create([
                'user_id'      => $user->id,
                'type'         => 'credit',
                'amount'       => $amount3,
                'title'        => 'Refund - Home Cleaning',
                'reference_id' => 'HC-' . rand(100000, 999999),
                'created_at'   => Carbon::now()->subDays(2),
                'updated_at'   => Carbon::now()->subDays(2),
            ]);
            $balance += $amount3;

            // 4. حركة اليوم (لاختبار فلتر Today) - سحب/دفع (سهم أحمر)
            $amount4 = 150.00;
            WalletTransaction::create([
                'user_id'      => $user->id,
                'type'         => 'debit',
                'amount'       => $amount4,
                'title'        => 'Payment - Deep Cleaning',
                'reference_id' => 'DC-' . rand(100000, 999999),
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ]);
            $balance -= $amount4;

            // 5. تحديث الرصيد النهائي للمستخدم في جدول users
            // الرصيد النهائي رح يكون: 500 - 130 + 130 - 150 = 350 EGP (مطابق لصورة الفيجما!)
            $user->update(['wallet_balance' => $balance]);
        }
    }
}