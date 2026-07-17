<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 1.25rem;
            background: #fafafa;
            color: #1f2937;
        }
        main {
            max-width: 48rem;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        h1 { font-size: 1.5rem; margin-top: 0; }
        h2 { font-size: 1.1rem; margin-top: 1.5rem; }
        .meta { color: #6b7280; font-size: 0.9rem; }
        .lang-switch { margin-bottom: 1rem; font-size: 0.9rem; }
        .lang-switch a { margin-inline-end: 0.75rem; }
        .placeholder { background: #fff7ed; border: 1px dashed #fdba74; padding: 0.75rem; border-radius: 8px; }
    </style>
</head>
<body>
<main>
    <div class="lang-switch">
        <a href="?lang=en">English</a>
        <a href="?lang=ar">العربية</a>
    </div>
    <p class="meta">{{ $locale === 'ar' ? 'آخر تحديث' : 'Last updated' }}: {{ $lastUpdated }}</p>
    <h1>{{ $pageTitle }}</h1>
    @yield('content')
</main>
</body>
</html>
