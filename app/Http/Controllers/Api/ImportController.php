<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Services\LeadImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        protected LeadImportService $importService
    ) {}

    public function uploadAndPreview(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('leads.import') && !$user->hasPermission('leads.create')) {
            return response()->json(['message' => 'Only administrators or authorized users can import leads.'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        if (!in_array(strtolower($extension), ['csv', 'xlsx', 'xls', 'txt'])) {
            return response()->json([
                'message' => 'Invalid file format. Please upload a CSV (.csv) or Excel (.xlsx, .xls) file.',
            ], 422);
        }

        $storedPath = $file->storeAs('imports', uniqid('import_') . '.' . $extension);
        $fullPath = Storage::path($storedPath);

        try {
            $analysis = $this->importService->inspectFile($fullPath, $extension);

            return response()->json([
                'file_token' => $storedPath,
                'filename' => $file->getClientOriginalName(),
                'headers' => $analysis['headers'],
                'preview' => $analysis['preview'],
                'total_rows' => $analysis['total_rows'],
                'detected_mapping' => $analysis['detected_mapping'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to parse file: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function previewDuplicates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('leads.import') && !$user->hasPermission('leads.create')) {
            return response()->json(['message' => 'Only administrators or authorized users can preview import duplicates.'], 403);
        }

        $validated = $request->validate([
            'file_token' => 'required|string',
            'mapping' => 'required|array',
        ]);

        $fullPath = Storage::path($validated['file_token']);
        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'Uploaded file session has expired. Please re-upload the file.'], 404);
        }

        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

        try {
            $analysis = $this->importService->validateAndPreviewDuplicates(
                $fullPath,
                $extension,
                $validated['mapping'],
                $user
            );

            return response()->json($analysis);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Duplicate analysis failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function assignableUsers(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('leads.import') && !$user->hasPermission('leads.create')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $assignableIds = $user->getAssignableUserIds();
        $users = \App\Models\User::whereIn('id', $assignableIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'role_display' => $u->role === 'admin' ? 'Administrator' : ($u->role === 'team_lead' ? 'Team Lead' : 'Employee'),
                    'active_leads_count' => \App\Models\Lead::where('assigned_to', $u->id)
                        ->whereNull('deleted_at')
                        ->whereNotIn('status', ['converted', 'lost'])
                        ->count(),
                ];
            });

        return response()->json([
            'users' => $users,
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('leads.import') && !$user->hasPermission('leads.create')) {
            return response()->json(['message' => 'Only administrators or authorized users can import leads.'], 403);
        }

        $validated = $request->validate([
            'file_token' => 'required|string',
            'mapping' => 'required|array',
            'skip_duplicates' => 'nullable|boolean',
            'assignment_mode' => 'nullable|in:unassigned,specific,round_robin',
            'assigned_to' => 'nullable|exists:users,id',
            'selected_assignee_ids' => 'nullable|array',
            'selected_assignee_ids.*' => 'integer|exists:users,id',
        ]);

        if (($validated['assignment_mode'] ?? 'unassigned') === 'specific' && !empty($validated['assigned_to'])) {
            if (!$user->canAssignTo((int)$validated['assigned_to'])) {
                return response()->json(['message' => 'You are not authorized to assign leads to this user.'], 403);
            }
            $assignee = \App\Models\User::find($validated['assigned_to']);
            if (!$assignee || !$assignee->is_active) {
                return response()->json(['message' => 'Target assignee must be an active user.'], 422);
            }
        }

        if (($validated['assignment_mode'] ?? 'unassigned') === 'round_robin' && !empty($validated['selected_assignee_ids'])) {
            foreach ($validated['selected_assignee_ids'] as $uId) {
                if (!$user->canAssignTo((int)$uId)) {
                    return response()->json(['message' => "You are not authorized to assign leads to user #{$uId}."], 403);
                }
            }
        }

        $fullPath = Storage::path($validated['file_token']);
        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'Uploaded file session has expired. Please re-upload the file.'], 404);
        }

        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

        try {
            $result = $this->importService->processImport(
                $user,
                $fullPath,
                $extension,
                $validated['mapping'],
                [
                    'skip_duplicates' => $validated['skip_duplicates'] ?? true,
                    'assignment_mode' => $validated['assignment_mode'] ?? 'unassigned',
                    'assigned_to' => $validated['assigned_to'] ?? null,
                    'selected_assignee_ids' => $validated['selected_assignee_ids'] ?? [],
                ]
            );

            return response()->json([
                'message' => 'Import completed successfully',
                'report' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Import processing failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('leads.import')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $histories = ImportHistory::with(['user:id,name', 'assignedEmployee:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($histories);
    }

    public function downloadFailedReport(Request $request, ImportHistory $history): StreamedResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('leads.import')) {
            abort(403);
        }

        $failedRows = $history->summary['failed_rows'] ?? [];

        $filename = "failed_import_report_" . $history->id . ".csv";

        return response()->stream(function () use ($failedRows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['Row Number', 'Lead Name', 'Phone', 'Failure Reason']);

            foreach ($failedRows as $item) {
                fputcsv($handle, [
                    $item['row'] ?? '',
                    $item['name'] ?? '',
                    $item['phone'] ?? '',
                    $item['reason'] ?? '',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
