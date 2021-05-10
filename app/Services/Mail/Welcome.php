<?php

namespace App\Services\Mail;

use Illuminate\Mail\Mailable;

class Welcome extends Mailable
{
    public $token;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token)
    {
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
            ->text('emails.welcome')
            ->with([
                'token' => $this->token
            ]);
    }
}
