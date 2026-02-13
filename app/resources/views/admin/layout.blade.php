<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '受注販売管理システム' }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding:16px; }
        nav a { margin-right: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea { width: 100%; }
        textarea { min-height: 140px; }
        .row { display: flex; gap: 12px; }
        .col { flex: 1; }
        .muted { color: #6b7280; }
        .actions { display: flex; gap: 8px; align-items: center; }
    </style>
</head>
<body>
    <nav>
        <a href="{{ route('admin.accounts.index') }}">アカウント</a>
        <a href="{{ route('ops.sessions.index') }}">仕様書セッション</a>
        <a href="{{ route('ops.quotes.index') }}">仕様書見積</a>
        <a href="{{ route('admin.skus.index') }}">パーツ(SKU)</a>
        <a href="{{ route('admin.price-books.index') }}">パーツ価格表</a>
        <a href="{{ route('admin.templates.index') }}">納品規則テンプレ(DSL)</a>
        <a href="{{ route('admin.change-requests.index') }}">編集承認リクエスト</a>
        <a href="{{ route('admin.audit-logs.index') }}">全作業監査ログ</a>
    </nav>

    @if(session('status'))
        <div style="margin:12px 0; padding:8px; background:#ecfeff; border:1px solid #06b6d4;">
            {{ session('status') }}
        </div>
    @endif

    @php
        $errorsBag = $errors ?? null;
    @endphp
    @if($errorsBag instanceof \Illuminate\Support\MessageBag && $errorsBag->any())
        <div style="margin:12px 0; padding:8px; background:#fee2e2; border:1px solid #dc2626;">
            <ul>
                @foreach($errorsBag->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</body>
</html>
