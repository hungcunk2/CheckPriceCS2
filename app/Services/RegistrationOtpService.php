<?php

namespace App\Services;

use App\Mail\RegisterOtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class RegistrationOtpService
{
    private const PENDING_PREFIX = 'register_pending:';

    private const SEND_COOLDOWN_PREFIX = 'register_otp_sent:';

    public function ttlMinutes(): int
    {
        return max(5, (int) config('cs2price.registration_otp_ttl_minutes', 10));
    }

    public function resendCooldownSeconds(): int
    {
        return max(30, (int) config('cs2price.registration_otp_resend_cooldown_seconds', 60));
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendOtp(string $name, string $email, string $plainPassword): array
    {
        $email = $this->normalizeEmail($email);
        $cooldownKey = self::SEND_COOLDOWN_PREFIX.$email;

        if (Cache::has($cooldownKey)) {
            $wait = Cache::get($cooldownKey);
            $seconds = is_int($wait) ? max(1, $wait - time()) : $this->resendCooldownSeconds();

            return ['ok' => false, 'message' => 'Vui lòng đợi '.$seconds.' giây trước khi gửi lại mã.'];
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = $this->ttlMinutes();

        Cache::put(self::PENDING_PREFIX.$email, [
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes($ttl)->timestamp,
        ], now()->addMinutes($ttl));

        Cache::put($cooldownKey, time() + $this->resendCooldownSeconds(), $this->resendCooldownSeconds());

        try {
            Mail::to($email)->send(new RegisterOtpMail($email, $name, $otp, $ttl));
        } catch (Throwable $e) {
            report($e);
            Cache::forget(self::PENDING_PREFIX.$email);

            return [
                'ok' => false,
                'message' => 'Không gửi được email. Kiểm tra cấu hình MAIL_* trong .env và thử lại.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Đã gửi mã OTP 6 số tới '.$email.'. Nhập mã để hoàn tất đăng ký.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function resendOtp(string $email): array
    {
        $email = $this->normalizeEmail($email);
        $pending = Cache::get(self::PENDING_PREFIX.$email);

        if (! is_array($pending) || empty($pending['password'])) {
            return ['ok' => false, 'message' => 'Phiên đăng ký đã hết hạn. Vui lòng điền form lại.'];
        }

        return $this->sendOtp(
            (string) $pending['name'],
            $email,
            (string) $pending['password'],
        );
    }

    /**
     * @return array{ok: bool, message: string, name?: string, email?: string, password?: string}
     */
    public function verifyOtp(string $email, string $otp): array
    {
        $email = $this->normalizeEmail($email);
        $pending = Cache::get(self::PENDING_PREFIX.$email);

        if (! is_array($pending)) {
            return ['ok' => false, 'message' => 'Mã OTP đã hết hạn hoặc chưa gửi. Vui lòng đăng ký lại.'];
        }

        $expiresAt = (int) ($pending['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            Cache::forget(self::PENDING_PREFIX.$email);

            return ['ok' => false, 'message' => 'Mã OTP đã hết hạn. Vui lòng gửi mã mới.'];
        }

        $otp = trim($otp);
        if (! preg_match('/^\d{6}$/', $otp)) {
            return ['ok' => false, 'message' => 'Mã OTP phải gồm 6 chữ số.'];
        }

        if (! Hash::check($otp, (string) ($pending['otp_hash'] ?? ''))) {
            return ['ok' => false, 'message' => 'Mã OTP không đúng.'];
        }

        Cache::forget(self::PENDING_PREFIX.$email);

        return [
            'ok' => true,
            'message' => 'Xác nhận email thành công.',
            'name' => (string) ($pending['name'] ?? ''),
            'email' => $email,
            'password' => (string) ($pending['password'] ?? ''),
        ];
    }

    public function hasPending(string $email): bool
    {
        return Cache::has(self::PENDING_PREFIX.$this->normalizeEmail($email));
    }

    public function maskEmail(string $email): string
    {
        $email = $this->normalizeEmail($email);
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = Str::substr($local, 0, min(2, strlen($local)));

        return $visible.'***@'.$domain;
    }

    private function normalizeEmail(string $email): string
    {
        return trim(mb_strtolower($email));
    }
}
