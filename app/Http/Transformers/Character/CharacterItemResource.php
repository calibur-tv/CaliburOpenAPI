<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:53
 */

namespace App\Http\Transformers\Character;

use Illuminate\Http\Resources\Json\JsonResource;

class CharacterItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'alias' => explode('|', $this->alias),
            'intro' => mb_substr($this->intro, 0, 30, 'utf-8'),
            'avatar' => $this->avatar
        ];
    }
}
