@extends('admin.layout')

@section('content')
    <h1>見積 詳細</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('ops.quotes.edit', $quote->id) }}">コンフィギュレータで編集</a>
        <a href="{{ route('ops.quotes.index') }}">一覧へ戻る</a>
    </div>

    <div class="row" style="margin:8px 0;">
        <div class="col">ID: {{ $quote->id }}</div>
        <div class="col">アカウント（表示名）: {{ $quote->account_display_name ?? '' }}</div>
        <div class="col">顧客: {{ $quote->customer_names ?? '' }}</div>
        <div class="col">ステータス: {{ $quote->status }}</div>
    </div>
    <div class="row" style="margin:8px 0;">
        <div class="col">アカウント名: {{ $quote->account_name ?? '' }}</div>
        <div class="col">社内呼称: {{ $quote->account_internal_name ?? '-' }}</div>
        <div class="col">アカウントID: {{ $quote->account_id }}</div>
    </div>

    @php
        $config = $snapshot['config'] ?? [];
        $derived = $snapshot['derived'] ?? [];
        $errors = $snapshot['validation_errors'] ?? [];
        $bom = $snapshot['bom'] ?? [];
        $pricing = $snapshot['pricing'] ?? [];
        $sleeves = $config['sleeves'] ?? [];
        $fibers = $config['fibers'] ?? [];
        $tubes = $config['tubes'] ?? [];
        $connectors = $config['connectors'] ?? [];
    @endphp

    <div class="actions" style="margin-top:12px;">
        <h3 style="margin:0;">スナップショット</h3>
        <a href="{{ route('ops.quotes.snapshot.pdf', $quote->id) }}">PDFダウンロード</a>
    </div>
    <div style="border:1px solid #ddd; padding:12px; margin:12px 0;">
        {!! $svg !!}
    </div>
    <div class="row" style="margin:8px 0;">
        <div class="col">小計: {{ $totals['subtotal'] ?? '' }}</div>
        <div class="col">税: {{ $totals['tax'] ?? '' }}</div>
        <div class="col">合計: {{ $totals['total'] ?? '' }}</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>導出キー</th>
                <th>値</th>
            </tr>
        </thead>
        <tbody>
            @foreach($derived as $k => $v)
                <tr>
                    <td>{{ $k }}</td>
                    <td>
                        @if(is_array($v))
                            <details>
                                <summary>配列（{{ count($v) }}）</summary>
                                <pre>{{ print_r($v, true) }}</pre>
                            </details>
                        @else
                            {{ $v }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>承認リクエスト</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ステータス</th>
                <th>申請者</th>
                <th>承認者</th>
                <th>作成日</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requests as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ $r->requested_by }}</td>
                    <td>{{ $r->approved_by }}</td>
                    <td>{{ $r->created_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>検証エラー</h3>
    <table>
        <thead>
            <tr>
                <th>パス</th>
                <th>メッセージ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($errors as $e)
                <tr>
                    <td>{{ $e['path'] ?? '' }}</td>
                    <td>{{ $e['message'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>構成（概要）</h3>
    <table>
        <tbody>
            <tr><th>template_version_id</th><td>{{ $snapshot['template_version_id'] ?? '' }}</td></tr>
            <tr><th>price_book_id</th><td>{{ $snapshot['price_book_id'] ?? '' }}</td></tr>
            <tr><th>mfdCount</th><td>{{ $config['mfdCount'] ?? '' }}</td></tr>
            <tr><th>tubeCount</th><td>{{ $config['tubeCount'] ?? '' }}</td></tr>
            <tr><th>connectors.mode</th><td>{{ $connectors['mode'] ?? '' }}</td></tr>
            <tr><th>connectors.leftSkuCode</th><td>{{ $connectors['leftSkuCode'] ?? '' }}</td></tr>
            <tr><th>connectors.rightSkuCode</th><td>{{ $connectors['rightSkuCode'] ?? '' }}</td></tr>
        </tbody>
    </table>

    <h3>スリーブ</h3>
    <table>
        <thead><tr><th>番号</th><th>skuCode</th></tr></thead>
        <tbody>
            @foreach($sleeves as $i => $s)
                <tr><td>{{ $i }}</td><td>{{ $s['skuCode'] ?? '' }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h3>ファイバー</h3>
    <table>
        <thead><tr><th>番号</th><th>skuCode</th><th>lengthMm</th><th>toleranceMm</th></tr></thead>
        <tbody>
            @foreach($fibers as $i => $f)
                <tr>
                    <td>{{ $i }}</td>
                    <td>{{ $f['skuCode'] ?? '' }}</td>
                    <td>{{ $f['lengthMm'] ?? '' }}</td>
                    <td>{{ $f['toleranceMm'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>チューブ</h3>
    <table>
        <thead><tr><th>番号</th><th>skuCode</th><th>targetFiberIndex</th><th>startOffsetMm</th><th>lengthMm</th><th>toleranceMm</th></tr></thead>
        <tbody>
            @foreach($tubes as $i => $t)
                <tr>
                    <td>{{ $i }}</td>
                    <td>{{ $t['skuCode'] ?? '' }}</td>
                    <td>{{ $t['targetFiberIndex'] ?? '' }}</td>
                    <td>{{ $t['startOffsetMm'] ?? '' }}</td>
                    <td>{{ $t['lengthMm'] ?? '' }}</td>
                    <td>{{ $t['toleranceMm'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>BOM</h3>
    <table>
        <thead>
            <tr><th>sku_code</th><th>quantity</th><th>source_path</th><th>sort_order</th></tr>
        </thead>
        <tbody>
            @foreach($bom as $b)
                <tr>
                    <td>{{ $b['sku_code'] ?? '' }}</td>
                    <td>{{ $b['quantity'] ?? '' }}</td>
                    <td>{{ $b['source_path'] ?? '' }}</td>
                    <td>{{ $b['sort_order'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>価格内訳</h3>
    <table>
        <thead>
            <tr><th>sku_code</th><th>quantity</th><th>unit_price</th><th>line_total</th><th>pricing_model</th></tr>
        </thead>
        <tbody>
            @foreach($pricing as $p)
                <tr>
                    <td>{{ $p['sku_code'] ?? '' }}</td>
                    <td>{{ $p['quantity'] ?? '' }}</td>
                    <td>{{ $p['unit_price'] ?? '' }}</td>
                    <td>{{ $p['line_total'] ?? '' }}</td>
                    <td>{{ $p['pricing_model'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <details style="margin-top:12px;">
        <summary>JSONデータ閲覧</summary>
        <h4>snapshot</h4>
        <pre>{{ $snapshotJson }}</pre>
        <h4>config</h4>
        <pre>{{ $configJson }}</pre>
        <h4>derived</h4>
        <pre>{{ $derivedJson }}</pre>
        <h4>validation_errors</h4>
        <pre>{{ $errorsJson }}</pre>
    </details>
@endsection
