<?php


namespace App\Modules\Spider\Auth;


use App\Services\Qiniu\Http\Client;
use App\Models\User;

class UserAuthLink
{
    protected $channel;
    protected $headers;
    protected $message = '我要认证calibur';
    protected $client;

    public function __construct($channel)
    {
        $this->channel = $channel;
        $this->headers = [
            'cookie' => $this->getCookie()
        ];
        $this->client = new Client();
    }

    public function verify($userId, $verifyId)
    {

    }

    public function status()
    {

    }

    public function writeDB($userId, $verifyId)
    {
        User
            ::where('id', $userId)
            ->update([
                "{$this->channel}_id" => $verifyId
            ]);
    }

    public function getCookie()
    {
        $filePath = $this->getFilePath();
        if (!file_exists($filePath))
        {
            return '';
        }

        $fp = fopen($filePath, 'r');
        $cookie = fread($fp, filesize($filePath));

        fclose($fp);

        return trim($cookie);
    }

    public function setCookie($cookie)
    {
        $filePath = $this->getFilePath();
        if(!file_exists($filePath))
        {
            touch($filePath);
        }

        $fp = fopen($filePath, 'w');

        if ( ! $fp)
        {
            return false;
        }

        fwrite($fp, $cookie);
        fclose($fp);

        return true;
    }

    protected function getFilePath()
    {
        return base_path() . '/storage/app/cookie/' . $this->channel . '.txt';
    }
}
