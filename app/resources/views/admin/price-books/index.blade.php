@extends('admin.layout')

@section('content')
    <h1>価格表管理</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('admin.price-books.create') }}">価格表作成</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>バージョン</th>
                <th>通貨</th>
                <th>有効期間</th>
                <th>メモ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($books as $b)
                <tr>
                    <td>{{ $b->id }}</td>
                    <td>{{ $b->name }}</td>
                    <td>{{ $b->version }}</td>
                    <td>{{ $b->currency }}</td>
                    <td>{{ $b->valid_from }} ~ {{ $b->valid_to }}</td>
                    <td>{{ $b->memo ?? '-' }}</td>
                    <td><a href="{{ route('admin.price-books.edit', $b->id) }}">編集</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
