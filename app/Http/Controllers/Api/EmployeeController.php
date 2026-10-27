<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $now = Carbon::now();
        $employees = User::orderBy('role', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($emp) use ($now) {
                $assignedLeadsCount = Lead::where('assigned_to', $emp->id)->count();
                $convertedCount = Lead::where('assigned_to', $emp->id)->where('status', 'converted')->count();
                $dueTodayCount = FollowUp::where('user_id', $emp->id)
                    ->where('status', 'pending')
                    ->whereDate('scheduled_at', Carbon::today())
                    ->count();
                $overdueCount = FollowUp::where('user_id', $emp->id)
                    ->where('status', 'pending')
                    ->where('scheduled_at', '<', $now)
                    ->count();

                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'email' => $emp->email,
                    'phone' => $emp->phone,
                    'role' => $emp->role,
                    'is_active' => $emp->is_active,
                    'created_at' => $emp->created_at->format('Y-m-d'),
                    'stats' => [
                        'assigned_leads' => $assignedLeadsCount,
                        'converted_leads' => $convertedCount,
                        'due_today' => $dueTodayCount,
                        'overdue' => $overdueCount,
                    ],
                ];
            });

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,employee',
        ]);

        $employee = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'notification_preferences' => [
                'whatsapp_enabled' => true,
                'email_enabled' => true,
                'in_app_enabled' => true,
                'phone_for_notifications' => $validated['phone'] ?? null,
            ],
        ]);

        return response()->json([
            'message' => 'Employee account created successfully',
            'employee' => $employee,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|required|in:admin,employee',
            'is_active' => 'sometimes|required|boolean',
        ]);

        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (!empty($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        if (!empty($validated['role'])) {
            $user->role = $validated['role'];
        }
        if (isset($validated['is_active'])) {
            if ($user->id === $currentUser->id && !$validated['is_active']) {
                return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
            }
            $user->is_active = $validated['is_active'];
        }

        $user->save();

        return response()->json([
            'message' => 'Employee updated successfully',
            'employee' => $user,
        ]);
    }

    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'You cannot change the active status of your own account.'], 422);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => "Account " . ($user->is_active ? "activated" : "deactivated") . " successfully.",
            'is_active' => $user->is_active,
        ]);
    }
}
