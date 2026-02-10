<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm; }
        @font-face {
            font-family: 'JPFont';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $fontPath ?? "" }}') format('truetype');
        }
        body { font-family: 'JPFont', DejaVu Sans, sans-serif; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        .frame { border: 1px solid #ddd; padding: 8px; }
        svg, img { width: 100%; height: auto; display: block; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="frame">
        @if(!empty($pngDataUri))
            <img src="{{ $pngDataUri }}" alt="snapshot">
        @else
            {!! $svg !!}
        @endif
    </div>
</body>
</html>
