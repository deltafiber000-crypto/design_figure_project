@extends('admin.layout')

@section('content')
    <h1>SKU管理</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('admin.skus.create') }}">SKU作成</a>
    </div>

    <form method="GET" action="{{ route('admin.skus.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>検索</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col">
                <label>カテゴリ</label>
                <select name="category">
                    <option value="">すべて</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @if(($filters['category'] ?? '') === $cat) selected @endif>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>有効</label>
                <select name="active">
                    <option value="">すべて</option>
                    <option value="1" @if(($filters['active'] ?? '') === '1') selected @endif>有効</option>
                    <option value="0" @if(($filters['active'] ?? '') === '0') selected @endif>無効</option>
                </select>
            </div>
        </div>
        <div style="margin-top:8px;">
            <button type="submit">絞り込み</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>SKU</th>
                <th>名称</th>
                <th>カテゴリ</th>
                <th>有効</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($skus as $s)
                <tr>
                    <td>{{ $s->id }}</td>
                    <td>{{ $s->sku_code }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->category }}</td>
                    <td>{{ $s->active ? '有効' : '無効' }}</td>
                    <td>{{ $s->updated_at }}</td>
                    <td><a href="{{ route('admin.skus.edit', $s->id) }}">編集</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
