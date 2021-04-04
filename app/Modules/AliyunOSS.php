<?php


namespace App\Modules;

use GuzzleHttp\Client;

class AliyunOSS extends OssClient
{
    public function __construct($userId = 0)
    {
        parent::__construct(
            config('app.aliyun.oss.id'),
            config('app.aliyun.oss.secret'),
            config('app.aliyun.oss.endpoint')
        );

        $this->bucket = 'calibur-arthur';
        $this->userId = $userId;
    }

    public function fetch($url, $prefix = 'users')
    {
        $client = new Client();
        $resp = $client->get($url);
        if ($resp->getStatusCode() !== 200)
        {
            return $url;
        }

        $name = $prefix . '/' . $this->userId . '/' . time() . '-' . last(explode('/', explode('?', $url)[0]));
        $result = $this->upload($resp->getBody(), $name);

        return $result ? $result : $url;
    }

    public function upload($file, $name)
    {
        try
        {
            $this->putObject(
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
