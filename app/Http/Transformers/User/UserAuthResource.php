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
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'banner' => $this->banner,
            'birthday' => $this->birthday,
            'birth_secret' => $this->birth_secret,
            'sex' => $this->sex,
            'sex_secret' => $this->sex_secret,
            'signature' => $this->signature,
            'title' => $this->title,
            'level' => $this->level,
            'providers' => [
                'bind_qq' => !!$this->qq_unique_id,
                'bind_wechat' => !!$this->wechat_unique_id,
                'bind_weapp' => !!$this->wechat_unique_id,
                'bind_phone' => !!$this->phone,
                'bind_bilibili' => !!$this->bilibili_id
            ],
            // 钱包
            'wallet_coin' => (float)$this->virtual_coin,
            'wallet_money' => (float)$this->money_coin,
            'is_admin' => $this->is_admin
        ];
    }
}
