@extends('admin.layout')

@section('content')
    <h1>構成セッション 編集承認リクエスト</h1>
    <div class="muted">ID: {{ $session->id }}</div>

    <form method="POST" action="{{ route('ops.sessions.edit-request.store', $session->id) }}">
        @csrf
        <h3>簡易フォーム（JSONが空のときのみ反映）</h3>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>mfdCount</label>
                <input type="number" name="mfd_count" value="{{ old('mfd_count', $simple['mfdCount'] ?? '') }}">
            </div>
            <div class="col">
                <label>tubeCount</label>
                <input type="number" name="tube_count" value="{{ old('tube_count', $simple['tubeCount'] ?? '') }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>connectors.mode</label>
                <select name="connectors_mode">
                    <option value="">（未指定）</option>
                    @foreach(['none','left','right','both'] as $m)
                        <option value="{{ $m }}" @if(old('connectors_mode', $simple['connectors_mode'] ?? '') === $m) selected @endif>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>connectors.leftSkuCode</label>
                <input type="text" name="connectors_left" value="{{ old('connectors_left', $simple['connectors_left'] ?? '') }}">
            </div>
            <div class="col">
                <label>connectors.rightSkuCode</label>
                <input type="text" name="connectors_right" value="{{ old('connectors_right', $simple['connectors_right'] ?? '') }}">
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>config（JSON）</label>
            <div class="muted">JSONが入力されている場合はJSONが優先されます。</div>
            <textarea name="config_json">{{ old('config_json', $configJson) }}</textarea>
        </div>
        <div style="margin-top:8px;">
            <label>コメント</label>
            <textarea name="comment">{{ old('comment') }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">承認リクエスト送信</button>
            <a href="{{ route('ops.sessions.show', $session->id) }}">戻る</a>
        </div>
    </form>
@endsection
