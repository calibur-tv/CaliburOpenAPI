<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:53
 */

namespace App\Http\Transformers\Bangumi;

use Illuminate\Http\Resources\Json\JsonResource;

class BangumiItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'slug' => $this->slug,
            'name' => $this->title,
            'alias' => explode('|', $this->alias),
            'intro' => mb_substr($this->intro, 0, 30, 'utf-8'),
            'avatar' => $this->avatar
        ];
    }
}
