<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:53
 */

namespace App\Http\Transformers\User;

use Illuminate\Http\Resources\Json\JsonResource;

class UserItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'slug' => $this->slug,
            'avatar' => $this->avatar,
            'nickname' => $this->nickname,
            'providers' => [
                'bind_qq' => !!$this->qq_unique_id,
                'bind_wechat' => !!$this->wechat_unique_id,
                'bind_phone' => !!$this->phone,
                'bind_email' => !!$this->email,
                'bind_idcard' => !!$this->idcard
            ],
            'meta' => $this->meta,
            'aboutus' => $this->aboutus
        ];
    }
}
