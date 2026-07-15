<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = [
        'user_id', 'type', 'amount', 'title_ar', 'title_en',
        'reference_type', 'reference_id', 'points_before',
        'points_after', 'reason', 'created_by', 'expiration_date',
    ];

    public static function getUserPointsBalance($userId): int
    {
        $credits = self::where('user_id', $userId)
            ->whereIn('type', ['earn', 'refund'])
            ->sum('amount');

        $adjustmentsCredit = self::where('user_id', $userId)
            ->where('type', 'adjustment')
            ->where('amount', '>', 0)
            ->sum('amount');

        $debits = self::where('user_id', $userId)
            ->whereIn('type', ['redeem', 'expire'])
            ->sum('amount');

        $adjustmentsDebit = self::where('user_id', $userId)
            ->where('type', 'adjustment')
            ->where('amount', '<', 0)
            ->sum('amount');

        $adjustmentsDebit = abs($adjustmentsDebit);

        return ($credits + $adjustmentsCredit) - ($debits + $adjustmentsDebit);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedAmountAttribute()
    {
        $isCredit = in_array($this->type, ['earn', 'refund']) || ($this->type === 'adjustment' && $this->amount > 0);

        return $isCredit ? '+'.abs($this->amount).' Pts' : '-'.abs($this->amount).' Pts';
    }
}
