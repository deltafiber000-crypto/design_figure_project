@extends('admin.layout')

@section('content')
    <h1>構成セッション（参照）</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>アカウントID</th>
                <th>顧客名</th>
                <th>テンプレート版ID</th>
                <th>ステータス</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($sessions as $s)
                <tr>
                    <td>{{ $s->id }}</td>
                    <td>{{ $s->account_id }}</td>
                    <td>{{ $s->customer_names ?? '' }}</td>
                    <td>{{ $s->template_version_id }}</td>
                    <td>{{ $s->status }}</td>
                    <td>{{ $s->updated_at }}</td>
                    <td><a href="{{ route('ops.sessions.show', $s->id) }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
