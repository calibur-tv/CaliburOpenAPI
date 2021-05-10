<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'avatar'
    ];

    public function getAvatarAttribute($avatar)
    {
        return patchImage($avatar);
    }
}
