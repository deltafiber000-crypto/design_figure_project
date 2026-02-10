@extends('admin.layout')

@section('content')
    <h1>編集承認リクエスト</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>対象種別</th>
                <th>対象ID</th>
                <th>ステータス</th>
                <th>申請者</th>
                <th>承認者</th>
                <th>作成日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($requests as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->entity_type }}</td>
                    <td>{{ $r->entity_id }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ $r->requested_by }}</td>
                    <td>{{ $r->approved_by }}</td>
                    <td>{{ $r->created_at }}</td>
                    <td><a href="{{ route('admin.change-requests.show', $r->id) }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
