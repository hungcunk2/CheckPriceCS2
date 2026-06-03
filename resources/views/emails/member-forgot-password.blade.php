<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Khôi phục mật khẩu</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111;">
    <h2 style="margin:0 0 8px 0;">Khôi phục mật khẩu</h2>
    <p style="margin:0 0 10px 0;">Xin chào <strong>{{ $user->name }}</strong>,</p>
    <p style="margin:0 0 10px 0;">Mật khẩu tạm thời cho tài khoản {{ config('site.name') }}:</p>
    <div style="display:inline-block;padding:12px 16px;border:1px solid #ddd;border-radius:8px;background:#f8f9fa;margin:0 0 12px 0;">
        <div style="font-size:22px;letter-spacing:4px;font-weight:700;">{{ $temporaryPassword }}</div>
    </div>
    <p style="margin:0 0 10px 0;">Đăng nhập và đổi mật khẩu mạnh hơn khi có thể. Không chia sẻ mã cho người khác.</p>
    <p style="margin:16px 0 0 0;color:#666;font-size:12px;">Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>
</body>
</html>
