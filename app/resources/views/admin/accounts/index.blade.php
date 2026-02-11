@extends('admin.layout')

@section('content')
    <h1>アカウント</h1>

    <form method="GET" action="{{ route('admin.accounts.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="アカウント名 / 社内呼称で検索">
            </div>
            <div class="col" style="flex:0 0 auto;">
                <button type="submit">検索</button>
            </div>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>表示名</th>
                <th>アカウント名</th>
                <th>社内呼称</th>
                <th>種別</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($accounts as $a)
                @php
                    $display = $a->internal_name ?: $a->name;
                @endphp
                <tr>
                    <td>{{ $a->id }}</td>
                    <td>{{ $display }}</td>
                    <td>{{ $a->name }}</td>
                    <td>{{ $a->internal_name ?? '-' }}</td>
                    <td>{{ $a->account_type }}</td>
                    <td>{{ $a->updated_at }}</td>
                    <td><a href="{{ route('admin.accounts.edit', $a->id) }}">編集</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
