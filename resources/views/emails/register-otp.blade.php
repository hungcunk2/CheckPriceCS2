@php
    $siteUrl = rtrim(config('site.url', url('/')), '/');
    $otpDigits = str_split(str_pad(preg_replace('/\D/', '', (string) $otpCode), 6, '0', STR_PAD_LEFT));
    $supportEmail = config('mail.from.address', 'support@checkpricecs2.io.vn');
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã OTP - CheckPriceCS2</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        @media only screen and (max-width: 600px) {
            .otp-box {
                width: 40px !important;
                height: 50px !important;
                font-size: 20px !important;
                line-height: 50px !important;
            }
            .container {
                width: 100% !important;
                padding: 20px 15px !important;
            }
            .otp-container {
                gap: 6px !important;
            }
            .btn-verify {
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #0B1220; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;">

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #0B1220;">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="container" style="max-width: 600px; width: 100%; background-color: #0F172A; border-radius: 16px; border: 1px solid rgba(255,255,255,0.06);">

                    <tr>
                        <td style="padding: 40px 40px 0 40px; text-align: center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin-bottom: 8px;">
                                <tr>
                                    <td style="font-size: 24px; font-weight: 800; color: #F8FAFC; letter-spacing: -0.5px;">
                                        CheckPrice<span style="background: linear-gradient(135deg, #ffb09a, #f78166); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; color: #f78166;">CS2</span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0; font-size: 13px; color: #64748B; letter-spacing: 2px; text-transform: uppercase;">Xác nhận bảo mật</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top: 1px solid rgba(255,255,255,0.06);"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px 40px 24px 40px;">
                            <h1 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 700; color: #F8FAFC; text-align: center; line-height: 1.3;">
                                Xác nhận đăng ký tài khoản
                            </h1>
                            <p style="margin: 0 0 24px 0; font-size: 15px; color: #94A3B8; text-align: center; line-height: 1.6;">
                                Chào bạn, đây là mã OTP xác thực của bạn. Mã này sẽ hết hiệu lực sau <strong style="color: #F8FAFC;">{{ $expiresMinutes }} phút</strong>.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="otp-container" style="margin: 0 auto 28px auto;">
                                <tr>
                                    <td>
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border-spacing: 10px 0;">
                                            <tr>
                                                @foreach ($otpDigits as $digit)
                                                <td class="otp-box" style="width: 52px; height: 64px; background-color: #1E293B; border: 2px solid #334155; border-radius: 12px; text-align: center; font-size: 24px; font-weight: 700; color: #F8FAFC; vertical-align: middle;">
                                                    <span style="display: inline-block; line-height: 64px;">{{ $digit }}</span>
                                                </td>
                                                @endforeach
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto 28px auto;">
                                <tr>
                                    <td>
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border-spacing: 8px 0;">
                                            <tr>
                                                <td style="background-color: rgba(96,165,250,0.10); border: 1px solid rgba(96,165,250,0.25); border-radius: 20px; padding: 6px 14px;">
                                                    <span style="font-size: 13px; color: #60A5FA;">&#9201; Hết hạn sau {{ $expiresMinutes }} phút</span>
                                                </td>
                                                <td style="background-color: rgba(248,250,252,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 6px 14px;">
                                                    <span style="font-size: 13px; color: #94A3B8;">&#128274; Không chia sẻ mã này</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto 28px auto;">
                                <tr>
                                    <td>
                                        <a href="{{ $verificationUrl }}" class="btn-verify" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #60A5FA, #F97316); color: #ffffff; text-decoration: none; border-radius: 10px; font-size: 15px; font-weight: 600; text-align: center; box-shadow: 0 4px 20px rgba(96,165,250,0.30);">
                                            Mở CheckPriceCS2 &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top: 1px solid rgba(255,255,255,0.06);"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 40px 40px 40px; text-align: center;">
                            <p style="margin: 0 0 8px 0; font-size: 13px; color: #475569; line-height: 1.5;">
                                Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email này hoặc <a href="mailto:{{ $supportEmail }}" style="color: #60A5FA; text-decoration: none;">liên hệ hỗ trợ</a>.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #334155;">
                                &copy; {{ date('Y') }} CheckPriceCS2. Bảo mật &middot; <a href="{{ $siteUrl }}/privacy" style="color: #475569; text-decoration: none;">Chính sách bảo mật</a>
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
