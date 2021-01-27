<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:53
 */

namespace App\Http\Transformers\User;

use App\Http\Modules\DailyRecord\UserDailySign;
use Illuminate\Http\Resources\Json\JsonResource;

class UserItemResource extends JsonResource
{
    public function toArray($request)
    {
        $userDailySign = new UserDailySign();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'banner' => $this->banner,
            'signature' => $this->signature,
            'title' => $this->title,
            'level' => $this->level,
            'sex' => $this->sex_secret ? -1 : $this->sex,
            'birthday' => $this->birth_secret ? -1 : $this->birthday,
            // 签到
            'daily_signed' => $userDailySign->check($this->slug),
            'continuous_sign_count' => $this->continuous_sign_count,
            'total_sign_count' => $this->total_sign_count,
            'latest_signed_at' => $this->latest_signed_at,
            // 偶像
            'buy_idol_count' => $this->buy_idol_count,
            'get_idol_count' => $this->get_idol_count,
            // 钱包
            'wallet_coin' => (float)$this->virtual_coin,
            'wallet_money' => (float)$this->money_coin,
            // 统计
            'stat_activity' => (float)$this->activity_stat,
            'stat_exposure' => (float)$this->exposure_stat,
        ];
    }
}
