<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MemberForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Khôi phục mật khẩu — '.config('site.name', 'CheckPrice CS2'))
            ->view('emails.member-forgot-password');
    }
}
