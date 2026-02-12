@extends('admin.layout')

@section('content')
    <h1>価格表編集</h1>
    <form method="POST" action="{{ route('admin.price-books.update', $book->id) }}">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col">
                <label>名称</label>
                <input type="text" name="name" value="{{ old('name', $book->name) }}">
            </div>
            <div class="col">
                <label>バージョン</label>
                <input type="number" name="version" value="{{ old('version', $book->version) }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>通貨</label>
                <input type="text" name="currency" value="{{ old('currency', $book->currency) }}">
            </div>
            <div class="col">
                <label>有効開始日</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $book->valid_from) }}">
            </div>
            <div class="col">
                <label>有効終了日</label>
                <input type="date" name="valid_to" value="{{ old('valid_to', $book->valid_to) }}">
            </div>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">更新</button>
        </div>
    </form>

    <hr style="margin:16px 0;">

    <h2>明細</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>モデル</th>
                <th>単価</th>
                <th>mm単価</th>
                <th>式</th>
                <th>最小数量</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $it)
                <tr>
                    <td>{{ $it->id }}</td>
                    <td>{{ $it->sku_name }}</td>
                    <td>{{ $it->pricing_model }}</td>
                    <td>{{ $it->unit_price }}</td>
                    <td>{{ $it->price_per_mm }}</td>
                    <td><span class="muted">{{ $it->formula }}</span></td>
                    <td>{{ $it->min_qty }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.price-books.items.edit', [$book->id, $it->id]) }}">編集</a>
                        <form method="POST" action="{{ route('admin.price-books.items.destroy', [$book->id, $it->id]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">削除</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="margin-top:16px;">明細追加</h3>
    <form method="POST" action="{{ route('admin.price-books.items.store', $book->id) }}">
        @csrf
        <div class="row">
            <div class="col">
                <label>SKU名</label>
                <select name="sku_id">
                    @foreach($skus as $sku)
                        <option value="{{ $sku->id }}">{{ $sku->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>価格モデル</label>
                <select name="pricing_model">
                    <option value="FIXED">FIXED</option>
                    <option value="PER_MM">PER_MM</option>
                    <option value="FORMULA">FORMULA</option>
                </select>
            </div>
            <div class="col">
                <label>最小数量</label>
                <input type="number" step="0.001" name="min_qty" value="1">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>単価</label>
                <input type="number" step="0.01" name="unit_price">
            </div>
            <div class="col">
                <label>mm単価</label>
                <input type="number" step="0.0001" name="price_per_mm">
            </div>
            <div class="col">
                <label>式（JSON）</label>
                <input type="text" name="formula" placeholder='{"type":"linear","base":500,"k":1.2}'>
            </div>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">追加</button>
        </div>
    </form>
@endsection
