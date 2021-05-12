<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'type', // 1：邮箱
        'name',
        'avatar'
    ];

    public function getAvatarAttribute($avatar)
    {
        return patchImage($avatar);
    }
}
