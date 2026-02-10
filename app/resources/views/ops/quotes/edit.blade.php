@extends('admin.layout')

@section('content')
    <h1>見積編集（コンフィギュレータ）</h1>
    <div class="muted">見積ID: {{ $quote->id }}</div>
    <div style="margin:8px 0;">
        <a href="{{ route('ops.quotes.show', $quote->id) }}">詳細へ戻る</a>
    </div>

    @livewire('configurator', [
        'quoteEditId' => (int)$quote->id,
        'initialConfig' => $initialConfig,
        'initialTemplateVersionId' => (int)$templateVersionId,
    ])
@endsection
