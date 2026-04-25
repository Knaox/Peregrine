@php
    // Self-contained wrapper for MailTemplateService-rendered bodies. Avoids
    // a dependency on the Laravel anonymous x-mail components so any admin
    // change in /admin/email-templates renders consistently without needing
    // us to publish vendor mail views.
    $footerText = $footerText ?? '';
    $brand = $brand ?? (config('app.name') ?: 'Peregrine');
    $logoUrl = $logoUrl ?? app(\App\Services\SettingsService::class)->getEmailLogoUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:32px 16px;background:#f4f4f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827;line-height:1.6;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
        <div style="padding:28px 32px;border-bottom:1px solid #e5e7eb;background:linear-gradient(135deg,#111827,#312e81);text-align:left;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                <tr>
                    <td style="vertical-align:middle;">
                        <img src="{{ $logoUrl }}" alt="{{ $brand }}" height="40" style="display:block;height:40px;width:auto;border:0;outline:none;text-decoration:none;">
                    </td>
                    <td style="vertical-align:middle;text-align:right;color:rgba(255,255,255,0.85);font-size:13px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">
                        {{ $brand }}
                    </td>
                </tr>
            </table>
        </div>
        <div style="padding:32px;font-size:15px;">
            {!! $bodyHtml !!}
        </div>
        @if($footerText !== '')
            <div style="padding:18px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;text-align:center;">
                {{ $footerText }}
            </div>
        @endif
    </div>
</body>
</html>
