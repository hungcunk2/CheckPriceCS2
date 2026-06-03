<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã xác nhận đăng ký</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111;">
    <h2 style="margin:0 0 8px 0;">Xác nhận đăng ký tài khoản</h2>
    <p style="margin:0 0 10px 0;">
        Xin chào <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="margin:0 0 10px 0;">
        Mã OTP để hoàn tất đăng ký trên {{ config('site.name', 'CheckPrice CS2') }}:
    </p>
    <div style="display:inline-block;padding:12px 16px;border:1px solid #ddd;border-radius:8px;background:#f8f9fa;margin:0 0 12px 0;">
        <div style="font-size:22px;letter-spacing:4px;font-weight:700;">{{ $otpCode }}</div>
    </div>
    <p style="margin:0 0 10px 0;">
        Mã có hiệu lực <strong>{{ $expiresMinutes }} phút</strong>. Không chia sẻ mã cho người khác.
    </p>
    <p style="margin:16px 0 0 0;color:#666;font-size:12px;">
        Nếu bạn không yêu cầu đăng ký, hãy bỏ qua email này.
    </p>
</body>
</html>
