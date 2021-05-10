<?php


namespace App\Services\Sms;


use Illuminate\Support\Facades\Mail;

class Email
{
    public function register($mail, $code)
    {
        try
        {
            Mail::to([
                'email' => $mail
            ])->send(new \App\Services\Mail\Welcome($code));

            return null;
        }
        catch(\Exception $e)
        {
            return $e;
        }
    }

    public function login($mail, $code)
    {
        try
        {
            Mail::to([
                'email' => $mail
            ])->send(new \App\Services\Mail\Welcome($code));

            return null;
        }
        catch(\Exception $e)
        {
            return $e;
        }
    }

    public function forgotPassword($mail, $code)
    {
        try
        {
            Mail::to([
                'email' => $mail
            ])->send(new \App\Services\Mail\Welcome($code));

            return null;
        }
        catch(\Exception $e)
        {
            return $e;
        }
    }

    public function bindEmail($mail, $code)
    {
        try
        {
            Mail::to([
                'email' => $mail
            ])->send(new \App\Services\Mail\Welcome($code));

            return null;
        }
        catch(\Exception $e)
        {
            return $e;
        }
    }
}
