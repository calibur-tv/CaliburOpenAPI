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

    public static function createBangumi($data)
    {
        $bangumi = self::create($data);
        $bangumi->update([
            'slug' => id2slug($bangumi->id)
        ]);

        Search::createSearch([
            'uuid' => $bangumi->id,
            'text' => $bangumi->alias,
            'type' => 2
        ]);

        return $bangumi;
    }

    public function getAvatarAttribute($avatar)
    {
        return patchImage($avatar);
    }
}
