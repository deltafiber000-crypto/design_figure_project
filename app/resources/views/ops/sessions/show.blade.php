@extends('admin.layout')

@section('content')
    <h1>セッション 詳細</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('ops.sessions.index') }}">一覧へ戻る</a>
    </div>

    @php
        $config = json_decode($configJson, true) ?? [];
        $derived = json_decode($derivedJson, true) ?? [];
        $errors = json_decode($errorsJson, true) ?? [];
        $snapshotView = [
            'config' => $config,
            'derived' => $derived,
            'validation_errors' => $errors,
            'bom' => [],
            'pricing' => [],
            'totals' => [],
            'template_version_id' => (int)($session->template_version_id ?? 0),
            'price_book_id' => null,
        ];
    @endphp
{{-- 
    <h3>承認リクエスト</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ステータス</th>
                <th>申請者</th>
                <th>承認者</th>
                <th>作成日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ $r->requested_by }}</td>
                    <td>{{ $r->approved_by }}</td>
                    <td>{{ $r->created_at }}</td>
                    <td><a href="{{ route('ops.change-requests.show', $r->id) }}">詳細</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">-</td>
                </tr>
            @endforelse
        </tbody>
    </table> --}}

    @include('partials.snapshot_bundle', [
        'panelTitle' => '構成セッションスナップショット',
        'pdfUrl' => route('ops.sessions.snapshot.pdf', $session->id),
        'summaryItems' => [
            ['label' => 'セッションID', 'value' => $session->id],
            ['label' => 'ステータス', 'value' => $session->status],
            ['label' => 'アカウント表示名', 'value' => $session->account_display_name ?? ''],
            ['label' => '顧客', 'value' => $session->customer_names ?? ''],
            ['label' => '承認リクエスト件数', 'value' => is_countable($requests) ? count($requests) : 0],
        ],
        'svg' => $svg,
        'snapshot' => $snapshotView,
        'config' => $config,
        'derived' => $derived,
        'errors' => $errors,
        'snapshotJson' => json_encode($snapshotView, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'configJson' => $configJson,
        'derivedJson' => $derivedJson,
        'errorsJson' => $errorsJson,
    ])
@endsection

