<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peregrine Bridge API — Developer Documentation</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
    <style>
        :root {
            color-scheme: light dark;
            --bg: #0d1117;
            --surface: #161b22;
            --border: #30363d;
            --text: #e6edf3;
            --text-muted: #7d8590;
            --link: #58a6ff;
            --code-bg: #161b22;
            --code-text: #e6edf3;
            --table-header: #21262d;
            --accent: #f78166;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg: #ffffff;
                --surface: #f6f8fa;
                --border: #d0d7de;
                --text: #1f2328;
                --text-muted: #656d76;
                --link: #0969da;
                --code-bg: #f6f8fa;
                --code-text: #1f2328;
                --table-header: #f6f8fa;
            }
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: var(--text);
            background: var(--bg);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 920px;
            margin: 0 auto;
            padding: 3rem 2rem 6rem;
        }
        h1, h2, h3, h4 {
            font-weight: 600;
            line-height: 1.25;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.3rem;
        }
        h1 { font-size: 2.2rem; border-bottom: 1px solid var(--border); margin-top: 0; }
        h2 { font-size: 1.6rem; border-bottom: 1px solid var(--border); }
        h3 { font-size: 1.25rem; }
        h4 { font-size: 1rem; color: var(--text-muted); }
        p { margin: 0.8rem 0; }
        a { color: var(--link); text-decoration: none; }
        a:hover { text-decoration: underline; }
        code {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.875em;
            padding: 0.2em 0.4em;
            background: var(--code-bg);
            border-radius: 6px;
            color: var(--code-text);
        }
        pre {
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.85rem;
            line-height: 1.45;
        }
        pre code { padding: 0; background: transparent; border-radius: 0; }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 1rem 0;
            font-size: 0.92rem;
        }
        th, td {
            border: 1px solid var(--border);
            padding: 0.55rem 0.85rem;
            text-align: left;
            vertical-align: top;
        }
        th { background: var(--table-header); font-weight: 600; }
        ul, ol { padding-left: 1.5rem; }
        li { margin: 0.3rem 0; }
        blockquote {
            border-left: 4px solid var(--accent);
            margin: 1rem 0;
            padding: 0.3rem 1rem;
            color: var(--text-muted);
            background: var(--surface);
            border-radius: 0 6px 6px 0;
        }
        hr { border: none; border-top: 1px solid var(--border); margin: 2.5rem 0; }
        .breadcrumb {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }
        .breadcrumb a { color: var(--text-muted); }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="/">← Back to Peregrine</a>
        </div>
        {!! $content !!}
    </div>
</body>
</html>
