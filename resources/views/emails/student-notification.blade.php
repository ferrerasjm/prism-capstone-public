<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $content['subject'] }}</title>
    <style>
        @font-face {
            font-family: 'Poppins';
            src: url('{{ $fontRegularUrl }}') format('truetype');
            font-style: normal;
            font-weight: 400;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ $fontMediumUrl }}') format('truetype');
            font-style: normal;
            font-weight: 500;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ $fontSemiBoldUrl }}') format('truetype');
            font-style: normal;
            font-weight: 600;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ $fontBoldUrl }}') format('truetype');
            font-style: normal;
            font-weight: 700;
        }
        body, table, td, a, div, p, h1, span {
            font-family: 'Poppins', 'Trebuchet MS', Arial, sans-serif !important;
        }
        @media only screen and (max-width: 620px) {
            .email-shell { padding: 12px 8px !important; }
            .email-card { border-radius: 12px !important; }
            .email-header, .email-body, .email-footer { padding-left: 22px !important; padding-right: 22px !important; }
            .email-title { font-size: 22px !important; line-height: 1.35 !important; }
            .email-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;color:#172033;font-family:'Poppins','Trebuchet MS',Arial,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $content['headline'] }} &mdash; open PRISM to review the details securely.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
           style="width:100%;background:#EEF2F7;font-family:'Poppins','Trebuchet MS',Arial,sans-serif;">
        <tr>
            <td class="email-shell" align="center" style="padding:32px 16px;">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0"
                       class="email-card"
                       style="width:100%;max-width:640px;background:#FFFFFF;border:1px solid #DDE4EE;border-radius:16px;overflow:hidden;box-shadow:0 12px 32px rgba(20,42,104,.08);">
                    <tr>
                        <td style="font-size:0;line-height:0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="78%" style="height:5px;background:#142A68;font-size:0;line-height:0;">&nbsp;</td>
                                    <td width="22%" style="height:5px;background:#E2BE56;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-header" style="padding:22px 34px;background:#FFFFFF;border-bottom:1px solid #E6EBF2;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="58" valign="middle">
                                        <img src="{{ isset($message) ? $message->embed(public_path('assets/logo.png')) : $logoUrl }}" width="44" height="44" alt="PRISM logo"
                                             style="display:block;width:44px;height:44px;border:0;">
                                    </td>
                                    <td valign="middle">
                                        <div style="font-size:17px;line-height:1.2;font-weight:700;color:#142A68;">NU Clark</div>
                                        <div style="margin-top:3px;font-size:9px;line-height:1.4;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#66758F;">Student Discipline Office</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-body" style="padding:34px 34px 32px;">
                            <div style="font-size:13px;line-height:1.6;color:#475569;">Hello, {{ $recipientName }}</div>
                            <h1 class="email-title" style="margin:10px 0 0;font-size:24px;line-height:1.35;letter-spacing:-.015em;font-weight:700;color:#112044;">
                                {{ $content['headline'] }}
                            </h1>

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.75;color:#475569;">
                                {{ $content['message'] }}
                            </p>

                            @if($content['deadline'])
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                       style="margin-top:20px;background:#F8FAFC;border:1px solid #E1E7F0;border-radius:8px;">
                                    <tr>
                                        <td style="padding:15px 18px;">
                                            <div style="font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#65738A;">
                                                {{ $content['deadlineLabel'] }}
                                            </div>
                                            <div style="margin-top:4px;font-size:18px;font-weight:700;color:#18233A;">
                                                {{ $content['deadline'] }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:20px 0 0;font-size:12px;line-height:1.65;color:#64748B;">
                                Sign in to PRISM to view the complete and official record, including any required next steps.
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:14px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td bgcolor="#244DA8" style="border-radius:7px;box-shadow:0 4px 10px rgba(36,77,168,.18);">
                                                    <a class="email-button" href="{{ $actionUrl }}"
                                                       style="display:inline-block;padding:12px 20px;color:#FFFFFF;text-decoration:none;font-size:13px;font-weight:600;line-height:1.2;">
                                                        {{ $actionLabel }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                   style="margin-top:22px;padding-top:16px;border-top:1px solid #E6EBF2;">
                                <tr>
                                    <td style="font-size:10px;line-height:1.6;color:#718096;">
                                        <span style="display:inline-block;margin-right:8px;padding:2px 5px;border-radius:3px;background:#FFF6D8;font-size:9px;font-weight:700;letter-spacing:.06em;color:#765A0B;vertical-align:baseline;">PRIVATE</span>
                                        <span style="vertical-align:baseline;">This email contains a summary only. Sign in to PRISM to view case details.</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-footer" style="padding:20px 34px;background:#F8FAFC;border-top:1px solid #E6EBF2;color:#65738A;">
                            <div style="font-size:10px;line-height:1.65;">
                                This service notification was sent to {{ $recipientEmail }}.
                            </div>
                            <div style="margin-top:4px;font-size:10px;line-height:1.65;color:#8A96A8;">
                                &copy; {{ $year }} NU Clark Student Discipline Office &middot; PRISM
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="max-width:560px;margin:14px auto 0;font-size:9px;line-height:1.6;color:#8A96A8;text-align:center;">
                    Button not working? Copy and paste this link into your browser:<br>
                    <a href="{{ $actionUrl }}" style="color:#667792;text-decoration:underline;word-break:break-all;">{{ $actionUrl }}</a>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
