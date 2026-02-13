@extends('admin.layout')

@section('content')
    <h1>テンプレート版編集</h1>
    <div class="muted">テンプレート: {{ $template->template_code }}</div>

    <form method="POST" action="{{ route('admin.templates.versions.update', [$template->id, $version->id]) }}">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col">
                <label>バージョン</label>
                <input type="number" name="version" value="{{ old('version', $version->version) }}">
            </div>
            <div class="col">
                <label>DSLバージョン</label>
                <input type="text" name="dsl_version" value="{{ old('dsl_version', $version->dsl_version) }}">
            </div>
            <div class="col">
                <label>有効</label>
                <div>
                    <input type="checkbox" name="active" value="1" @if(old('active', $version->active ? '1' : '0') === '1') checked @endif> 有効
                </div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>dsl_json</label>
            <textarea name="dsl_json">{{ old('dsl_json', $dslJson) }}</textarea>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo', $version->memo) }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">更新</button>
            <a href="{{ route('admin.templates.edit', $template->id) }}">戻る</a>
        </div>
    </form>
@endsection
