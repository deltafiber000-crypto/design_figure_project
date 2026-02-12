<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\DslEngine;
use App\Services\SnapshotPdfService;
use App\Services\SvgRenderer;
use Illuminate\Support\Facades\DB;

final class ChangeRequestController extends Controller
{
    public function show(int $id, SvgRenderer $renderer)
    {
        $req = DB::table('change_requests')->where('id', $id)->first();
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];
        $snapshot = [];
        $baseConfig = [];
        $baseDerived = [];
        $baseErrors = [];
        $baseSnapshot = [];
        $accountId = null;
        $templateVersionId = null;
        $snapshotForName = [];

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['config'] ?? null) ? $proposed['config'] : [];
            $baseConfig = is_array($proposed['base_config'] ?? null) ? $proposed['base_config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $accountId = (int)$session->account_id;
                $templateVersionId = (int)$session->template_version_id;
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

                if (!empty($baseConfig)) {
                    $baseEval = $dslEngine->evaluate($baseConfig, $dsl);
                    $baseDerived = is_array($baseEval['derived'] ?? null) ? $baseEval['derived'] : [];
                    $baseErrors = is_array($baseEval['errors'] ?? null) ? $baseEval['errors'] : [];
                }

                /** @var \App\Services\BomBuilder $bomBuilder */
                $bomBuilder = app(\App\Services\BomBuilder::class);
                $snapshotForName = [
                    'bom' => $bomBuilder->build($config, $derived, $dsl),
                    'template_version_id' => (int)$session->template_version_id,
                ];
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $baseSnapshot = is_array($proposed['base_snapshot'] ?? null) ? $proposed['base_snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
            $baseConfig = is_array($baseSnapshot['config'] ?? null) ? $baseSnapshot['config'] : [];
            $baseDerived = is_array($baseSnapshot['derived'] ?? null) ? $baseSnapshot['derived'] : [];
            $baseErrors = is_array($baseSnapshot['validation_errors'] ?? null) ? $baseSnapshot['validation_errors'] : [];
            $snapshotForName = $snapshot;
            $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);
            $quote = DB::table('quotes')->where('id', (int)$req->entity_id)->first();
            if ($quote) {
                $accountId = (int)$quote->account_id;
            }
        }

        $renderDerived = $this->augmentDerivedForRender($config, $derived);
        $svg = $renderer->render($config, $renderDerived, $errors);
        $baseSvg = '';
        if (!empty($baseConfig)) {
            $baseRenderDerived = $this->augmentDerivedForRender($baseConfig, $baseDerived);
            $baseSvg = $renderer->render($baseConfig, $baseRenderDerived, $baseErrors);
        }

        return view('admin.change-requests.show', [
            'req' => $req,
            'proposedJson' => json_encode($proposed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'configJson' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'derivedJson' => json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'errorsJson' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'snapshot' => $snapshot,
            'svg' => $svg,
            'baseSvg' => $baseSvg,
            'baseConfigJson' => json_encode($baseConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'baseDerivedJson' => json_encode($baseDerived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'baseErrorsJson' => json_encode($baseErrors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'baseSnapshot' => $baseSnapshot,
            'canApprove' => false,
            'snapshotPdfUrl' => route('ops.change-requests.snapshot.pdf', $req->id),
        ]);
    }

    public function downloadSnapshotPdf(int $id, SvgRenderer $renderer, SnapshotPdfService $pdfService)
    {
        $req = DB::table('change_requests')->where('id', $id)->first();
        if (!$req) abort(404);

        $proposed = $this->decodeJson($req->proposed_json) ?? [];
        $config = [];
        $derived = [];
        $errors = [];
        $accountId = null;
        $templateVersionId = null;
        $snapshotForName = [];

        if ($req->entity_type === 'configurator_session') {
            $config = is_array($proposed['config'] ?? null) ? $proposed['config'] : [];
            $session = DB::table('configurator_sessions')->where('id', (int)$req->entity_id)->first();
            if ($session) {
                $accountId = (int)$session->account_id;
                $templateVersionId = (int)$session->template_version_id;
                $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];
                /** @var DslEngine $dslEngine */
                $dslEngine = app(DslEngine::class);
                $eval = $dslEngine->evaluate($config, $dsl);
                $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
                $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

                /** @var \App\Services\BomBuilder $bomBuilder */
                $bomBuilder = app(\App\Services\BomBuilder::class);
                $snapshotForName = [
                    'bom' => $bomBuilder->build($config, $derived, $dsl),
                    'template_version_id' => (int)$session->template_version_id,
                ];
            }
        } elseif ($req->entity_type === 'quote') {
            $snapshot = is_array($proposed['snapshot'] ?? null) ? $proposed['snapshot'] : [];
            $config = is_array($snapshot['config'] ?? null) ? $snapshot['config'] : [];
            $derived = is_array($snapshot['derived'] ?? null) ? $snapshot['derived'] : [];
            $errors = is_array($snapshot['validation_errors'] ?? null) ? $snapshot['validation_errors'] : [];
            $snapshotForName = $snapshot;
            $templateVersionId = (int)($snapshot['template_version_id'] ?? 0);
            $quote = DB::table('quotes')->where('id', (int)$req->entity_id)->first();
            if ($quote) {
                $accountId = (int)$quote->account_id;
            }
        }

        $renderDerived = $this->augmentDerivedForRender($config, $derived);
        $svg = $renderer->render($config, $renderDerived, $errors);

        $filename = $pdfService->buildFilename(
            'request',
            $accountId,
            $templateVersionId,
            $snapshotForName,
            $config,
            $derived,
            (string)$req->updated_at
        );

        return $pdfService->download('編集承認リクエスト スナップショット', $svg, $filename);
    }

    private function augmentDerivedForRender(array $config, array $derived): array
    {
        if (empty($derived['skuNameByCode'])) {
            $derived['skuNameByCode'] = $this->buildSkuNameMap();
        }
        if (empty($derived['skuSvgByCode'])) {
            $derived['skuSvgByCode'] = $this->buildSkuSvgMap();
        }
        return $derived;
    }

    private function buildSkuNameMap(): array
    {
        return DB::table('skus')->pluck('name', 'sku_code')->all();
    }

    private function buildSkuSvgMap(): array
    {
        $dir = public_path('sku-svg');
        if (!is_dir($dir)) return [];

        $map = [];
        $files = glob($dir . '/*.svg') ?: [];
        foreach ($files as $path) {
            $code = basename($path, '.svg');
            if ($code === '') continue;
            $map[$code] = '/sku-svg/' . $code . '.svg';
        }
        return $map;
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

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
