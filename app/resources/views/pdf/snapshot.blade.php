<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @php
        $fontSrc = !empty($fontPath ?? null) && str_starts_with((string)$fontPath, '/')
            ? 'file://' . $fontPath
            : ($fontPath ?? '');
        $fontBoldSrc = !empty($fontBoldPath ?? null) && str_starts_with((string)$fontBoldPath, '/')
            ? 'file://' . $fontBoldPath
            : ($fontBoldPath ?? '');
    @endphp
    <style>
        @page { margin: 18mm; }
        @font-face {
            font-family: 'JPFont';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $fontSrc }}') format('truetype');
        }
        @if(!empty($fontBoldSrc))
        @font-face {
            font-family: 'JPFont';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $fontBoldSrc }}') format('truetype');
        }
        @endif
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
