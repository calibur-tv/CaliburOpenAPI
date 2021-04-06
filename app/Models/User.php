<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Laravel\Lumen\Auth\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory, SoftDeletes;

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
        'invitor_id',
        'password',
        'api_token',
        'wechat_open_id',
        'wechat_unique_id',
        'qq_open_id',
        'qq_unique_id',
        'desk_max_space',
        'desk_use_space',
        'migration_state'
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
        return Crypt::decrypt($this->password) === $password;
    }

    public function setPasswordAttribute($str)
    {
        $this->attributes['password'] = Crypt::encrypt($str);
    }

    public function getAvatarAttribute($avatar)
    {
        return patchImage($avatar);
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
        $user->invitor_id = $data['invitor_id'] ?? '';

        Search::createSearch([
            'uuid' => $user->id,
            'text' => $user->nickname,
            'type' => 1
        ]);

        return $user;
    }

    public static function spaceIsExceed($user, $size)
    {
        return $user->desk_max_space - $user->desk_use_space < ceil($size);
    }

    public static function spaceUsageAdd($user, $size)
    {
        $user->increment('desk_use_space', ceil($size));
    }
}
