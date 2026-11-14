<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebDeskRbacSession
{
    /**
     * Handle an incoming request.
     *
     * Enforces the unforgeable architectural boundary:
     * RBAC administration is strictly restricted to verified, stateful web desk browser sessions.
     * Any authentication backed by a Sanctum Personal Access Token (mobile token) is rejected with HTTP 403,
     * regardless of user role, wildcard abilities, or client-supplied headers/parameters.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $currentToken = $user->currentAccessToken();

        // 1. REJECT Personal Access Tokens (mobile tokens / API bearer tokens)
        if ($currentToken instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Web-only RBAC administration: Personal access tokens cannot perform this action.',
            ], 403);
        }

        // 2. REQUIRE genuine authenticated web desk session context
        $hasWebSession = Auth::guard('web')->check() || ($request->hasSession() && $currentToken instanceof \Laravel\Sanctum\TransientToken);
        if (!$hasWebSession) {
            return response()->json([
                'message' => 'Web-only RBAC administration: An active web desk browser session is required.',
            ], 403);
        }

        // 3. FUNCTIONAL RBAC AUTHORIZATION: Must be admin or have 'roles.manage' permission
        if (!$user->isAdmin() && !$user->hasPermission('roles.manage')) {
            return response()->json([
                'message' => 'Unauthorized access.',
            ], 403);
        }

        return $next($request);
    }
}
