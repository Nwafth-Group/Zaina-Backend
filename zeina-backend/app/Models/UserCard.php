<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCard extends Model
{
    protected $fillable = ['user_id', 'card_token', 'last_four', 'card_brand', 'card_holder_name', 'is_default'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
