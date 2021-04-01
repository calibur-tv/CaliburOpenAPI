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

        return $character;
    }
}
