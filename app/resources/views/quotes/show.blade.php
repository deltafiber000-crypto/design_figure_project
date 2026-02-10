<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>見積 #{{ $quote->id ?? '' }}</title>
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; padding:16px;">
    <h1>見積 #{{ $quote->id ?? '' }}</h1>
    <div style="margin:8px 0;">
        <a href="{{ route('quotes.snapshot.pdf', $quote->id) }}">PDFダウンロード</a>
    </div>
    <div style="margin:12px 0 16px;">
        <div style="border:1px solid #ddd; padding:12px;">
            {!! $svg !!}
        </div>
    </div>

    <h2>合計</h2>
    <div>小計: {{ number_format((float)($totals['subtotal'] ?? 0), 2) }} {{ $quote->currency ?? '' }}</div>
    <div>税: {{ number_format((float)($totals['tax'] ?? 0), 2) }} {{ $quote->currency ?? '' }}</div>
    <div>合計: {{ number_format((float)($totals['total'] ?? 0), 2) }} {{ $quote->currency ?? '' }}</div>
</body>
</html>
