<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = ['user_id', 'type', 'amount', 'title_ar', 'title_en', 'reference_type', 'reference_id'];


    public function getFormattedAmountAttribute()
    {
        return $this->type === 'earn' ? '+' . $this->amount . ' Pts' : '-' . $this->amount . ' Pts';
    }
}
