<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\SnapshotPdfService;
use App\Services\SvgRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class QuoteController extends Controller
{
    public function index()
    {
        $customerNames = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'quotes.account_id')
            ->where('au.role', 'customer')
            ->selectRaw("string_agg(u.name, ', ')");

        $quotes = DB::table('quotes')
            ->select('quotes.*')
            ->selectSub($customerNames, 'customer_names')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return view('ops.quotes.index', ['quotes' => $quotes]);
    }

    public function show(int $id, SvgRenderer $renderer)
    {
        $customerNames = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->whereColumn('au.account_id', 'quotes.account_id')
            ->where('au.role', 'customer')
            ->selectRaw("string_agg(u.name, ', ')");

        $quote = DB::table('quotes')
            ->select('quotes.*')
            ->selectSub($customerNames, 'customer_names')
            ->where('id', $id)
            ->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $config = $snapshot['config'] ?? [];
        $derived = $snapshot['derived'] ?? [];
        $errors = $snapshot['validation_errors'] ?? [];

        $svg = $renderer->render($config, $derived, $errors);
        $totals = $snapshot['totals'] ?? [];

        $requests = DB::table('change_requests')
            ->where('entity_type', 'quote')
            ->where('entity_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        return view('ops.quotes.show', [
            'quote' => $quote,
            'snapshotJson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'snapshot' => $snapshot,
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'totals' => [
                'subtotal' => $totals['subtotal'] ?? null,
                'tax' => $totals['tax'] ?? null,
                'total' => $totals['total'] ?? null,
            ],
            'svg' => $svg,
            'requests' => $requests,
        ]);
    }

    public function edit(int $id)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
        $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);

        return view('ops.quotes.edit', [
            'quote' => $quote,
            'initialConfig' => $config,
            'templateVersionId' => $templateVersionId,
        ]);
    }

    public function editRequest(int $id)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $totals = $snapshot['totals'] ?? [];

        return view('ops.quotes.edit-request', [
            'quote' => $quote,
            'snapshotJson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'totals' => [
                'subtotal' => $totals['subtotal'] ?? null,
                'tax' => $totals['tax'] ?? null,
                'total' => $totals['total'] ?? null,
            ],
        ]);
    }

    public function storeEditRequest(Request $request, int $id)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $data = $request->validate([
            'snapshot_json' => 'nullable|string',
            'comment' => 'nullable|string',
            'subtotal' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'total' => 'nullable|numeric',
        ]);

        $decoded = null;
        if (!empty($data['snapshot_json'])) {
            $decoded = json_decode($data['snapshot_json'], true);
            if (!is_array($decoded)) {
                return back()->withErrors(['snapshot_json' => 'snapshotはJSON形式で入力してください'])->withInput();
            }
        } else {
            $decoded = $this->decodeJson($quote->snapshot) ?? [];
            $decoded['totals'] = is_array($decoded['totals'] ?? null) ? $decoded['totals'] : [];
            if (isset($data['subtotal'])) $decoded['totals']['subtotal'] = (float)$data['subtotal'];
            if (isset($data['tax'])) $decoded['totals']['tax'] = (float)$data['tax'];
            if (isset($data['total'])) $decoded['totals']['total'] = (float)$data['total'];
        }

        DB::table('change_requests')->insert([
            'entity_type' => 'quote',
            'entity_id' => $id,
            'proposed_json' => json_encode(['snapshot' => $decoded], JSON_UNESCAPED_UNICODE),
            'status' => 'PENDING',
            'requested_by' => (int)$request->user()->id,
            'comment' => $data['comment'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('ops.quotes.show', $id)->with('status', '承認リクエストを送信しました');
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function downloadSnapshotPdf(int $id, SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $quote = DB::table('quotes')->where('id', $id)->first();
        if (!$quote) abort(404);

        $snapshot = $this->decodeJson($quote->snapshot) ?? [];
        $config = $snapshot['config'] ?? [];
        $derived = $snapshot['derived'] ?? [];
        $errors = $snapshot['validation_errors'] ?? [];

        $svg = $renderer->render($config, $derived, $errors);

        return $pdfService->download(
            '見積 スナップショット',
            $svg,
            "quote_{$id}_snapshot.pdf"
        );
    }
}
