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

        $query = DB::table('audit_logs');
        if ($actor !== '') {
            $query->where('actor_user_id', (int)$actor);
        }
        if ($action !== '') {
            $query->where('action', $action);
        }
        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }
        if ($month !== '') {
            $query->whereRaw("to_char(created_at, 'YYYY-MM') = ?", [$month]);
        }

        $logs = $query->orderBy('id', 'desc')->limit(300)->get();

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
