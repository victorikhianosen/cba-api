<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $companyName }}</title>
</head>

<body style="margin:0; padding:0; background-color:#f1f5f9; -webkit-font-smoothing:antialiased;">

    <!-- Preheader (hidden preview text) -->
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ \Illuminate\Support\Str::limit(strip_tags($content), 90) }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:40px 16px;">

                <!-- Card -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                    style="max-width:600px; width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="background:#002766; padding:28px 24px;">
                            <span
                                style="color:#ffffff; font-size:22px; font-weight:700; font-family:'Segoe UI', Arial, sans-serif; letter-spacing:0.5px;">
                                {{ $headerTitle }}
                            </span>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td
                            style="padding:36px 32px; color:#334155; font-family:'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.65;">

                            <div>
                                {!! $content !!}
                            </div>

                            <!-- Divider -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-top:1px solid #e2e8f0; font-size:0; line-height:0; height:1px;">
                                        &nbsp;</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 4px 0;">Thank you for choosing <strong>{{ $companyName }}</strong>.
                            </p>
                            <p style="margin:0; font-weight:600; color:#0f172a;">{{ $companyName }} Team</p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center"
                            style="background:#002766; color:#d1dbee; padding:22px 24px; font-family:'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6;">
                            &copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.
                        </td>
                    </tr>

                </table>

                <!-- Sub-footer -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                    style="max-width:600px; width:100%;">
                    <tr>
                        <td align="center"
                            style="padding:18px 24px; color:#94a3b8; font-family:'Segoe UI', Arial, sans-serif; font-size:12px; line-height:1.5;">
                            This is an automated message — please do not reply directly to this email.
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>

</html>