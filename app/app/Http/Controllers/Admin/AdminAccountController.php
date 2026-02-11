<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAccountController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->input('q', ''));

        $query = DB::table('accounts');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ilike', "%{$q}%")
                    ->orWhere('internal_name', 'ilike', "%{$q}%");
            });
        }

        $accounts = $query->orderBy('id', 'desc')->limit(200)->get();

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'filters' => ['q' => $q],
        ]);
    }

    public function edit(int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        return view('admin.accounts.edit', [
            'account' => $account,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $data = $request->validate([
            'internal_name' => 'nullable|string|max:255',
        ]);

        $internal = trim((string)($data['internal_name'] ?? ''));
        if ($internal === '') $internal = null;

        $before = (array)$account;

        DB::table('accounts')->where('id', $id)->update([
            'internal_name' => $internal,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('accounts')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'ACCOUNT_UPDATED', 'account', $id, $before, $after);

        return redirect()->route('admin.accounts.edit', $id)->with('status', 'アカウントを更新しました');
    }
}
