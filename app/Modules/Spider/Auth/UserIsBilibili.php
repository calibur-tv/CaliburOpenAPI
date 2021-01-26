<?php


namespace App\Modules\Spider\Auth;

class UserIsBilibili extends UserAuthLink
{
    public function __construct()
    {
        parent::__construct('bilibili');
    }

    public function verify($userId, $verifyId)
    {
        $verifyId = intval($verifyId);
        try
        {
            $url = 'https://api.vc.bilibili.com/session_svr/v1/session_svr/get_sessions?session_type=1&group_fold=1&unfollow_fold=0&sort_rule=2&build=0&mobi_app=web';
            $resp = $this->client->get($url, $this->headers);
            $body = json_decode($resp->body, true);
            $list = $body['data']['session_list'];
            $msg = null;
            foreach ($list as $row)
            {
                if ($row['talker_id'] === $verifyId)
                {
                    $msg = $row['last_msg'];
                    break;
                }
            }

            if (!$msg)
            {
                return false;
            }

            $content = trim(json_decode($msg['content'])->content);
            $result = $content === $this->message;

            if (!$result)
            {
                return false;
            }

            $this->writeDB($userId, $verifyId);

            return true;
        }
        catch (\Exception $e)
        {
            return null;
        }
    }

    public function status()
    {
        try
        {
            $url = 'https://api.vc.bilibili.com/session_svr/v1/session_svr/get_sessions?session_type=1&group_fold=1&unfollow_fold=0&sort_rule=2&build=0&mobi_app=web';
            $resp = $this->client->get($url, $this->headers);
            $body = json_decode($resp->body, true);
            $list = $body['data']['session_list'];

            return gettype($list) === 'array';
        }
        catch (\Exception $e)
        {
            return false;
        }
    }
}
