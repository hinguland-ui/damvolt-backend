<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $code }}</title>
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: grid; place-items: center; background: #f5f5f7; color: #6e6e78; font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        main { text-align: center; padding: 24px; }
        b { display: block; font-size: 64px; font-weight: 600; color: #121014; letter-spacing: -.03em; line-height: 1; margin-bottom: 8px; }
    </style>
</head>
<body>
    <main>
        <b>{{ $code }}</b>
        {{ $text }}
    </main>
</body>
</html>
