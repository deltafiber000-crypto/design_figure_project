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

        $roleSummary = DB::table('account_user as au')
            ->selectRaw("
                'admin:' || count(*) filter (where au.role = 'admin')
                || ' / sales:' || count(*) filter (where au.role = 'sales')
                || ' / customer:' || count(*) filter (where au.role = 'customer')
            ")
            ->whereColumn('au.account_id', 'a.id');

        $memberSummary = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->selectRaw("string_agg(u.name || ' (' || au.role || ')', ', ' order by u.id)")
            ->whereColumn('au.account_id', 'a.id');

        $query = DB::table('accounts as a')
            ->select('a.*')
            ->selectSub($roleSummary, 'role_summary')
            ->selectSub($memberSummary, 'member_summary');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('a.name', 'ilike', "%{$q}%")
                    ->orWhere('a.internal_name', 'ilike', "%{$q}%")
                    ->orWhere('a.assignee_name', 'ilike', "%{$q}%")
                    ->orWhere('a.memo', 'ilike', "%{$q}%");
            });
        }

        $accounts = $query->orderBy('a.id', 'desc')->limit(200)->get();

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'filters' => ['q' => $q],
        ]);
    }

    public function edit(int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $members = DB::table('account_user as au')
            ->join('users as u', 'u.id', '=', 'au.user_id')
            ->where('au.account_id', $id)
            ->select(
                'au.user_id',
                'au.role',
                'au.created_at as assigned_at',
                'u.name as user_name',
                'u.email as user_email'
            )
            ->orderByRaw("case au.role when 'admin' then 1 when 'sales' then 2 else 3 end")
            ->orderBy('u.id')
            ->get();

        $roleCounts = [
            'admin' => $members->where('role', 'admin')->count(),
            'sales' => $members->where('role', 'sales')->count(),
            'customer' => $members->where('role', 'customer')->count(),
        ];

        return view('admin.accounts.edit', [
            'account' => $account,
            'members' => $members,
            'roleCounts' => $roleCounts,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account) abort(404);

        $data = $request->validate([
            'account_type' => 'required|in:B2B,B2C',
            'internal_name' => 'nullable|string|max:255',
            'memo' => 'nullable|string|max:5000',
            'assignee_name' => 'nullable|string|max:255',
        ]);

        $internal = trim((string)($data['internal_name'] ?? ''));
        if ($internal === '') $internal = null;
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;
        $assigneeName = trim((string)($data['assignee_name'] ?? ''));
        if ($assigneeName === '') $assigneeName = null;

        $before = (array)$account;

        DB::table('accounts')->where('id', $id)->update([
            'account_type' => $data['account_type'],
            'internal_name' => $internal,
            'memo' => $memo,
            'assignee_name' => $assigneeName,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('accounts')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'ACCOUNT_UPDATED', 'account', $id, $before, $after);

        return redirect()->route('admin.accounts.edit', $id)->with('status', 'アカウントを更新しました');
    }
}
