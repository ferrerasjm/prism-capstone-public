<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate your PRISM account</title>
    <style>
        @font-face {
            font-family: 'Poppins';
            src: url('{{ $fontRegularUrl }}') format('truetype');
            font-weight: 400;
            font-display: swap;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ $fontSemiBoldUrl }}') format('truetype');
            font-weight: 600;
            font-display: swap;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ $fontBoldUrl }}') format('truetype');
            font-weight: 700;
            font-display: swap;
        }
        body, table, td, a, div, p, h1, span {
            font-family: 'Poppins', Arial, Helvetica, sans-serif !important;
        }
        @media only screen and (max-width: 620px) {
            .email-shell { padding: 16px 10px !important; }
            .email-card { border-radius: 14px !important; }
            .email-header, .email-body, .email-footer {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
            .email-title { font-size: 24px !important; }
            .email-button { display: block !important; text-align: center !important; }
            .header-badge { display: none !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;color:#18233A;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Use this one-time link to activate your NU Clark PRISM account.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
           style="width:100%;background:#EEF2F7;">
        <tr>
            <td class="email-shell" align="center" style="padding:32px 16px;">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0"
                       class="email-card"
                       style="width:100%;max-width:560px;background:#FFFFFF;border:1px solid #DDE4EF;border-radius:18px;overflow:hidden;box-shadow:0 12px 32px rgba(21,39,78,.08);">
                    <tr>
                        <td style="height:5px;background:linear-gradient(90deg,#173778 0%,#173778 82%,#D7B348 82%,#D7B348 100%);font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="email-header" style="padding:20px 30px;background:#F9FBFF;border-bottom:1px solid #E7ECF4;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="52" valign="middle">
                                        <img src="{{ isset($message) ? $message->embed(public_path('assets/logo.png')) : $logoUrl }}"
                                             width="42" height="42" alt="PRISM logo"
                                             style="display:block;width:42px;height:42px;border:0;">
                                    </td>
                                    <td valign="middle">
                                        <div style="font-size:16px;font-weight:700;color:#142A68;">NU Clark</div>
                                        <div style="margin-top:3px;font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#667795;">
                                            Student Discipline Office
                                        </div>
                                    </td>
                                    <td align="right" valign="middle">
                                        <span class="header-badge" style="display:inline-block;padding:6px 10px;border:1px solid #D7E1F2;border-radius:5px;background:#FFFFFF;color:#244E9C;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
                                            Account activation
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-body" style="padding:30px;">
                            <div style="font-size:13px;line-height:1.6;color:#52617B;">Dear {{ $recipientName }},</div>
                            <h1 class="email-title" style="margin:7px 0 11px;font-size:27px;line-height:1.25;letter-spacing:-.02em;color:#14213D;">
                                Activate your PRISM account
                            </h1>
                            <p style="margin:0;font-size:14px;line-height:1.7;color:#4A5872;">
                                Your {{ $roleLabel }} account has been created. Use the secure one-time link below to create your password and begin the required account setup.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                   style="margin-top:20px;background:#F8FAFD;border:1px solid #D9E2EE;border-radius:8px;">
                                <tr>
                                    <td style="padding:15px 18px;">
                                        <div style="font-size:9px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;color:#71809A;">Registered email</div>
                                        <div style="margin-top:5px;font-size:14px;font-weight:700;color:#18233A;word-break:break-all;">{{ $email }}</div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;">
                                <tr>
                                    <td bgcolor="#244DA8" align="center" style="border-radius:9px;box-shadow:0 4px 10px rgba(36,77,168,.18);">
                                        <a class="email-button" href="{{ $setupUrl }}"
                                           style="display:block;padding:13px 20px;color:#FFFFFF;text-decoration:none;font-size:14px;font-weight:800;line-height:1.2;">
                                            Set Up My Account
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                   style="margin-top:22px;background:#F5F8FD;border-radius:10px;">
                                <tr>
                                    <td style="padding:15px 17px;">
                                        <div style="font-size:12px;font-weight:700;color:#22385F;">What happens next</div>
                                        <div style="margin-top:8px;font-size:12px;line-height:1.65;color:#56647B;">
                                            <strong style="color:#244DA8;">1.</strong>&nbsp; Open the one-time link and create your password.<br>
                                            @if($isStudent)
                                                <strong style="color:#244DA8;">2.</strong>&nbsp; Complete your student profile.<br>
                                                <strong style="color:#244DA8;">3.</strong>&nbsp; Review and acknowledge your information.
                                            @else
                                                <strong style="color:#244DA8;">2.</strong>&nbsp; Continue to your assigned PRISM access.
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                   style="margin-top:16px;background:#FFF8E8;border:1px solid #F0DBA0;border-radius:8px;">
                                <tr>
                                    <td style="padding:12px 14px;font-size:11px;line-height:1.6;color:#6E5618;">
                                        <strong>Security notice:</strong> This link expires {{ $expiresAt->format('F j, Y \a\t g:i A') }}, can only be used once, and becomes invalid when a replacement link is issued. Do not forward this email.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-footer" style="padding:18px 30px;background:#F5F7FB;border-top:1px solid #E4E9F1;color:#71809A;">
                            <div style="font-size:10px;line-height:1.6;">
                                This account notification was sent to {{ $email }}.
                            </div>
                            <div style="margin-top:3px;font-size:10px;line-height:1.6;color:#8A97AC;">
                                &copy; {{ $year }} NU Clark Student Discipline Office &middot; PRISM
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="max-width:520px;margin:14px auto 0;font-size:10px;line-height:1.55;color:#8995AA;text-align:center;">
                    If the button does not open, copy this address into your browser:<br>
                    <a href="{{ $setupUrl }}" style="color:#5C6F95;word-break:break-all;">{{ $setupUrl }}</a>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
