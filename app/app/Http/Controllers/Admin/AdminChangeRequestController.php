<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DslEngine;
use App\Services\SnapshotPdfService;
use Illuminate\Support\Facades\DB;

final class AdminChangeRequestController extends Controller
{
    public function index()
    {
        $requests = DB::table('change_requests')
            ->orderByRaw("status = 'PENDING' desc")
            ->orderBy('id', 'desc')
            ->limit(300)
            ->get();

        return view('admin.change-requests.index', ['requests' => $requests]);
    }

    public function show(int $id, \App\Services\SvgRenderer $renderer)
    {
        $req = DB::table('change_requests')->where('id', $id)->first();
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];
        $snapshot = [];

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['config'] ?? null) ? $proposed['config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
        }

        $svg = $renderer->render($config, $derived, $errors);

        return view('admin.change-requests.show', [
            'req' => $req,
            'proposedJson' => json_encode($proposed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'snapshot' => $snapshot,
            'svg' => $svg,
        ]);
    }

    public function downloadSnapshotPdf(int $id, \App\Services\SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = DB::table('change_requests')->where('id', $id)->first();
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['config'] ?? null) ? $proposed['config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
        }

        $svg = $renderer->render($config, $derived, $errors);

        return $pdfService->download(
            '編集承認リクエスト スナップショット',
            $svg,
            "change_request_{$id}_snapshot.pdf"
        );
    }

    public function approve(int $id)
    {
        $actorId = (int)auth()->id();

        DB::transaction(function () use ($id, $actorId) {
            $req = DB::table('change_requests')->where('id', $id)->lockForUpdate()->first();
            if (!$req) abort(404);
            if ($req->status !== 'PENDING') {
                return;
            }

            $proposed = $this->decodeJson($req->proposed_json) ?? [];

            if ($req->entity_type === 'configurator_session') {
                $this->applySessionChange((int)$req->entity_id, $proposed, $actorId);
            } elseif ($req->entity_type === 'quote') {
                $this->applyQuoteChange((int)$req->entity_id, $proposed, $actorId);
            }

            DB::table('change_requests')->where('id', $id)->update([
                'status' => 'APPROVED',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.change-requests.index')->with('status', '承認しました');
    }

    public function reject(int $id)
    {
        $actorId = (int)auth()->id();
        DB::table('change_requests')->where('id', $id)->update([
            'status' => 'REJECTED',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.change-requests.index')->with('status', '却下しました');
    }

    private function applySessionChange(int $sessionId, array $proposed, int $actorId): void
    {
        $session = DB::table('configurator_sessions')->where('id', $sessionId)->lockForUpdate()->first();
        if (!$session) return;

        $config = $proposed['config'] ?? null;
        if (!is_array($config)) return;

        $before = [
            'config' => $this->decodeJson($session->config) ?? [],
            'derived' => $this->decodeJson($session->derived) ?? [],
            'validation_errors' => $this->decodeJson($session->validation_errors) ?? [],
            'status' => $session->status,
        ];

        $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
        /** @var DslEngine $dslEngine */
        $dslEngine = app(DslEngine::class);
        $eval = $dslEngine->evaluate($config, $dsl);
        $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
        $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

        DB::table('configurator_sessions')->where('id', $sessionId)->update([
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'derived' => json_encode($derived, JSON_UNESCAPED_UNICODE),
            'validation_errors' => json_encode($errors, JSON_UNESCAPED_UNICODE),
            'status' => 'DRAFT',
            'updated_at' => now(),
        ]);

        $after = [
            'config' => $config,
            'derived' => $derived,
            'validation_errors' => $errors,
            'status' => 'DRAFT',
        ];

        $this->logAudit($actorId, 'CHANGE_REQUEST_APPROVED', 'configurator_session', $sessionId, $before, $after);
    }

    private function applyQuoteChange(int $quoteId, array $proposed, int $actorId): void
    {
        $quote = DB::table('quotes')->where('id', $quoteId)->lockForUpdate()->first();
        if (!$quote) return;

        $snapshot = $proposed['snapshot'] ?? null;
        if (!is_array($snapshot)) return;

        $before = [
            'snapshot' => $this->decodeJson($quote->snapshot) ?? [],
            'subtotal' => (float)$quote->subtotal,
            'tax_total' => (float)$quote->tax_total,
            'total' => (float)$quote->total,
        ];

        $totals = $snapshot['totals'] ?? [];
        $subtotal = isset($totals['subtotal']) ? (float)$totals['subtotal'] : (float)$quote->subtotal;
        $tax = isset($totals['tax']) ? (float)$totals['tax'] : (float)$quote->tax_total;
        $total = isset($totals['total']) ? (float)$totals['total'] : (float)$quote->total;

        DB::table('quotes')->where('id', $quoteId)->update([
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => $total,
            'updated_at' => now(),
        ]);

        $this->replaceQuoteItems($quoteId, $snapshot['bom'] ?? [], $snapshot['pricing'] ?? []);

        $after = [
            'snapshot' => $snapshot,
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => $total,
        ];

        $this->logAudit($actorId, 'CHANGE_REQUEST_APPROVED', 'quote', $quoteId, $before, $after);
    }

    private function replaceQuoteItems(int $quoteId, array $bom, array $pricingItems): void
    {
        DB::table('quote_items')->where('quote_id', $quoteId)->delete();

        $pricingBySort = [];
        foreach ($pricingItems as $pi) {
            if (!is_array($pi)) continue;
            $pricingBySort[(int)($pi['sort_order'] ?? 0)] = $pi;
        }

        $skuCodes = array_values(array_unique(array_filter(array_map(
            fn ($r) => is_array($r) ? ($r['sku_code'] ?? null) : null,
            $bom
        ))));

        $skuIdByCode = [];
        if (!empty($skuCodes)) {
            $skuIdByCode = DB::table('skus')
                ->whereIn('sku_code', $skuCodes)
                ->pluck('id', 'sku_code')
                ->all();
        }

        $rows = [];
        foreach ($bom as $row) {
            if (!is_array($row)) continue;
            $skuCode = (string)($row['sku_code'] ?? '');
            if ($skuCode === '') continue;
            $skuId = $skuIdByCode[$skuCode] ?? null;
            if (!$skuId) continue;

            $sort = (int)($row['sort_order'] ?? 0);
            $pricing = $pricingBySort[$sort] ?? null;

            $qty = $this->asNumber($row['quantity'] ?? 1);
            $unitPrice = $this->asNumber($pricing['unit_price'] ?? 0);
            $lineTotal = $this->asNumber($pricing['line_total'] ?? ($unitPrice * $qty));

            $rows[] = [
                'quote_id' => $quoteId,
                'sku_id' => $skuId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'options' => json_encode($row['options'] ?? [], JSON_UNESCAPED_UNICODE),
                'source_path' => $row['source_path'] ?? null,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('quote_items')->insert($rows);
        }
    }

    private function loadTemplateDsl(int $templateVersionId): ?array
    {
        $raw = DB::table('product_template_versions')
            ->where('id', $templateVersionId)
            ->value('dsl_json');
        if ($raw === null) return null;

        $dsl = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($dsl)) return null;

        return $dsl;
    }

    private function logAudit(int $actorUserId, string $action, string $entityType, int $entityId, array $before, array $after): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function asNumber(mixed $v): float
    {
        return is_numeric($v) ? (float)$v : 0.0;
    }
}
