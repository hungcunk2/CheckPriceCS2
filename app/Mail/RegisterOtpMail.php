<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegisterOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $recipientName,
        public string $otpCode,
        public int $expiresMinutes,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Mã xác nhận đăng ký — '.config('site.name', 'CheckPrice CS2'))
            ->view('emails.register-otp');
    }
}
