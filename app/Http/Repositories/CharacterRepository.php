<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:31
 */

namespace App\Http\Repositories;


use App\Http\Transformers\Character\CharacterItemResource;
use App\Models\Character;

class CharacterRepository extends Repository
{
    public function item($slug, $refresh = false)
    {
        if (!$slug)
        {
            return null;
        }

        $id = slug2id($slug);

        $result = $this->RedisItem("character:{$id}", function () use ($id)
        {
            $character = Character
                ::where('id', $id)
                ->first();

            if (is_null($character))
            {
                return 'nil';
            }

            return new CharacterItemResource($character);
        }, $refresh);

        if ($result === 'nil')
        {
            return null;
        }

        return $result;
    }
}
