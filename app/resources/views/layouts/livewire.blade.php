<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- CSRF(CSRF:改ざん防止) --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @livewireStyles
</head>
<body style="font-family: system-ui; padding:16px;">
    {{ $slot }}
    @livewireScripts
</body>
</html>
