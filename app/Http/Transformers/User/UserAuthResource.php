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
                'bind_email' => !!$this->email,
                'bind_idcard' => !!$this->idcard
            ],
            'love_type' => $this->love_type,
            'love_user' => $this->love_user,
            'idcard' => $this->formatIdCard($this->idcard),
            'realname' => $this->formatTrueName($this->realname),
            'meta' => $this->meta,
            'email' => $this->email,
            'aboutus' => $this->aboutus
        ];
    }

    protected function formatTrueName($true_name)
    {
        if (!$true_name)
        {
            return '';
        }

        return "*" . mb_substr($true_name, 1);
    }

    protected function formatIdCard($id_card)
    {
        if (!$id_card)
        {
            return '';
        }

        //每隔1位分割为数组
        $split = str_split($id_card);
        //头2位和尾保留，其他部分替换为星号
        $split = array_fill(2,count($split) - 3,"*") + $split;
        ksort($split);
        //合并
        return implode('', $split);
    }
}
