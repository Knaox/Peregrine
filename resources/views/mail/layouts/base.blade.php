<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $subject ?? config('app.name', 'Peregrine') }}</title>
    <style>
        :root {
            color-scheme: light dark;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #1a1a2e;
            background-color: #f4f4f8;
        }
        @media (prefers-color-scheme: dark) {
            body {
                color: #e0e0e8;
                background-color: #0c0a14;
            }
            .email-wrapper {
                background-color: #16131e !important;
                border-color: #2a2535 !important;
            }
            .email-header {
                border-color: #2a2535 !important;
            }
            .email-footer {
                color: #5a5370 !important;
                border-color: #2a2535 !important;
            }
            .btn-primary {
                background-color: #e11d48 !important;
            }
        }
        .email-container {
            max-width: 580px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        .email-wrapper {
            background-color: #ffffff;
            border: 1px solid #e2e2ea;
            border-radius: 8px;
            overflow: hidden;
        }
        .email-header {
            padding: 24px 32px;
            border-bottom: 1px solid #e2e2ea;
            text-align: center;
        }
        .email-header img {
            max-height: 40px;
            width: auto;
        }
        .email-body {
            padding: 32px;
        }
        .email-body h1 {
            margin: 0 0 16px;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.3;
        }
        .email-body p {
            margin: 0 0 16px;
            font-size: 15px;
            line-height: 1.6;
        }
        .email-body ul {
            margin: 0 0 16px;
            padding-left: 20px;
        }
        .email-body li {
            margin-bottom: 4px;
            font-size: 14px;
        }
        .btn-primary {
            display: inline-block;
            padding: 12px 28px;
            background-color: #e11d48;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border-radius: 6px;
            margin: 8px 0;
        }
        .text-muted {
            color: #6b7280;
            font-size: 13px;
        }
        .email-footer {
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #e2e2ea;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-wrapper">
            <div class="email-header">
                @if(!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $appName ?? config('app.name', 'Peregrine') }}">
                @else
                    <strong style="font-size: 18px;">{{ $appName ?? config('app.name', 'Peregrine') }}</strong>
                @endif
            </div>

            <div class="email-body">
                {!! is_string($slot) ? $slot : '' !!}{{ !is_string($slot) ? $slot : '' }}
            </div>

            <div class="email-footer">
                {{ $appName ?? config('app.name', 'Peregrine') }}<br>
                <span class="text-muted">{{ $footerText ?? '' }}</span>
            </div>
        </div>
    </div>
</body>
</html>
