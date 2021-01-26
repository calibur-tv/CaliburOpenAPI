<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Auth\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug',
        'phone',
        'nickname',
        'avatar',
        'banner',
        'signature',
        'birthday',
        'birth_secret',
        'sex',
        'sex_secret',
        'password',
        'api_token',
        'qq_open_id',
        'qq_unique_id',
        'wechat_unique_id',
        'wechat_open_id',
        'title',                        // 头衔
        'level',                        // 等级
        'virtual_coin',                 // 团子数量
        'money_coin',                   // 光玉数量
        'banned_to',                    // 封禁结束时间
        'continuous_sign_count',        // 连续签到次数
        'total_sign_count',             // 总签到次数
        'latest_signed_at',             // 最后签到时间
        'activity_stat',                // 活跃度统计
        'exposure_stat',                // 曝光度统计
        'migration_state',
        'followers_count',              // 粉丝数量
        'following_count',              // 关注数量
        'visit_count',                  // 访问量
        'is_admin',                     // 是否是管理员
        'buy_idol_count',               // 购买股票的团子数
        'get_idol_count',               // 从股市获得的收益数
        'unread_agree_count',           // 未读点赞个数
        'unread_reward_count',          // 未读投食个数
        'unread_mark_count',            // 未读收藏个数
        'unread_comment_count',         // 未读评论个数
        'unread_share_count',           // 未读分享个数
        'unread_message_count',         // 未读私信个数
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'api_token'
    ];

    /**
     * 确认密码是否正确
     *
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    public function createApiToken()
    {
        $token = Crypt::encrypt($this->slug . time());
        $token = id2slug($this->id) . ':' . str_replace(':', '-', $token);

        $this->update([
            'api_token' => $token
        ]);

        return $token;
    }

    public static function createUser($data)
    {
        $user = self::create($data);
        $slug = 'cc-' . id2slug($user->id);

        $user->update([
            'slug' => $slug,
            'nickname' => isset($data['nickname']) ? $data['nickname'] : $slug
        ]);

        $user->slug = $slug;
        $user->api_token = $user->createApiToken();
        $user->invitor_slug = $data['invitor_slug'] ?? '';

        return $user;
    }
}
