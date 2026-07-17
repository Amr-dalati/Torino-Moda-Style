<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payment' }} — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 4rem auto; padding: 0 1rem; color: #1a1a1a; }
        p { line-height: 1.5; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Payment result received' }}</h1>
    <p>Payment result received. Please return to the app.</p>
    <p><small>Payment status is confirmed by the app or your order history — not by this page.</small></p>
</body>
</html>
