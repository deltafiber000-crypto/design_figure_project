<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminTemplateVersionController extends Controller
{
    public function store(Request $request, int $templateId)
    {
        $template = DB::table('product_templates')->where('id', $templateId)->first();
        if (!$template) abort(404);

        $data = $request->validate([
            'version' => 'required|integer|min:1',
            'dsl_version' => 'required|string|max:255',
            'dsl_json' => 'required|string',
            'memo' => 'nullable|string|max:5000',
        ]);

        $exists = DB::table('product_template_versions')
            ->where('template_id', $templateId)
            ->where('version', $data['version'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['version' => '同じversionが既に存在します'])->withInput();
        }

        $decoded = json_decode($data['dsl_json'], true);
        if (!is_array($decoded)) {
            return back()->withErrors(['dsl_json' => 'dsl_jsonはJSON形式で入力してください'])->withInput();
        }

        $active = $request->boolean('active', true);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $id = (int)DB::table('product_template_versions')->insertGetId([
            'template_id' => $templateId,
            'version' => $data['version'],
            'dsl_version' => $data['dsl_version'],
            'dsl_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            'active' => $active,
            'memo' => $memo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('product_template_versions')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'TEMPLATE_VERSION_CREATED', 'product_template_version', $id, null, $after);

        return redirect()->route('admin.templates.edit', $templateId)->with('status', 'テンプレのversionを追加しました');
    }

    public function edit(int $templateId, int $versionId)
    {
        $template = DB::table('product_templates')->where('id', $templateId)->first();
        if (!$template) abort(404);

        $version = DB::table('product_template_versions')
            ->where('id', $versionId)
            ->where('template_id', $templateId)
            ->first();
        if (!$version) abort(404);

        $dsl = $version->dsl_json ?? '';
        if (is_array($dsl)) {
            $dsl = json_encode($dsl, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return view('admin.templates.versions.edit', [
            'template' => $template,
            'version' => $version,
            'dslJson' => (string)$dsl,
        ]);
    }

    public function update(Request $request, int $templateId, int $versionId)
    {
        $version = DB::table('product_template_versions')
            ->where('id', $versionId)
            ->where('template_id', $templateId)
            ->first();
        if (!$version) abort(404);

        $data = $request->validate([
            'version' => 'required|integer|min:1',
            'dsl_version' => 'required|string|max:255',
            'dsl_json' => 'required|string',
            'memo' => 'nullable|string|max:5000',
        ]);

        $exists = DB::table('product_template_versions')
            ->where('template_id', $templateId)
            ->where('version', $data['version'])
            ->where('id', '!=', $versionId)
            ->exists();
        if ($exists) {
            return back()->withErrors(['version' => '同じversionが既に存在します'])->withInput();
        }

        $decoded = json_decode($data['dsl_json'], true);
        if (!is_array($decoded)) {
            return back()->withErrors(['dsl_json' => 'dsl_jsonはJSON形式で入力してください'])->withInput();
        }

        $active = $request->boolean('active', false);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $before = (array)$version;
        DB::table('product_template_versions')->where('id', $versionId)->update([
            'version' => $data['version'],
            'dsl_version' => $data['dsl_version'],
            'dsl_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            'active' => $active,
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('product_template_versions')->where('id', $versionId)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'TEMPLATE_VERSION_UPDATED', 'product_template_version', $versionId, $before, $after);

        return redirect()->route('admin.templates.edit', $templateId)->with('status', 'テンプレversionを更新しました');
    }
}
