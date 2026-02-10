<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'アプリ') }}</title>
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; padding:24px;">
    <h1>{{ config('app.name', 'アプリ') }}</h1>
    <p>このページは簡易の既定ビューです。</p>
    <p><a href="{{ url('/') }}">トップへ戻る</a></p>
</body>
</html>
