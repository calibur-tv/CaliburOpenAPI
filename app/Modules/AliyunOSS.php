<?php


namespace App\Modules;

use OSS\OssClient;
use GuzzleHttp\Client;

class AliyunOSS
{
    public function __construct($userId = 0)
    {
        $this->accessKeyId = config('app.aliyun.oss.id');
        $this->accessKeySecret = config('app.aliyun.oss.secret');
        $this->endpoint = config('app.aliyun.oss.endpoint');
        $this->bucket = 'calibur-arthur';
        $this->oss = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        $this->guzzle = new Client();
        $this->userId = $userId;
    }

    public function fetch($url)
    {
        $resp = $this->guzzle->get($url);
        if ($resp->getStatusCode() !== 200)
        {
            return $url;
        }

        $name = 'users/' . $this->userId . '/' . time() . '-' . last(explode('/', explode('?', $url)[0]));
        $result = $this->upload($resp->getBody(), $name);

        return $result ? $result : $url;
    }

    public function upload($file, $name)
    {
        try
        {
            $this->oss->putObject(
                $this->bucket,
                $name,
                $file
            );

            return $name;
        }
        catch (\Exception $e)
        {
            return $e;
        }
    }
}
