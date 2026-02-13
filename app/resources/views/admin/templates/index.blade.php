@extends('admin.layout')

@section('content')
    <h1>納品物ルールテンプレ(DSL)管理</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('admin.templates.create') }}">テンプレ作成</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>テンプレートコード</th>
                <th>名称</th>
                <th>有効</th>
                <th>メモ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($templates as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>{{ $t->template_code }}</td>
                    <td>{{ $t->name }}</td>
                    <td>{{ $t->active ? '有効' : '無効' }}</td>
                    <td>{{ $t->memo ?? '-' }}</td>
                    <td><a href="{{ route('admin.templates.edit', $t->id) }}">編集</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
