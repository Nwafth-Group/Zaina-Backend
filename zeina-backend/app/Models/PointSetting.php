<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use OwenIt\Auditing\Contracts\Auditable;

class PointSetting extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\PointSettingFactory> */
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = ['key', 'action_type', 'target_steps', 'reward_points', 'value'];
}
