<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bangumi extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'alias',
        'intro',
        'avatar',
        'bgm_id',
        'bili_id',
        'update_week', // 0：不更新，1 ~ 7：星期一 ~ 星期日
        'published_at'
    ];
}
