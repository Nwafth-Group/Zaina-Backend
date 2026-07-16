<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserReferralCode extends Model
{
    use HasFactory;

    protected $table = 'user_referral_codes';

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship with the User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Generate a unique expiring referral code for a user.
     */
    public static function generateForUser(User $user): self
    {
        do {
            $code = 'REF-' . strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return self::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addDays(2),
        ]);
    }
}
