<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;
use OwenIt\Auditing\Contracts\Auditable;

class Role extends SpatieRole implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'name',
        'guard_name',
        'code',
        'description',
        'status',
    ];


}
