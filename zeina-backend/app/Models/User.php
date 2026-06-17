<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory , HasRoles , Notifiable;

    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'country_id',
        'first_name',
        'id_number',
        'last_name',
        'wallet_balance',
        'full_name',
        'verification_status',
        'rejection_reason',
        'gender',
        'phone_number',
        'email',
        'password',
        'type',
        'status',
        'email_verified_at',
        'preferred_language',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $guard_name = 'sanctum';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class, 'user_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    // علاقة المستخدم بحركات المحفظة
    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class)->latest(); // latest عشان يرجع الأجدد أولاً
    }

    // 🌟 دوال مساعدة للعمليات المالية 🌟

    // دالة الإيداع (إضافة رصيد)
    public function deposit($amount, $title, $referenceId = null)
    {
        $this->increment('wallet_balance', $amount);

        return $this->walletTransactions()->create([
            'type' => 'credit',
            'amount' => $amount,
            'title' => $title,
            'reference_id' => $referenceId,
        ]);
    }

    // دالة السحب أو الخصم
    public function withdraw($amount, $title, $referenceId = null)
    {
        $this->decrement('wallet_balance', $amount);

        return $this->walletTransactions()->create([
            'type' => 'debit',
            'amount' => $amount,
            'title' => $title,
            'reference_id' => $referenceId,
        ]);
    }

    public function cards()
    {
        return $this->hasMany(UserCard::class);
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function getTotalPointsAttribute()
    {
        $earned = $this->pointTransactions()->where('type', 'earn')->sum('amount');
        $redeemed = $this->pointTransactions()->where('type', 'redeem')->sum('amount');

        return $earned - $redeemed;
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon_users')->withPivot('used_count')->withTimestamps();
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function readAnnouncements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_user', 'user_id', 'announcement_id')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    //    public function readAnnouncements()
    // {
    //     return $this->belongsToMany(Announcement::class, 'announcement_user', 'announcement_id', 'user_id')
    //                 ->withPivot('read_at')
    //                 ->withTimestamps();
    // }

}
