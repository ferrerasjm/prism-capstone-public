<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your PRISM password change verification code</title>
    <style>
        @font-face { font-family:'Poppins'; src:url('{{ $fontRegularUrl }}') format('truetype'); font-weight:400; font-display:swap; }
        @font-face { font-family:'Poppins'; src:url('{{ $fontMediumUrl }}') format('truetype'); font-weight:500; font-display:swap; }
        @font-face { font-family:'Poppins'; src:url('{{ $fontSemiBoldUrl }}') format('truetype'); font-weight:600; font-display:swap; }
        @font-face { font-family:'Poppins'; src:url('{{ $fontBoldUrl }}') format('truetype'); font-weight:700; font-display:swap; }
        body, table, td, div, p, h1, span { font-family:'Poppins',Arial,Helvetica,sans-serif !important; }
        @media only screen and (max-width:620px) {
            .email-shell { padding:16px 10px !important; }
            .email-card { border-radius:14px !important; }
            .email-header, .email-body, .email-footer { padding-left:20px !important; padding-right:20px !important; }
            .email-title { font-size:24px !important; }
            .header-badge { display:none !important; }
            .otp-code { font-size:30px !important; letter-spacing:.2em !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;color:#18233A;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Use this one-time code to confirm your NU Clark PRISM password change.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#EEF2F7;">
        <tr><td class="email-shell" align="center" style="padding:32px 16px;">
            <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" class="email-card" style="width:100%;max-width:560px;background:#FFFFFF;border:1px solid #DDE4EF;border-radius:18px;overflow:hidden;box-shadow:0 12px 32px rgba(21,39,78,.08);">
                <tr><td style="height:5px;background:#173778;border-right:100px solid #D7B348;font-size:0;line-height:0;">&nbsp;</td></tr>
                <tr><td class="email-header" style="padding:20px 30px;background:#F9FBFF;border-bottom:1px solid #E7ECF4;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
                        <td width="52" valign="middle"><img src="{{ isset($message) ? $message->embed(public_path('assets/logo.png')) : $logoUrl }}" width="42" height="42" alt="PRISM logo" style="display:block;width:42px;height:42px;border:0;"></td>
                        <td valign="middle"><div style="font-size:16px;font-weight:700;color:#142A68;">NU Clark</div><div style="margin-top:3px;font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#667795;">Student Discipline Office</div></td>
                        <td align="right" valign="middle"><span class="header-badge" style="display:inline-block;padding:6px 10px;border:1px solid #D7E1F2;border-radius:5px;background:#FFFFFF;color:#244E9C;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Password change</span></td>
                    </tr></table>
                </td></tr>
                <tr><td class="email-body" style="padding:30px;">
                    <div style="font-size:13px;line-height:1.6;color:#52617B;">Hello {{ $recipientName }},</div>
                    <h1 class="email-title" style="margin:7px 0 11px;font-size:27px;line-height:1.25;letter-spacing:-.02em;color:#14213D;">Confirm your new password</h1>
                    <p style="margin:0;font-size:14px;line-height:1.7;color:#4A5872;">Enter the one-time verification code below to confirm the password change on your PRISM account.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:22px;background:#F5F8FD;border:1px solid #DCE5F1;border-radius:10px;"><tr><td align="center" style="padding:22px 17px;">
                        <div style="font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#536581;">Verification code</div>
                        <div class="otp-code" style="margin-top:8px;font-size:36px;line-height:1.3;font-weight:700;letter-spacing:.26em;color:#244DA8;">{{ $otp }}</div>
                        <div style="margin-top:8px;font-size:12px;color:#667795;">Expires in {{ $expiryMinutes }} minutes</div>
                    </td></tr></table>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:16px;background:#FFF8E8;border:1px solid #F0DBA0;border-radius:8px;"><tr><td style="padding:12px 14px;font-size:11px;line-height:1.6;color:#6E5618;"><strong>Security notice:</strong> Never share this code. PRISM staff will never ask for it. If you did not request this password change, ignore this email and consider changing your password immediately.</td></tr></table>
                </td></tr>
                <tr><td class="email-footer" style="padding:18px 30px;background:#F5F7FB;border-top:1px solid #E4E9F1;color:#71809A;"><div style="font-size:10px;line-height:1.6;">This security notification was sent to {{ $email }}.</div><div style="margin-top:3px;font-size:10px;line-height:1.6;color:#8A97AC;">&copy; {{ $year }} NU Clark Student Discipline Office &middot; PRISM</div></td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
