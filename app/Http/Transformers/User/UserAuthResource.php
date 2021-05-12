<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:53
 */

namespace App\Http\Transformers\User;

use Illuminate\Http\Resources\Json\JsonResource;

class UserAuthResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'avatar' => $this->avatar,
            'nickname' => $this->nickname,
            'desk_max_space' => $this->desk_max_space,
            'desk_use_space' => $this->desk_use_space,
            'title' => [],
            'group' => $this->group,
            'providers' => [
                'bind_qq' => !!$this->qq_unique_id,
                'bind_wechat' => !!$this->wechat_unique_id,
                'bind_phone' => !!$this->phone,
                'bind_email' => !!$this->email
            ],
            'meta' => $this->meta
        ];
    }
}
