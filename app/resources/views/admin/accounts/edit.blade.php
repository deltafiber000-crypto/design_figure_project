@extends('admin.layout')

@section('content')
    <h1>アカウント編集</h1>

    <div class="row" style="margin:8px 0;">
        <div class="col">ID: {{ $account->id }}</div>
        <div class="col">種別: {{ $account->account_type }}</div>
    </div>
    <div style="margin:8px 0;">アカウント名: {{ $account->name }}</div>

    <form method="POST" action="{{ route('admin.accounts.update', $account->id) }}">
        @csrf
        @method('PUT')
        <table>
            <tbody>
                <tr>
                    <th>社内呼称（あだ名/メモ）</th>
                    <td>
                        <input type="text" name="internal_name" value="{{ old('internal_name', $account->internal_name) }}">
                        <div class="muted">未入力の場合は一覧でアカウント名を表示します。</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="actions" style="margin-top:12px;">
            <button type="submit">保存</button>
            <a href="{{ route('admin.accounts.index') }}">一覧へ戻る</a>
        </div>
    </form>
@endsection
