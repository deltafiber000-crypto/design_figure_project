<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminPriceBookController extends Controller
{
    public function index()
    {
        $books = DB::table('price_books')->orderBy('id', 'desc')->limit(200)->get();
        return view('admin.price-books.index', ['books' => $books]);
    }

    public function create()
    {
        return view('admin.price-books.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'required|integer|min:1',
            'currency' => 'required|string|max:3',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!empty($data['valid_from']) && !empty($data['valid_to']) && $data['valid_from'] > $data['valid_to']) {
            return back()->withErrors(['valid_to' => 'valid_toはvalid_from以降の日付にしてください'])->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $id = (int)DB::table('price_books')->insertGetId([
            'name' => $data['name'],
            'version' => $data['version'],
            'currency' => $data['currency'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_to' => $data['valid_to'] ?: null,
            'memo' => $memo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AuditLogger::class)->log((int)auth()->id(), 'PRICE_BOOK_CREATED', 'price_book', $id, null, [
            'id' => $id,
            'name' => $data['name'],
            'version' => $data['version'],
            'currency' => $data['currency'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_to' => $data['valid_to'] ?: null,
            'memo' => $memo,
        ]);

        return redirect()->route('admin.price-books.index')->with('status', '価格表を作成しました');
    }

    public function edit(int $id)
    {
        $book = DB::table('price_books')->where('id', $id)->first();
        if (!$book) abort(404);

        $items = DB::table('price_book_items as p')
            ->join('skus as s', 's.id', '=', 'p.sku_id')
            ->where('p.price_book_id', $id)
            ->orderBy('p.id')
            ->get([
                'p.id',
                'p.pricing_model',
                'p.unit_price',
                'p.price_per_mm',
                'p.formula',
                'p.min_qty',
                'p.memo',
                's.sku_code',
                's.name as sku_name',
            ]);

        $skus = DB::table('skus')->orderBy('sku_code')->get(['id', 'sku_code', 'name']);

        return view('admin.price-books.edit', [
            'book' => $book,
            'items' => $items,
            'skus' => $skus,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $book = DB::table('price_books')->where('id', $id)->first();
        if (!$book) abort(404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'required|integer|min:1',
            'currency' => 'required|string|max:3',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!empty($data['valid_from']) && !empty($data['valid_to']) && $data['valid_from'] > $data['valid_to']) {
            return back()->withErrors(['valid_to' => 'valid_toはvalid_from以降の日付にしてください'])->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $before = (array)$book;
        DB::table('price_books')->where('id', $id)->update([
            'name' => $data['name'],
            'version' => $data['version'],
            'currency' => $data['currency'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_to' => $data['valid_to'] ?: null,
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('price_books')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'PRICE_BOOK_UPDATED', 'price_book', $id, $before, $after);

        return redirect()->route('admin.price-books.edit', $id)->with('status', '価格表を更新しました');
    }
}
