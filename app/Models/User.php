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
        'phone',
        'nickname',
        'invitor_id',
        'api_token',
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
        $user->invitor_id = $data['invitor_id'] ?? '';

        return $user;
    }
}
