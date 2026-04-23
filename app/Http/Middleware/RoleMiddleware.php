<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => "Non authentifié"
            ], 401);
        }

        $roleId = (int) $user->role_id;
        $roles = array_map('intval', $roles);

        // Cas négatif : role !:2
        if (str_starts_with((string) $roles[0], "!")) {
            $forbidden = array_map(
                fn($r) => (int) ltrim($r, '!'),
                $roles
            );

            if (in_array($roleId, $forbidden, true)) {
                return response()->json([
                    'message' => "Accès interdit"
                ], 403);
            }

            return $next($request);
        }

        // Cas positif classique : role:1,3
        if (! in_array($roleId, $roles, true)) {
            return response()->json([
                'message' => 'Accès interdit'
            ], 403);
        }

        return $next($request);
    }
}
