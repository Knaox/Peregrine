<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ $locale ?? 'en' }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $subject ?? ($appName ?? config('app.name', 'Peregrine')) }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, a { font-family: 'Segoe UI', Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        /* Client resets */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0; mso-table-rspace: 0; border-collapse: collapse; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        a { color: #e11d48; text-decoration: none; }

        /* Card + typography */
        .email-body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        h1, h2, h3 { margin: 0 0 16px 0; color: #111827; font-weight: 700; line-height: 1.3; }
        h1 { font-size: 22px; }
        h2 { font-size: 17px; color: #374151; }
        p { margin: 0 0 14px 0; color: #374151; font-size: 15px; line-height: 1.6; }
        ul { margin: 0 0 16px 0; padding-left: 18px; }
        li { color: #374151; font-size: 14px; line-height: 1.55; margin-bottom: 4px; }
        strong { color: #111827; }
        code { font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; color: #111827; }

        /* CTA button — bulletproof button wrapper is table+td, the <a> gets the padding for tap targets */
        .btn-primary a { display: inline-block; padding: 13px 32px; font-weight: 600; font-size: 15px; color: #ffffff !important; text-decoration: none; border-radius: 8px; }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; padding: 12px !important; }
            .email-card { border-radius: 0 !important; }
            .email-header { padding: 24px 20px !important; }
            .email-content { padding: 24px 20px !important; }
            .email-footer { padding: 16px 20px !important; }
            h1 { font-size: 20px !important; }
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            body, .email-bg { background-color: #0c0a14 !important; }
            .email-card { background-color: #16131e !important; border-color: #2a2535 !important; }
            h1, h2, h3, strong { color: #f8fafc !important; }
            p, li { color: #cbd5e1 !important; }
            .email-footer { color: #64748b !important; border-color: #2a2535 !important; }
            .email-footer-sep { border-color: #2a2535 !important; }
            code { background: #1e1a29 !important; color: #f1f5f9 !important; }
            .hr { border-color: #2a2535 !important; }
        }
    </style>
</head>
<body class="email-bg" style="margin:0; padding:0; background-color:#f4f4f8; color:#1a1a2e;">
    <!-- Preview text (hidden) -->
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:transparent;">
        {{ $previewText ?? ($appName ?? config('app.name', 'Peregrine')) }}
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="email-bg" style="background-color:#f4f4f8;">
        <tr>
            <td align="center" style="padding: 32px 12px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="email-container" style="width:600px; max-width:600px;">

                    {{-- Card --}}
                    <tr>
                        <td class="email-card" style="background-color:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.04);">

                            {{-- Header: gradient banner + logo --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td class="email-header" align="center" bgcolor="#111827" style="padding: 28px 32px; background: linear-gradient(135deg, #111827 0%, #312e81 100%); background-color:#111827;">
                                        @if(!empty($logoUrl))
                                            <img src="{{ $logoUrl }}" width="48" height="48" alt="{{ $appName ?? config('app.name', 'Peregrine') }}" style="display:block; width:48px; height:48px; border-radius:10px; background:#ffffff; padding:6px; box-sizing:border-box;">
                                        @endif
                                        <div style="margin-top:12px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; font-size:13px; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:rgba(255,255,255,0.85);">
                                            {{ $appName ?? config('app.name', 'Peregrine') }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Content --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td class="email-content email-body" style="padding: 36px 40px;">
                                        {!! is_string($slot) ? $slot : (string) $slot !!}
                                    </td>
                                </tr>
                            </table>

                            {{-- Footer --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td class="email-footer-sep" style="padding: 0 40px;">
                                        <div class="hr" style="border-top:1px solid #e5e7eb; font-size:0; line-height:0;">&nbsp;</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-footer" align="center" style="padding: 18px 32px 24px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; font-size:12px; color:#9ca3af;">
                                        <div style="color:#6b7280; font-weight:600; margin-bottom:4px;">{{ $appName ?? config('app.name', 'Peregrine') }}</div>
                                        @if(!empty($footerText))
                                            <div style="color:#9ca3af; line-height:1.6;">{{ $footerText }}</div>
                                        @endif
                                        @if(!empty($appUrl))
                                            <div style="margin-top:8px; color:#9ca3af;">
                                                <a href="{{ $appUrl }}" style="color:#9ca3af; text-decoration:none;">{{ preg_replace('#^https?://#', '', $appUrl) }}</a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- Tiny "sent by Peregrine" fine-print --}}
                    <tr>
                        <td align="center" style="padding: 16px 12px 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; font-size:11px; color:#9ca3af; line-height:1.5;">
                            You received this email because someone shared server access with you. If it wasn't you, you can safely ignore this message.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
