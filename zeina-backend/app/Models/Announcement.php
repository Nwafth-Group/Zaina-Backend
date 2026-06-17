<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
    ];


    public function media(){
        return $this->morphMany(Media::class,'model');
    }

    public function readers(){
        return $this->belongsToMany(User::class,'announcement_user','announcement_id','user_id')
        ->withPivot('read_at')
        ->withTimestamps();
    }
}
