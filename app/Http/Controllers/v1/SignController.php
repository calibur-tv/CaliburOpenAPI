<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Repositories\UserRepository;
use App\Http\Transformers\User\UserAuthResource;
use App\Models\Group;
use App\Models\Relation;
use App\Services\Geetest\Captcha;
use App\Services\Sms\Email;
use App\Services\Sms\Message;
use App\Modules\WXBizDataCrypt;
use App\Services\Socialite\AccessToken;
use App\Services\Socialite\SocialiteManager;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use GuzzleHttp\Client;
use App\Modules\Spider\Auth\UserIsBilibili;

class SignController extends Controller
{
    /**
     * 发送手机验证码
     *
     * > 一个通用的接口，通过 `type` 和 `phone_number` 发送手机验证码.
     * 目前支持 `type` 为：
     * 1. `sign_up`，注册时调用
     * 2. `forgot_password`，找回密码时使用
     * 3. `bind_phone`，绑定手机号时使用
     *
     * > 目前返回的数字验证码是`6位`
     *
     * @Post("/sign/message")
     *
     * @Parameters({
     *      @Parameter("type", description="上面的某种type", type="string", required=true),
     *      @Parameter("phone_number", description="只支持`11位`的手机号", type="number", required=true),
     *      @Parameter("geetest", description="Geetest认证对象", type="object", required=true)
     * })
     *
     * @Transaction({
     *      @Response(201, body={"code": 0, "data": "短信已发送"}),
     *      @Response(400, body={"code": 40001, "message": "未经过图形验证码认证"}),
     *      @Response(401, body={"code": 40100, "message": "图形验证码认证失败"}),
     *      @Response(400, body={"code": 40003, "message": "各种错误"}),
     *      @Response(503, body={"code": 50310, "message": "短信服务暂不可用或请求过于频繁"})
     * })
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => [
                'required',
                Rule::in(['sign_up', 'forgot_password', 'bind_phone', 'sign_in']),
            ],
            'phone_number' => 'required|digits:11'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $phone = $request->get('phone_number');
        $type = $request->get('type');

        if ($this->checkMessageThrottle($phone))
        {
            return $this->resErrThrottle('一分钟内只能发送一次');
        }

        if ($type === 'sign_up')
        {
            $mustNew = false;
            $mustOld = false;
        }
        else if ($type === 'sign_in')
        {
            $mustNew = false;
            $mustOld = true;
        }
        else if ($type === 'forgot_password')
        {
            $mustNew = false;
            $mustOld = true;
        }
        else if ($type === 'bind_phone')
        {
            $mustNew = true;
            $mustOld = false;
        }
        else
        {
            $mustNew = false;
            $mustOld = false;
        }

        if ($mustNew && !$this->accessIsNew('phone', $phone))
        {
            return $this->resErrBad('手机号已注册');
        }

        if ($mustOld && $this->accessIsNew('phone', $phone))
        {
            return $this->resErrBad('未注册的手机号');
        }

        $authCode = $this->createMessageAuthCode($phone, $type);
        $sms = new Message();

        if ($type === 'sign_up')
        {
            $result = $sms->register($phone, $authCode);
        }
        else if ($type === 'sign_in')
        {
            $result = $sms->login($phone, $authCode);
        }
        else if ($type === 'forgot_password')
        {
            $result = $sms->forgotPassword($phone, $authCode);
        }
        else if ($type === 'bind_phone')
        {
            $result = $sms->bindPhone($phone, $authCode);
        }
        else
        {
            return $this->resErrBad();
        }

        if (!$result)
        {
            $this->checkMessageAuthCode($phone, $type, $authCode);
            $this->checkMessageThrottle($phone, true);
            return $this->resErrServiceUnavailable();
        }

        return $this->resOK('短信已发送');
    }

    public function sendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => [
                'required',
                Rule::in(['sign_up', 'forgot_password', 'bind_email', 'sign_in']),
            ],
            'email_address' => 'required|email'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $mail = $request->get('email_address');
        $type = $request->get('type');

        if ($this->checkMessageThrottle($mail))
        {
            return $this->resErrThrottle('一分钟内只能发送一次');
        }

        if ($type === 'sign_up')
        {
            $mustNew = false;
            $mustOld = false;
        }
        else if ($type === 'sign_in')
        {
            $mustNew = false;
            $mustOld = true;
        }
        else if ($type === 'forgot_password')
        {
            $mustNew = false;
            $mustOld = true;
        }
        else if ($type === 'bind_email')
        {
            $mustNew = true;
            $mustOld = false;
        }
        else
        {
            $mustNew = false;
            $mustOld = false;
        }

        if ($mustNew && !$this->accessIsNew('email', $mail))
        {
            return $this->resErrBad('邮箱已注册');
        }

        if ($mustOld && $this->accessIsNew('email', $mail))
        {
            return $this->resErrBad('未注册的邮箱');
        }

        $authCode = $this->createMessageAuthCode($mail, $type);
        $sms = new Email();

        if ($type === 'sign_up')
        {
            $error = $sms->register($mail, $authCode);
        }
        else if ($type === 'sign_in')
        {
            $error = $sms->login($mail, $authCode);
        }
        else if ($type === 'forgot_password')
        {
            $error = $sms->forgotPassword($mail, $authCode);
        }
        else if ($type === 'bind_email')
        {
            $error = $sms->bindEmail($mail, $authCode);
        }
        else
        {
            return $this->resErrBad();
        }

        if ($error)
        {
            $this->checkMessageAuthCode($mail, $type, $authCode);
            $this->checkMessageThrottle($mail, true);
            return $this->resErrServiceUnavailable($error->getMessage());
        }

        return $this->resOK('邮件已发送');
    }

    public function detectAccess(Request $request)
    {
        $method = $request->get('method') ?: 'phone';
        $access = $request->get('access');

        $isNew = $this->accessIsNew($method, $access);

        return $this->resOK([
            'is_new' => $isNew
        ]);
    }

    /**
     * 获取 Geetest 验证码
     *
     * @Get("/image/captcha")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"success": "数字0或1", "gt": "Geetest.gt", "challenge": "Geetest.challenge", "payload": "字符串荷载"}})
     * })
     */
    public function captcha()
    {
        $captcha = new Captcha();

        return $this->resOK($captcha->get());
    }

