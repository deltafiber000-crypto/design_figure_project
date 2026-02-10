<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class RoleHelper
{
    public static function currentRole(): ?string
    {
        $userId = auth()->id();
        if (!$userId) return null;

        $role = DB::table('account_user')
            ->where('user_id', $userId)
            ->orderBy('account_id')
            ->value('role');

        return $role ? (string)$role : null;
    }

    /**
     * @param array<int, string> $roles
     */
    public static function currentHasRole(array $roles): bool
    {
        $userId = auth()->id();
        if (!$userId) return false;
        if (empty($roles)) return true;

        return DB::table('account_user')
            ->where('user_id', $userId)
            ->whereIn('role', $roles)
            ->exists();
    }
}
