<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Dedicated Web Desk session login.
     * Establishes a stateful Laravel browser session.
     * Does NOT issue a Personal Access Token.
     */
    public function webLogin(Request $request): JsonResponse
    {
        $user = $this->validateCredentials($request);

        // Establish stateful browser session
        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Web desk session authenticated successfully',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Dedicated Android mobile login.
     * Issues a scoped Sanctum Personal Access Token.
     * Strictly omits 'rbac:manage' and wildcard '*'.
     * Does NOT establish a web browser session.
     */
    public function mobileLogin(Request $request): JsonResponse
    {
        $user = $this->validateCredentials($request);

        $abilities = [
            'mobile:access',
            'leads:view',
            'leads:create',
            'leads:edit',
            'follow_ups:manage',
            'whatsapp:send',
            'mobile:sync',
            'notifications:view',
            'profile:manage',
        ];

        $token = $user->createToken('crm-mobile-session', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Mobile session authenticated successfully',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Standard/legacy login endpoint.
     * Issues a scoped personal access token without creating a web session.
     */
    public function login(Request $request): JsonResponse
    {
        $user = $this->validateCredentials($request);

        $abilities = [
            'mobile:access',
            'leads:view',
            'leads:create',
            'leads:edit',
            'follow_ups:manage',
            'whatsapp:send',
            'mobile:sync',
            'notifications:view',
            'profile:manage',
        ];

        $token = $user->createToken('crm-session', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Validate credentials and account status.
     */
    protected function validateCredentials(Request $request): User
    {
        $validated = $request->validate([
            'email' => 'nullable|string',
            'username' => 'nullable|string',
            'login' => 'nullable|string',
            'password' => 'required|string',
        ]);

        $identifier = trim($validated['login'] ?? $validated['username'] ?? $validated['email'] ?? '');
        if (empty($identifier)) {
            throw ValidationException::withMessages([
                'email' => ['Please provide your email address or username.'],
            ]);
        }

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (!$user->is_active) {
            abort(response()->json([
                'message' => 'Your account has been deactivated. Please contact your CRM administrator.',
            ], 403));
        }

        return $user;
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadCount = NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'user' => $this->formatUserResponse($user),
            'unread_notifications_count' => $unreadCount,
        ]);
    }

    protected function formatUserResponse(User $user): array
    {
        $role = $user->roleRelation;
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'role_id' => $user->role_id,
            'role_name' => $role?->name ?? $user->role,
            'role_display_name' => $role?->display_name ?? ucfirst($user->role),
            'team_id' => $user->team_id,
            'team_name' => $user->team?->name,
            'is_active' => (bool)$user->is_active,
            'must_change_password' => (bool)$user->must_change_password,
            'notification_preferences' => $user->notification_preferences,
            'permissions' => $user->getEffectivePermissions(),
            'visibility_scope' => $user->getEffectiveVisibilityScope(),
            'allowed_categories' => $user->getEffectiveAllowedCategories(),
        ];
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'notification_preferences' => 'nullable|array',
        ]);

        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->must_change_password = false;
        }
        if (!empty($validated['notification_preferences'])) {
            $user->notification_preferences = array_merge(
                $user->notification_preferences ?? [],
                $validated['notification_preferences']
            );
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Dedicated Web Desk logout.
     * Invalidates the stateful web session.
     */
    public function webLogout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Successfully logged out of web desk',
        ]);
    }

    /**
     * Universal logout.
     * Deletes personal access token if present, and invalidates web session if present.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } elseif ($bearer = $request->bearerToken()) {
            $pat = PersonalAccessToken::findToken($bearer);
            $pat?->delete();
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }
}
