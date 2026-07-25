<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $code)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Verify your Ledgerly account')
            ->view('emails.otp')
            ->with(['name' => $this->name, 'code' => $this->code]);
    }
}
