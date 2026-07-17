<h2 style="margin:0 0 16px 0; color:#0f172a; font-size:19px; font-weight:700;">
    Hello, {{ $user->first_name }}
</h2>

<p style="margin:0 0 16px 0;">
    Your password has been reset by an administrator. Below is your new temporary password:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin:0 0 20px 0;">
    <tr>
        <td style="padding:16px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                <tr>
                    <td style="padding:6px 0; color:#64748b; width:140px;">Username</td>
                    <td style="padding:6px 0; color:#0f172a; font-weight:600;">{{ $user->username }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0; color:#64748b;">New Password</td>
                    <td style="padding:6px 0; color:#0f172a; font-weight:600;">{{ $password }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0 0 16px 0;">
    For security reasons, please log in and change this password immediately.
</p>

<p style="margin:0;">
    If you did not request this password reset, please contact your administrator immediately.
</p>
