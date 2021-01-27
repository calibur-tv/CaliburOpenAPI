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
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->title,
            'alias' => explode('|', $this->alias),
            'rank' => $this->rank,
            'score' => $this->score,
            'intro' => mb_substr(trim($this->intro), 0, 30, 'utf-8'),
            'source_id' => $this->source_id,
            'is_parent' => $this->is_parent,
            'parent_slug' => $this->parent_slug,
            'tags' => BangumiTagResource::collection($this->tags),
            'type' => $this->type,
            'is_liked' => false,
            'like_user_count' => $this->like_user_count,
            'subscribe_user_count' => $this->subscribe_user_count,
            'avatar' => patchImage($this->avatar, 'default-poster')
        ];
    }
}
