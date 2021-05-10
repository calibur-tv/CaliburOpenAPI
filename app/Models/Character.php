<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'alias',
        'intro',
        'avatar',
        'bgm_id'
    ];

    public static function createCharacter($data)
    {
        $character = self::create($data);
        $character->update([
            'slug' => id2slug($character->id)
        ]);

        Search::createSearch([
            'uuid' => $character->id,
            'text' => $character->alias,
            'type' => 3
        ]);

        return $character;
    }

    public function getAvatarAttribute($avatar)
    {
        return patchImage($avatar);
    }
}
