<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class AuditLogger
{
    public function log(int $actorUserId, string $action, string $entityType, ?int $entityId, mixed $before, mixed $after): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
