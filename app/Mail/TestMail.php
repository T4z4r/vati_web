<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TestMail extends Mailable
{
    public function build(): static
    {
        return $this->subject('VATI test email')->text('emails.test');
    }
}
