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
     * Determine if the incoming request is authenticated via an active, stateful Web Desk browser session.
     * Rejects any request authenticated via a Sanctum PersonalAccessToken (mobile token).
     */
    public static function isWebDeskSession(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $currentToken = $user->currentAccessToken();

        // 1. REJECT Personal Access Tokens (mobile tokens / API bearer tokens)
        if ($currentToken instanceof PersonalAccessToken) {
            return false;
        }

        // 2. REQUIRE genuine authenticated web desk session context
        return Auth::guard('web')->check() || ($request->hasSession() && $currentToken instanceof \Laravel\Sanctum\TransientToken);
    }

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
                'error_code' => 'MOBILE_RBAC_FORBIDDEN',
            ], 403);
        }

        // 2. REQUIRE genuine authenticated web desk session context
        if (!static::isWebDeskSession($request)) {
            return response()->json([
                'message' => 'Web-only RBAC administration: An active web desk browser session is required.',
                'error_code' => 'WEB_SESSION_REQUIRED',
            ], 403);
        }

        // 3. FUNCTIONAL RBAC AUTHORIZATION: Must be admin or have 'roles.manage' permission
        if (!$user->isAdmin() && !$user->hasPermission('roles.manage')) {
            return response()->json([
                'message' => 'Unauthorized access.',
                'error_code' => 'INSUFFICIENT_PERMISSIONS',
            ], 403);
        }

        return $next($request);
    }
}