    /**
     * 用户注册
     *
     * 目前仅支持使用手机号注册
     *
     * @Post("/sign/register")
     *
     * @Parameters({
     *      @Parameter("access", description="手机号", type="number", required=true),
     *      @Parameter("authCode", description="6位数字的短信验证码", type="number", required=true),
     *      @Parameter("inviteCode", description="邀请码", type="string", required=false),
     * })
     *
     * @Transaction({
     *      @Response(201, body={"code": 0, "data": "JWT-Token"}),
     *      @Response(400, body={"code": 40003, "message": "各种错误"})
     * })
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access' => 'required|digits:11',
            'authCode' => 'required|digits:6'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $access = $request->get('access');
        $authCode = $request->get('authCode');
        $detect = $this->checkMessageAuthCode($access, 'sign_up', $authCode);

        if ($detect === null)
        {
            return $this->resErrBad('短信验证码过期');
        }
        if ($detect === false)
        {
            return $this->resErrBad('短信验证码错误');
        }

        if (!$this->accessIsNew('phone', $access))
        {
            $user = User
                ::where('phone', $access)
                ->first();

            return $this->resOK($user->api_token);
        }

        $secret = $request->get('secret');

        $data = [
            'password' => $secret ?? $authCode,
            'phone' => $access
        ];

        $inviteCode = $request->get('inviteCode');
        if ($inviteCode)
        {
            $hasInvitor = User::where('id', $inviteCode)->count() !== 0;
            if ($hasInvitor)
            {
                $data['invitor_id'] = $inviteCode;
            }
        }

        $user = User::createUser($data);

        return $this->resOK($user->api_token);
    }

    /**
     * 用户登录
     *
     * 目前仅支持手机号和密码登录
     *
     * @Post("/sign/login")
     *
     * @Parameters({
     *      @Parameter("access", description="手机号", type="number", required=true),
     *      @Parameter("secret", description="6至16位的密码", type="string", required=true),
     *      @Parameter("geetest", description="Geetest认证对象", type="object", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "JWT-Token"}),
     *      @Response(400, body={"code": 40001, "message": "未经过图形验证码认证"}),
     *      @Response(401, body={"code": 40100, "message": "图形验证码认证失败"}),
     *      @Response(400, body={"code": 40003, "message": "各种错误"})
     * })
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access' => 'required|digits:11',
            'secret' => 'required|min:6|max:16'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $method = $request->get('method') ?: 'pwd';
        $access = $request->get('access');
        $secret = $request->get('secret');
        $user = User::where('phone', $access)->first();

        if (is_null($user))
        {
            return $this->resErrBad('未注册的账号');
        }

        if ($method === 'pwd')
        {
            if (!$user->verifyPassword($secret))
            {
                return $this->resErrBad('密码错误');
            }
        }
        else if ($method === 'msg')
        {
            $detect = $this->checkMessageAuthCode($access, 'sign_in', $secret);

            if ($detect === null)
            {
                return $this->resErrBad('短信验证码过期');
            }
            if ($detect === false)
            {
                return $this->resErrBad('短信验证码错误');
            }
        }

        $role = $request->get('role');
        if ($role && $user->cant($role))
        {
            return $this->resErrRole();
        }

        return $this->resOK($user->api_token);
    }

    public function getUserInfo(Request $request)
    {
        $user = $request->user();
        $role = $request->get('role');

        if ($role && $user->cant($role))
        {
            return $this->resErrRole();
        }

        $group = $request->get('with_group');
        if ($group)
        {
            $relation = Relation
                ::where('type', 1)
                ->where('attach_id', $user->id)
                ->first();

            if ($relation)
            {
                $group = Group
                    ::where('id', $relation->detach_id)
                    ->first();

                if ($group)
                {
                    $user->group = [
                        'id' => $group->id,
                        'name' => $group->name,
                        'avatar' => $group->avatar
                    ];
                }
            }
        }

        return $this->resOK(new UserAuthResource($user));
    }

    /**
     * 用户登出
     *
     * @Post("/sign/logout")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"}),
     * @Response(204)
     */
    public function logout()
    {
        return $this->resOK();
    }

