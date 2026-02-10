<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountRole
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $hasRole = DB::table('account_user')
            ->where('user_id', $user->id)
            ->whereIn('role', $roles)
            ->exists();

        if (!$hasRole) {
            abort(403);
        }

        return $next($request);
    }
}
