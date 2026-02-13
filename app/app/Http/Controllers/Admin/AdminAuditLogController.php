<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $actor = (string)$request->input('actor_user_id', '');
        $action = (string)$request->input('action', '');
        $entityType = (string)$request->input('entity_type', '');
        $month = (string)$request->input('month', '');

        $query = DB::table('audit_logs as al')
            ->leftJoin('users as actor', 'actor.id', '=', 'al.actor_user_id')
            ->select('al.*')
            ->addSelect('actor.email as actor_email')
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->join('users as u', 'u.id', '=', 'au.user_id')
                    ->whereColumn('au.user_id', 'al.actor_user_id')
                    ->selectRaw("coalesce(nullif(a.internal_name, ''), u.name)")
                    ->orderBy('au.account_id')
                    ->limit(1),
                'actor_account_display_name'
            )
            ->selectSub(
                DB::table('account_user as au')
                    ->join('accounts as a', 'a.id', '=', 'au.account_id')
                    ->whereColumn('au.user_id', 'al.actor_user_id')
                    ->select('a.assignee_name')
                    ->orderBy('au.account_id')
                    ->limit(1),
                'actor_assignee_name'
            );
        if ($actor !== '') {
            $query->where('al.actor_user_id', (int)$actor);
        }
        if ($action !== '') {
            $query->where('al.action', $action);
        }
        if ($entityType !== '') {
            $query->where('al.entity_type', $entityType);
        }
        if ($month !== '') {
            $query->whereRaw("to_char(al.created_at, 'YYYY-MM') = ?", [$month]);
        }

        $logs = $query->orderBy('al.id', 'desc')->limit(300)->get();

        $actorOptions = DB::table('audit_logs')
            ->select('actor_user_id')
            ->whereNotNull('actor_user_id')
            ->distinct()
            ->orderBy('actor_user_id')
            ->pluck('actor_user_id')
            ->all();

        $actionOptions = DB::table('audit_logs')
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        $entityTypeOptions = DB::table('audit_logs')
            ->select('entity_type')
            ->whereNotNull('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->all();

        $monthOptions = DB::table('audit_logs')
            ->selectRaw("to_char(created_at, 'YYYY-MM') as ym")
            ->distinct()
            ->orderBy('ym', 'desc')
            ->pluck('ym')
            ->all();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => [
                'actor_user_id' => $actor,
                'action' => $action,
                'entity_type' => $entityType,
                'month' => $month,
            ],
            'actorOptions' => $actorOptions,
            'actionOptions' => $actionOptions,
            'entityTypeOptions' => $entityTypeOptions,
            'monthOptions' => $monthOptions,
        ]);
    }
}