    /**
     * APP授权QQ登录、注册
     *
     * @Post("/sign/oauth2/qq")
     *
     * @Parameters({
     *      @Parameter("from", description="如果是登录，就是 sign，如果是绑定，就是 bind", type="string", required=true),
     *      @Parameter("access_token", description="登录授权的 access_code", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "账号绑定成功"}),
     *      @Response(201, body={"code": 0, "data": "JWT-TOKEN"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"}),
     *      @Response(403, body={"code": 40301, "message": "未登录或已绑定"}),
     *      @Response(503, body={"code": 50301, "message": "服务暂时不可用"})
     * })
     */
    public function qqAuthRedirect(Request $request)
    {
        $from = $request->get('from') === 'bind' ? 'bind' : 'sign';
        $code = $request->get('access_token');
        if (!$code)
        {
            return $this->resErrBad('请求参数错误');
        }

        $socialite = new SocialiteManager(config('app.oauth2', []), $request);
        $accessToken = new AccessToken([
            'access_token' => $code
        ]);

        try
        {
            $user = $socialite
                ->driver('qq')
                ->user($accessToken);
        }
        catch (\Exception $e)
        {
            return $this->resErrServiceUnavailable();
        }

        $openId = $user['id'];
        $uniqueId = $user['unionid'];
        $isNewUser = $this->accessIsNew('qq_unique_id', $uniqueId);

        if ($from === 'bind')
        {
            if (!$isNewUser)
            {
                return $this->resErrRole('该QQ号已绑定其它账号');
            }

            $userSlug = $request->get('slug');
            $userZone = $request->get('zone');
            $hasUser = User
                ::where('slug', $userSlug)
                ->where('zone', $userZone)
                ->count();

            if (!$hasUser)
            {
                return $this->resErrRole('继续操作前请先登录');
            }

            User
                ::where('slug', $userSlug)
                ->update([
                    'qq_open_id' => $openId,
                    'qq_unique_id' => $uniqueId
                ]);

            return $this->resOK();
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
                return $this->resErrRole('该账号不存在了');
            }
        }

