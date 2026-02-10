@extends('admin.layout')

@section('content')
    <h1>編集承認リクエスト詳細</h1>
    <div class="row" style="margin:8px 0;">
        <div class="col">ID: {{ $req->id }}</div>
        <div class="col">対象: {{ $req->entity_type }} #{{ $req->entity_id }}</div>
        <div class="col">ステータス: {{ $req->status }}</div>
    </div>
    @if(!empty($req->comment))
        <div style="margin:8px 0;">コメント: {{ $req->comment }}</div>
    @endif

    @php
        $config = json_decode($configJson, true) ?? [];
        $derived = json_decode($derivedJson, true) ?? [];
        $errors = json_decode($errorsJson, true) ?? [];
        $sleeves = $config['sleeves'] ?? [];
        $fibers = $config['fibers'] ?? [];
        $tubes = $config['tubes'] ?? [];
        $connectors = $config['connectors'] ?? [];
    @endphp

    <div class="actions" style="margin-top:12px;">
        <h3 style="margin:0;">スナップショット</h3>
        <a href="{{ route('admin.change-requests.snapshot.pdf', $req->id) }}">PDFダウンロード</a>
    </div>
    <div style="border:1px solid #ddd; padding:12px; margin:12px 0;">
        {!! $svg !!}
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

    @if($req->entity_type === 'quote')
        @php
            $totals = $snapshot['totals'] ?? [];
            $bom = $snapshot['bom'] ?? [];
            $pricing = $snapshot['pricing'] ?? [];
        @endphp
        <div class="row" style="margin:8px 0;">
            <div class="col">小計: {{ $totals['subtotal'] ?? '' }}</div>
            <div class="col">税: {{ $totals['tax'] ?? '' }}</div>
            <div class="col">合計: {{ $totals['total'] ?? '' }}</div>
        </div>

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
    @endif

    <h3>承認結果</h3>
    <table>
        <thead>
            <tr>
                <th>ステータス</th>
                <th>承認者</th>
                <th>承認日時</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $req->status }}</td>
                <td>{{ $req->approved_by }}</td>
                <td>{{ $req->approved_at }}</td>
            </tr>
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
            @if($req->entity_type === 'quote')
                <tr><th>template_version_id</th><td>{{ $snapshot['template_version_id'] ?? '' }}</td></tr>
                <tr><th>price_book_id</th><td>{{ $snapshot['price_book_id'] ?? '' }}</td></tr>
            @endif
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

    <details style="margin-top:12px;">
        <summary>JSONデータ閲覧</summary>
        <h4>proposed_json</h4>
        <pre>{{ $proposedJson }}</pre>
        <h4>config</h4>
        <pre>{{ $configJson }}</pre>
        <h4>derived</h4>
        <pre>{{ $derivedJson }}</pre>
        <h4>validation_errors</h4>
        <pre>{{ $errorsJson }}</pre>
    </details>

    @if($req->status === 'PENDING')
        <div class="actions" style="margin-top:12px;">
            <form method="POST" action="{{ route('admin.change-requests.approve', $req->id) }}">
                @csrf
                <button type="submit">承認</button>
            </form>
            <form method="POST" action="{{ route('admin.change-requests.reject', $req->id) }}">
                @csrf
                <button type="submit">却下</button>
            </form>
        </div>
    @endif
@endsection
