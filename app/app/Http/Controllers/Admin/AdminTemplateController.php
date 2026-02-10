<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminTemplateController extends Controller
{
    public function index()
    {
        $templates = DB::table('product_templates')->orderBy('id', 'desc')->limit(200)->get();
        return view('admin.templates.index', ['templates' => $templates]);
    }

    public function create()
    {
        return view('admin.templates.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'template_code' => 'required|string|max:255|unique:product_templates,template_code',
            'name' => 'required|string|max:255',
        ]);

        $active = $request->boolean('active', true);

        $id = (int)DB::table('product_templates')->insertGetId([
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AuditLogger::class)->log((int)auth()->id(), 'TEMPLATE_CREATED', 'product_template', $id, null, [
            'id' => $id,
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
        ]);

        return redirect()->route('admin.templates.index')->with('status', 'テンプレを作成しました');
    }

    public function edit(int $id)
    {
        $template = DB::table('product_templates')->where('id', $id)->first();
        if (!$template) abort(404);

        $versions = DB::table('product_template_versions')
            ->where('template_id', $id)
            ->orderBy('version', 'desc')
            ->get();

        $nextVersion = (int)DB::table('product_template_versions')
            ->where('template_id', $id)
            ->max('version') + 1;

        return view('admin.templates.edit', [
            'template' => $template,
            'versions' => $versions,
            'nextVersion' => $nextVersion,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $template = DB::table('product_templates')->where('id', $id)->first();
        if (!$template) abort(404);

        $data = $request->validate([
            'template_code' => 'required|string|max:255|unique:product_templates,template_code,' . $id,
            'name' => 'required|string|max:255',
        ]);

        $active = $request->boolean('active', false);

        $before = (array)$template;
        DB::table('product_templates')->where('id', $id)->update([
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('product_templates')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'TEMPLATE_UPDATED', 'product_template', $id, $before, $after);

        return redirect()->route('admin.templates.edit', $id)->with('status', 'テンプレを更新しました');
    }
}