        return $this->resOK($user->api_token);
    }

    /**
     * APP授权微信登录、注册
     *
     * @Post("/sign/oauth2/wechat")
     *
     * @Parameters({
     *      @Parameter("from", description="如果是登录，就是 sign，如果是绑定，就是 bind", type="string", required=true),
     *      @Parameter("access_token", description="登录授权的 access_code", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "账号绑定成功"}),
     *      @Response(201, body={"code": 0, "data": "JWT-TOKEN"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"}),
     *      @Response(403, body={"code": 40301, "message": "未登录或已绑定"}),
     *      @Response(503, body={"code": 50301, "message": "服务暂时不可用"})
     * })
     */
    public function wechatAuthRedirect(Request $request)
    {
        $from = $request->get('from') === 'bind' ? 'bind' : 'sign';
        $code = $request->get('access_token');
        $open_id = $request->get('openid');
        if (!$code || !$open_id)
        {
            return $this->resErrBad();
        }

        $socialite = new SocialiteManager(config('app.oauth2', []), $request);
        $accessToken = new AccessToken([
            'access_token' => $code,
            'openid' => $open_id
        ]);

        try
        {
            $user = $socialite
                ->driver('weixin')
                ->user($accessToken);
        }
        catch (\Exception $e)
        {
            return $this->resErrServiceUnavailable();
        }

        $openId = $user['original']['openid'];
        $uniqueId = $user['original']['unionid'];
        $isNewUser = $this->accessIsNew('wechat_unique_id', $uniqueId);

        if ($from === 'bind')
        {
            if (!$isNewUser)
            {
                return $this->resErrRole('该微信号已绑定其它账号');
            }

            $userSlug = $request->get('slug');
            $userZone = $request->get('zone');
            $hasUser = User
                ::where('slug', $userSlug)
                ->where('zone', $userZone)
                ->count();

            if (!$hasUser)
            {
                return $this->resErrRole('继续操作前请先登录');
            }

            User
                ::where('slug', $userSlug)
                ->update([
                    'wechat_open_id' => $openId,
                    'wechat_unique_id' => $uniqueId
                ]);

            return $this->resOK();
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

            if (is_null($user))
            {
                return $this->resErrRole('该账号不存在了');
            }
        }

        return $this->resOK($user->api_token);
    }

    /**
     * 绑定用户手机号
     *
     * @Post("/sign/bind_phone")
     *
     * @Parameters({
     *      @Parameter("phone", description="手机号", type="number", required=true),
     *      @Parameter("authCode", description="6位数字的短信验证码", type="number", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "绑定成功"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误或验证码过期或手机号已占用"})
     * })
     */
    public function bindPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|digits:11',
            'authCode' => 'required|digits:6'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $phone = $request->get('phone');
        $detect = $this->checkMessageAuthCode($phone, 'bind_phone', $request->get('authCode'));

        if ($detect === null)
        {
            return $this->resErrBad('短信验证码过期');
        }
        if ($detect === false)
        {
            return $this->resErrBad('短信验证码错误');
        }

        if (!$this->accessIsNew('phone', $phone))
        {
            return $this->resErrBad('该手机号已绑定另外一个账号');
        }

        $user = $request->user();

        if ($user->phone)
        {
            $pattern = '/(\d{3})(\d{4})(\d{4})/i';
            $replacement = '$1****$3';
            $maskPhone = preg_replace($pattern, $replacement, $user->phone);

            return $this->resErrBad('您的账号已绑定了手机号：' . $maskPhone);
        }

        $user->update([
            'phone' => $phone
        ]);

        return $this->resOK('手机号绑定成功');
    }

    public function bindEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_address' => 'required|email',
            'email_code' => 'required|digits:6'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $email = $request->get('email_address');
        $detect = $this->checkMessageAuthCode($email, 'bind_email', $request->get('email_code'));

        if ($detect === null)
        {
            return $this->resErrBad('邮箱验证码过期');
        }
        if ($detect === false)
        {
            return $this->resErrBad('邮箱验证码错误');
        }

        if (!$this->accessIsNew('email', $email))
        {
            return $this->resErrBad('该邮箱已绑定另外一个账号');
        }

        $user = $request->user();

        if ($user->email)
        {
            return $this->resErrBad('您的账号已绑定了其它邮箱：' . $user->email);
        }

        $user->update([
            'email' => $email
        ]);

        $org = explode('@', $email)[1];

        /**
         * 查询或创建 group
         */
        $group = Group
            ::where('type', 1)
            ->where('name', $org)
            ->first();
        if (!$group)
        {
            $group = Group::create([
                'type' => 1,
                'name' => $org
            ]);
        }

        /**
         * 从旧的 group 离开，加入新的 Group
         */
        $relation = Relation
            ::where('attach_id', $user->id)
            ->where('type', 1)
            ->first();
        if ($relation)
        {
            $relation->delete();
        }
        Relation::create([
            'attach_id' => $user->id,
            'detach_id' => $group->id,
            'type' => 1,
        ]);

        return $this->resOK('邮箱绑定成功');
    }

    public function bindWechatUser(Request $request)
    {
        $appName = $request->get('app_name');
        if (!in_array($appName, array_keys(config("app.oauth2.wechat_mini_app"))))
        {
            return $this->resErrBad();
        }

        $iv = $request->get('iv');
        $code = $request->get('code');
        $encryptedData = $request->get('encrypted_data');

        $client = new Client();
        $appId = config("app.oauth2.wechat_mini_app.{$appName}.client_id");
        $appSecret = config("app.oauth2.wechat_mini_app.{$appName}.client_secret");
        $resp = $client->get(
            "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code",
            [
                'Accept' => 'application/json'
            ]
        );
        $body = json_decode($resp->getBody(), true);

        if (!isset($body['session_key']))
        {
            return $this->resErrServiceUnavailable('微信授权失败');
        }

        $tool = new WXBizDataCrypt($appId, $body['session_key']);
        $code = $tool->decryptData($encryptedData, $iv, $data);

        if ($code)
        {
            return $this->resErrServiceUnavailable('微信服务异常：' . $code);
        }

        $data = json_decode($data, true);
        $uniqueId = $data['unionId'];
        $isNewUser = $this->accessIsNew('wechat_unique_id', $uniqueId);

        if (!$isNewUser)
        {
            return $this->resErrBad('该微信号已绑定另外一个账号');
        }

        $user = $request->user();
        $user->update([
            'wechat_open_id' => $data['openId'],
            'wechat_unique_id' => $uniqueId
        ]);

        return $this->resOK();
    }

    public function bindQQUser(Request $request)
    {
        $appName = $request->get('app_name');
        if (!in_array($appName, array_keys(config("app.oauth2.wechat_mini_app"))))
        {
            return $this->resErrBad();
        }

        $iv = $request->get('iv');
        $code = $request->get('code');
        $encryptedData = $request->get('encrypted_data');

        $client = new Client();
        $appId = config("app.oauth2.qq_mini_app.{$appName}.client_id");
        $appSecret = config("app.oauth2.qq_mini_app.{$appName}.client_secret");
        $resp = $client->get(
            "https://api.q.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code",
            [
                'Accept' => 'application/json'
            ]
        );
        $body = json_decode($resp->getBody(), true);

        if (!isset($body['session_key']))
        {
            return $this->resErrServiceUnavailable('QQ服务调用失败');
        }

        $tool = new WXBizDataCrypt($appId, $body['session_key']);
        $code = $tool->decryptData($encryptedData, $iv, $data);

        if ($code)
        {
            return $this->resErrServiceUnavailable('QQ服务异常：' . $code);
        }

        $data = json_decode($data, true);
        $uniqueId = $data['unionId'];
        $isNewUser = $this->accessIsNew('qq_unique_id', $uniqueId);

        if (!$isNewUser)
        {
            return $this->resErrBad('该QQ号已绑定另外一个账号');
        }

        $user = $request->user();
        $user->update([
            'qq_open_id' => $data['openId'],
            'qq_unique_id' => $uniqueId,
        ]);

        return $this->resOK();
    }

    public function wechatMiniAppSign(Request $request)
    {
        $appName = $request->get('app_name');
        if (!in_array($appName, array_keys(config("app.oauth2.wechat_mini_app"))))
        {
            return $this->resErrBad();
        }

        $iv = $request->get('iv');
        $code = $request->get('code');
        $encryptedData = $request->get('encrypted_data');

        $client = new Client();
        $appId = config("app.oauth2.wechat_mini_app.{$appName}.client_id");
        $appSecret = config("app.oauth2.wechat_mini_app.{$appName}.client_secret");
        $resp = $client->get(
            "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code",
            [
                'Accept' => 'application/json'
            ]
        );
        $body = json_decode($resp->getBody(), true);

        if (!isset($body['session_key']))
        {
            return $this->resErrServiceUnavailable('微信授权失败');
        }

        $tool = new WXBizDataCrypt($appId, $body['session_key']);
        $code = $tool->decryptData($encryptedData, $iv, $data);

        if ($code)
        {
            return $this->resErrServiceUnavailable('微信服务异常：' . $code);
        }

        $data = json_decode($data, true);
        $phone = $data['purePhoneNumber'];

        if (!$phone)
        {
            return $this->resErrServiceUnavailable('获取手机号失败');
        }

        if ($this->accessIsNew('phone', $phone))
        {
            $user = User::createUser([
                'password' => $body['session_key'],
                'phone' => $phone
            ]);
        }
        else
        {
            $user = User
                ::where('phone', $phone)
                ->first();
        }

        if ($this->accessIsNew('wechat_unique_id', $body['unionid']))
        {
            $user->update([
                'wechat_unique_id' => $body['unionid'],
                'wechat_open_id' => $body['openid']
            ]);
        }

        return $this->resOK($user->api_token);
    }

    // QQ小程序注册用户或获取当前用户的 token
    public function qqMiniAppLogin(Request $request)
    {
        $appName = $request->get('app_name');
        if (!in_array($appName, array_keys(config("app.oauth2.wechat_mini_app"))))
        {
            return $this->resErrBad();
        }

        $iv = $request->get('iv');
        $user = $request->get('user');
        $sessionKey = $request->get('session_key');
        $encryptedData = $request->get('encrypted_data');

        $appId = config("app.oauth2.qq_mini_app.{$appName}.client_id");

        $tool = new WXBizDataCrypt($appId, $sessionKey);
        $code = $tool->decryptData($encryptedData, $iv, $data);

        if ($code)
        {
            return $this->resErrServiceUnavailable('QQ服务异常：' . $code);
        }

        $data = json_decode($data, true);

        $uniqueId = $data['unionId'];
        $isNewUser = $this->accessIsNew('qq_unique_id', $uniqueId);
        if ($isNewUser)
        {
            // signUp
            $data = [
                'nickname' => $user['nickName'],
                'qq_open_id' => $data['openId'],
                'qq_unique_id' => $uniqueId,
                'password' => str_rand()
            ];

            $user = User::createUser($data);
        }
        else
        {
            $user = User
                ::where('qq_unique_id', $uniqueId)
                ->first();

            if (is_null($user))
            {
                return $this->resErrNotFound('这个用户已经消失了');
            }
        }

        return $this->resOK($user->api_token);
    }

    // QQ小程序获取用户的 session_key 或获取当前用户的 token
    public function qqMiniAppToken(Request $request)
    {
        $code = $request->get('code');
        $appName = $request->get('app_name');
        if (!$code || !in_array($appName, array_keys(config("app.oauth2.wechat_mini_app"))))
        {
            return $this->resErrBad();
        }

        $client = new Client();
        $appId = config("app.oauth2.qq_mini_app.{$appName}.client_id");
        $appSecret = config("app.oauth2.qq_mini_app.{$appName}.client_secret");
        $resp = $client->get(
            "https://api.q.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code",
            [
                'Accept' => 'application/json'
            ]
        );
        $body = json_decode($resp->getBody(), true);
        $uniqueId = $body['unionid'] ?? '';
        if (!isset($body['session_key']))
        {
            return $this->resErrServiceUnavailable('请尝试使用手机号登录/注册');
        }

        if (!$uniqueId)
        {
            return $this->resOK([
                'type' => 'key',
                'data' => $body['session_key']
            ]);
        }

        $user = User
            ::where('qq_unique_id', $uniqueId)
            ->first();

        if (is_null($user))
        {
            return $this->resOK([
                'type' => 'key',
                'data' => $body['session_key']
            ]);
        }

        return $this->resOK([
            'type' => 'token',
            'data' => $user->api_token
        ]);
    }

    /**
     * 重置密码
     *
     * @Post("/sign/reset")
     *
     * @Parameters({
     *      @Parameter("access", description="手机号", type="number", required=true),
     *      @Parameter("secret", description="6至16位的密码", type="string", required=true),
     *      @Parameter("authCode", description="6位数字的短信验证码", type="number", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "密码重置成功"}),
     *      @Response(400, body={"code": 40001, "message": "未经过图形验证码认证"}),
     *      @Response(401, body={"code": 40100, "message": "图形验证码认证失败"}),
     *      @Response(400, body={"code": 40003, "message": "各种错误"})
     * })
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access' => 'required|digits:11',
            'secret' => 'required|min:6|max:16',
            'authCode' => 'required|digits:6'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $access = $request->get('access');
        $detect = $this->checkMessageAuthCode($access, 'forgot_password', $request->get('authCode'));

        if ($detect === null)
        {
            return $this->resErrBad('短信验证码过期');
        }
        if ($detect === false)
        {
            return $this->resErrBad('短信验证码错误');
        }

        $user = User
            ::where('phone', $access)
            ->first();
        if (is_null($user))
        {
            return $this->resErrNotFound();
        }

        $user->update([
                'password' => $request->get('secret')
            ]);

        $user->createApiToken();

        return $this->resOK('密码重置成功');
    }

    public function shareTicket(Request $request)
    {
        $url = $request->get('url');
        if (!$url)
        {
            return $this->resErrBad();
        }

        $userRepository = new UserRepository();

        return $this->resOK($userRepository->getWechatJsSDKConfig($url));
    }

    public function OauthChannelVerify(Request $request)
    {
        $channel = $request->get('channel');
        $verifyId = $request->get('id');
        $userId = $request->user()->id;

        $service = null;
        if ($channel === 'bilibili')
        {
            $service = new UserIsBilibili();
        }

        if (!$service)
        {
            return $this->resErrBad();
        }

        $result = $service->verify($userId, $verifyId);

        return $this->resOK($result);
    }

    private function accessIsNew($method, $access)
    {
        return User::withTrashed()->where($method, $access)->count() === 0;
    }

    private function createMessageAuthCode($access, $type)
    {
        $key = 'validate_message_' . $type . ':' . $access;
        $value = rand(100000, 999999);

        Redis::SET($key, $value);
        Redis::EXPIRE($key, 300);

        return $value;
    }

    private function checkMessageAuthCode($access, $type, $token)
    {
        $key = 'validate_message_' . $type . ':' . $access;
        $value = Redis::GET($key);
        if (is_null($value))
        {
            return null;
        }

        Redis::DEL($key);
        return intval($value) === intval($token);
    }

    private function checkMessageThrottle($access, $isDelete = false)
    {
        $cacheKey = 'validate_message_throttle:' . $access;
        if ($isDelete)
        {
            Redis::DEL($cacheKey);
            return true;
        }
        if (Redis::EXISTS($cacheKey))
        {
            return true;
        }

        Redis::SET($cacheKey, 1);
        Redis::EXPIRE($cacheKey, 55);

        return false;
    }
}
