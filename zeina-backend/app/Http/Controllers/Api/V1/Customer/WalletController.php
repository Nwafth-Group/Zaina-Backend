<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\DepositWalletRequest;
use App\Http\Resources\Api\V1\Wallet\WalletTransactionResource;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponseTrait;
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = $user->walletTransactions();

        // 🌟 نظام الفلترة حسب شاشة الفيجما 🌟
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'last_7_days':
                    $query->where('created_at', '>=', Carbon::now()->subDays(7));
                    break;
                case 'last_30_days':
                    $query->where('created_at', '>=', Carbon::now()->subDays(30));
                    break;
            }
        }

        // فلترة تاريخ مخصص (Custom Range)
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $transactions = $query->paginate(15);

        return $this->successResponse([
             'available_balance' => (float) $user->wallet_balance,
            'currency' => 'EGP',
            'transactions' => WalletTransactionResource::collection($transactions)
        ],__('messages.wallet_tranasction_fetch'));


    }

    // دالة تجريبية لشحن المحفظة (Top-up) 
    // ملاحظة: في الواقع هنا يتم الربط مع بوابة الدفع مثل Paymob أو Stripe
    public function topUp(DepositWalletRequest $request)
    {
        $user = auth()->user();
        
        $user->deposit($request->amount, 'Wallet Top Up');

        try {
            $user->notify(new \App\Notifications\WalletNotification(
                app()->getLocale() === 'en' ? 'Wallet Credited' : 'رصيد جديد بمحفظتك 💰',
                app()->getLocale() === 'en' 
                    ? "An amount of EGP {$request->amount} has been added to your wallet." 
                    : "تم إيداع مبلغ {$request->amount} ج.م في محفظتك بنجاح.",
                (float) $request->amount,
                'credit'
            ));
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::error('Wallet topup notification error: ' . $ex->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'تم شحن المحفظة بنجاح!',
            'new_balance' => (float) $user->wallet_balance
        ]);
    }
}
