<?php

namespace App\Services\Mail;

use Illuminate\Mail\Mailable;

class Welcome extends Mailable
{
    public $name;
    public $token;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $token)
    {
        $this->name = $name;
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->view('emails.welcome')
            ->subject('欢迎加入 - calibur.tv')
            ->with([
                'name' => $this->name,
                'token' => $this->token
            ]);
    }
}
