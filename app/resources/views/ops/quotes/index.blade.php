@extends('admin.layout')

@section('content')
    <h1>見積（参照）</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>アカウント</th>
                <th>顧客名</th>
                <th>ステータス</th>
                <th>通貨</th>
                <th>合計</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotes as $q)
                <tr>
                    <td>{{ $q->id }}</td>
                    <td>
                        <div>{{ $q->account_display_name ?? '' }}</div>
                        <div class="muted">ID: {{ $q->account_id }}</div>
                    </td>
                    <td>{{ $q->customer_names ?? '' }}</td>
                    <td>{{ $q->status }}</td>
                    <td>{{ $q->currency }}</td>
                    <td>{{ $q->total }}</td>
                    <td>{{ $q->updated_at }}</td>
                    <td><a href="{{ route('ops.quotes.show', $q->id) }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
