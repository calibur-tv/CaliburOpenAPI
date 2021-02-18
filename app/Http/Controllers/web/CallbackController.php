<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-05-10
 * Time: 16:08
 */

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Services\Socialite\SocialiteManager;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    /**
     * QQ第三方登录调用授权
     *
     * @Get("/callback/oauth2/qq")
     *
     * @Response(302)
     */
    public function qqAuthEntry(Request $request)
    {
        $socialite = new SocialiteManager(config('app.oauth2', []), $request);

        return $socialite
            ->driver('qq')
            ->redirect('https://fc.calibur.tv/callback/auth/qq?' . http_build_query($request->all()));
    }

    // 微信开放平台登录 - PC
    public function wechatAuthEntry(Request $request)
    {
        $socialite = new SocialiteManager(config('app.oauth2', []), $request);

        return $socialite
            ->driver('wechat')
            ->redirect('https://fc.calibur.tv/callback/auth/wechat?' . http_build_query($request->all()));
    }

    /**
     * 微信公众平台登录 - H5
     *
     * @Get("/callback/oauth2/weixin")
     *
     * @Response(302)
     */
    public function weixinAuthEntry(Request $request)
    {
        $socialite = new SocialiteManager(config('app.oauth2', []), $request);

        return $socialite
            ->driver('weixin')
            ->redirect('https://fc.calibur.tv/callback/auth/weixin?' . http_build_query($request->all()));
    }

    public function qqAuthRedirect(Request $request)
    {
        $from = $request->get('from') === 'bind' ? 'bind' : 'sign';
        $code = $request->get('code');
        if (!$code)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '请求参数错误');
        }

        $socialite = new SocialiteManager(config('app.oauth2', []), $request);

        try
        {
            $user = $socialite
                ->driver('qq')
                ->user();
        }
        catch (\Exception $e)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '服务异常请重试');
        }

        $openId = $user['id'];
        $uniqueId = $user['unionid'];
        $isNewUser = $this->accessIsNew('qq_unique_id', $uniqueId);

        if ($from === 'bind')
        {
            if (!$isNewUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '该QQ号已绑定其它账号');
            }

            $token = $request->get('token');
            $hasUser = User
                ::where('api_token', $token)
                ->count();

            if (!$hasUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '继续操作前请先登录');
            }

            User
                ::where('api_token', $token)
                ->update([
                    'qq_open_id' => $openId,
                    'qq_unique_id' => $uniqueId
                ]);

            return redirect('https://www.calibur.tv/callback/auth-success?message=' . '已成功绑定QQ号');
        }

        if ($isNewUser)
        {
            // signUp
            $data = [
                'nickname' => $user['nickname'],
                'qq_open_id' => $openId,
                'qq_unique_id' => $uniqueId,
                'password' => str_rand()
            ];

            $user = User::createUser($data);
        }
        else
        {
            // signIn
            $user = User
                ::where('qq_unique_id', $uniqueId)
                ->first();

            if (is_null($user))
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '这个用户消失了');
            }
        }

        return redirect('https://www.calibur.tv/callback/auth-redirect?message=登录成功&token=' . $user->api_token . '&redirect=' . $request->get('redirect'));
    }

    public function wechatAuthRedirect(Request $request)
    {
        $from = $request->get('from') === 'bind' ? 'bind' : 'sign';
        $code = $request->get('code');
        if (!$code)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '请求参数错误');
        }

        $socialite = new SocialiteManager(config('app.oauth2', []), $request);

        try
        {
            $user = $socialite
                ->driver('wechat')
                ->user();
        }
        catch (\Exception $e)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '服务异常请重试');
        }

        $openId = $user['original']['openid'];
        $uniqueId = $user['original']['unionid'];
        $isNewUser = $this->accessIsNew('wechat_unique_id', $uniqueId);

        if ($from === 'bind')
        {
            if (!$isNewUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '该微信号已绑定其它账号');
            }

            $token = $request->get('token');
            $hasUser = User
                ::where('api_token', $token)
                ->count();

            if (!$hasUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '继续操作前请先登录');
            }

            User
                ::where('api_token', $token)
                ->update([
                    'wechat_open_id' => $openId,
                    'wechat_unique_id' => $uniqueId
                ]);

            return redirect('https://www.calibur.tv/callback/auth-success?message=' . '已成功绑定微信号');
        }

        if ($isNewUser)
        {
            // signUp
            $data = [
                'nickname' => $user['nickname'],
                'wechat_open_id' => $openId,
                'wechat_unique_id' => $uniqueId,
                'password' => str_rand()
            ];

            $user = User::createUser($data);
        }
        else
        {
            // signIn
            $user = User
                ::where('wechat_unique_id', $uniqueId)
                ->first();
        }

        return redirect('https://www.calibur.tv/callback/auth-redirect?message=登录成功&token=' . $user->api_token . '&redirect=' . $request->get('redirect'));
    }

    public function weixinAuthRedirect(Request $request)
    {
        $from = $request->get('from') === 'bind' ? 'bind' : 'sign';
        $code = $request->get('code');
        if (!$code)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '请求参数错误');
        }

        $socialite = new SocialiteManager(config('app.oauth2', []), $request);

        try
        {
            $user = $socialite
                ->driver('weixin')
                ->user();
        }
        catch (\Exception $e)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '服务异常请重试');
        }

        $openId = $user['original']['openid'];
        $uniqueId = $user['original']['unionid'];
        $isNewUser = $this->accessIsNew('wechat_unique_id', $uniqueId);

        if ($from === 'bind')
        {
            if (!$isNewUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '该微信号已绑定其它账号');
            }

            $token = $request->get('token');
            $hasUser = User
                ::where('api_token', $token)
                ->count();

            if (!$hasUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '继续操作前请先登录');
            }

            User
                ::where('api_token', $token)
                ->update([
                    'wechat_open_id' => $openId,
                    'wechat_unique_id' => $uniqueId
                ]);

            return redirect('https://www.calibur.tv/callback/auth-success?message=' . '已成功绑定微信号');
        }

        if ($isNewUser)
        {
            // signUp
            $data = [
                'nickname' => $user['nickname'],
                'wechat_open_id' => $openId,
                'wechat_unique_id' => $uniqueId,
                'password' => str_rand()
            ];

            $user = User::createUser($data);
        }
        else
        {
            // signIn
            $user = User
                ::where('wechat_unique_id', $uniqueId)
                ->first();
        }

        return redirect('https://www.calibur.tv/callback/auth-redirect?message=登录成功&token=' . $user->api_token . '&redirect=' . $request->get('redirect'));
    }

    public function aliyunOSSupload(Request $request)
    {
        // 1.获取OSS的签名header和公钥url
        $authorizationBase64 = $request->headers->get('authorization');
        $pubKeyUrlBase64 = $request->headers->get('x-oss-pub-key-url');
        if (!$authorizationBase64 || !$pubKeyUrlBase64)
        {
            return $this->resErrBad();
        }
        // 2.获取OSS的签名
        $authorization = base64_decode($authorizationBase64);
        // 3.获取公钥
        $pubKeyUrl = base64_decode($pubKeyUrlBase64);
        $client = new Client();
        $resp = $client->get($pubKeyUrl);
        $pubKey = $resp->getBody();
        if ($pubKey == "")
        {
            return $this->resErrServiceUnavailable();
        }
        // 4.获取回调body
        $body = $request->getContent();
        $path = '/' . $request->path();
        $fullUrl = $request->fullUrl();
        $pos = strpos($fullUrl, '?');
        // 5.拼接待签名字符串
        if ($pos === false)
        {
            $authStr = urldecode($path) . "\n" . $body;
        }
        else
        {
            $authStr = urldecode($path) . substr($fullUrl, $pos, strlen($fullUrl) - $pos) . "\n" . $body;
        }
        // 6.验证签名
        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);
        if ($ok != 1)
        {
            return $this->resErrRole();
        }

        $fileDir = $request->get('filename');
        $userId = str_replace('user-', '', explode($fileDir, '/')[0]);
        $meta = $request->except(['filename']);

        return $this->resOK([
            'data' => $request->all(),
            'uid' => $userId
        ]);
    }

    private function accessIsNew($method, $access)
    {
        return User::withTrashed()->where($method, $access)->count() === 0;
    }
}
