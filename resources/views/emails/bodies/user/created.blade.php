<h2 style="margin:0 0 16px 0; color:#0f172a; font-size:19px; font-weight:700;">
    Welcome, {{ $user->first_name }}!
</h2>

<p style="margin:0 0 16px 0;">
    Your account has been successfully created. Below are your account details:
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
                    <td style="padding:6px 0; color:#64748b;">Password</td>
                    <td style="padding:6px 0; color:#0f172a; font-weight:600;">{{ $password }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0; color:#64748b;">Account Code</td>
                    <td style="padding:6px 0; color:#0f172a; font-weight:600;">{{ $user->code }}</td>
                </tr>
                @if($user->staff_code)
                <tr>
                    <td style="padding:6px 0; color:#64748b;">Staff Code</td>
                    <td style="padding:6px 0; color:#0f172a; font-weight:600;">{{ $user->staff_code }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:6px 0; color:#64748b;">Email</td>
                    <td style="padding:6px 0; color:#0f172a; font-weight:600;">{{ $user->email }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0 0 16px 0;">
    For security reasons, please log in and change your password on your first login.
</p>

<p style="margin:0;">
    If you did not expect this account to be created, please contact your administrator immediately.
</p>