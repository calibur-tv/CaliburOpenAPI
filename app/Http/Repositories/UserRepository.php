<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:31
 */

namespace App\Http\Repositories;


use App\Http\Transformers\User\UserItemResource;
use GuzzleHttp\Client;
use App\Models\User;

class UserRepository extends Repository
{
    public function item($slug, $refresh = false)
    {
        if (!$slug)
        {
            return null;
        }

        $id = slug2id($slug);

        $result = $this->RedisItem("user:{$id}", function () use ($id)
        {
            $user = User
                ::where('id', $id)
                ->first();

            if (is_null($user))
            {
                return 'nil';
            }

            return new UserItemResource($user);
        }, $refresh);

        if ($result === 'nil')
        {
            return null;
        }

        return $result;
    }

    public function getWechatAccessToken()
    {
        return $this->RedisItem('wechat_js_sdk_access_token', function ()
        {
            $client = new Client();
            $appId = config('app.oauth2.weixin.client_id');
            $appSecret = config('app.oauth2.weixin.client_secret');
            $resp = $client->get("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}");
            $code = $resp->getStatusCode();
            if ($code !== 200)
            {
                return '';
            }

            try
            {
                $body = json_decode($resp->getBody(), true);
                return $body['access_token'];
            }
            catch (\Exception $e)
            {
                return '';
            }
        }, 'h');
    }

    public function getWechatJsApiTicket()
    {
        return $this->RedisItem('wechat_js_sdk_api_ticket', function ()
        {
            $client = new Client();
            $token = $this->getWechatAccessToken();
            $resp = $client->get("https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=jsapi");
            $code = $resp->getStatusCode();
            if ($code !== 200)
            {
                return '';
            }

            try
            {
                $body = json_decode($resp->getBody(), true);
                return $body['ticket'];
            }
            catch (\Exception $e)
            {
                return '';
            }
        }, 'h');
    }

    public function getWechatJsSDKConfig($url)
    {
        $jsapi_ticket = $this->getWechatJsApiTicket();
        $noncestr = str_rand(16);
        $timestamp = time();
        $signature = sha1("jsapi_ticket={$jsapi_ticket}&noncestr={$noncestr}&timestamp={$timestamp}&url={$url}");

        return [
            'appId' => config('app.oauth2.weixin.client_id'),
            'timestamp' => $timestamp,
            'nonceStr' => $noncestr,
            'signature' => $signature
        ];
    }
}
