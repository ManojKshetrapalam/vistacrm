<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Models\FollowUp;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\WhatsAppManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function getSettings(Request $request): JsonResponse
    {
        $sources = CrmSetting::get('lead_sources', [
            'Website',
            'WhatsApp Inbound',
            'LinkedIn',
            'Referral',
            'Cold Outreach',
            'Partner Vertical',
            'Exhibition / Event',
        ]);

        $services = CrmSetting::get('service_categories', [
            'IT Infrastructure & Hardware AMC',
            'Cloud & DevOps Solutions',
            'Enterprise Software Engineering',
            'Incubation & Strategic Investment',
            'Hospitality Management',
            'Retail & Commerce Solutions',
        ]);

        $companyInfo = CrmSetting::get('company_info', [
            'name' => 'Vistabound Integrated Solutions LLP',
            'tagline' => 'Building Businesses Beyond Boundaries',
            'website' => 'https://vistaboundintegrated.com/',
            'email' => 'vistaboundintegrated@gmail.com',
            'phone' => '+916361232225',
            'location' => 'RR Nagar, Bangalore',
        ]);

        $employees = User::where('is_active', true)
            ->select('id', 'name', 'email', 'phone', 'role')
            ->orderBy('name')
            ->get();

        $templates = WhatsappTemplate::orderBy('id', 'asc')->get();

        return response()->json([
            'lead_sources' => $sources,
            'service_categories' => $services,
            'company_info' => $companyInfo,
            'employees' => $employees,
            'templates' => $templates,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'lead_sources' => 'nullable|array',
            'service_categories' => 'nullable|array',
            'company_info' => 'nullable|array',
        ]);

        if (isset($validated['lead_sources'])) {
            CrmSetting::set('lead_sources', array_values(array_filter(array_map('trim', $validated['lead_sources']))));
        }

        if (isset($validated['service_categories'])) {
            CrmSetting::set('service_categories', array_values(array_filter(array_map('trim', $validated['service_categories']))));
        }

        if (isset($validated['company_info'])) {
            CrmSetting::set('company_info', $validated['company_info']);
        }

        return response()->json(['message' => 'CRM settings updated successfully.']);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'template_key' => 'required|string|max:100|unique:whatsapp_templates,template_key',
            'content' => 'required|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template = WhatsappTemplate::create([
            'title' => $validated['title'],
            'template_key' => $validated['template_key'],
            'content' => $validated['content'],
            'variables' => $validated['variables'] ?? ['lead_name', 'service_interest', 'employee_name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'WhatsApp template created successfully',
            'template' => $template,
        ], 201);
    }

    public function updateTemplate(Request $request, WhatsappTemplate $template): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_active' => 'sometimes|required|boolean',
            'variables' => 'nullable|array',
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'WhatsApp template updated successfully',
            'template' => $template,
        ]);
    }

    public function deleteTemplate(Request $request, WhatsappTemplate $template): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted successfully.']);
    }

    public function diagnostics(Request $request, WhatsAppManager $whatsAppManager): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $nowUtc = Carbon::now('UTC');
        $nowIst = Carbon::now('Asia/Kolkata');

        $totalFollowUps = FollowUp::count();
        $pendingFollowUps = FollowUp::where('status', 'pending')->count();
        $overdueFollowUps = FollowUp::where('status', 'pending')->where('scheduled_at', '<', $nowUtc)->count();
        $pendingRemindersDue = FollowUp::where('status', 'pending')
            ->where('reminder_status', 'pending')
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', $nowUtc)
            ->count();
        $overdueUnsentReminders = FollowUp::where('status', 'pending')
            ->where('reminder_status', 'pending')
            ->where('scheduled_at', '<', $nowUtc)
            ->count();

        $queueBacklog = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $whatsAppStatus = $whatsAppManager->getStatusSummary();

        $recentLogs = NotificationLog::where(function ($q) {
                $q->where('channel', 'like', '%whatsapp%')
                  ->orWhere('channel', 'in_app');
            })
            ->with(['user:id,name', 'followUp:id,lead_id,scheduled_at'])
            ->latest()
            ->take(15)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'follow_up_id' => $log->follow_up_id,
                    'user_name' => $log->user?->name ?? 'System',
                    'channel' => $log->channel,
                    'status' => $log->status,
                    'title' => $log->title,
                    'reminder_key' => $log->reminder_key,
                    'created_at_ist' => $log->created_at?->setTimezone('Asia/Kolkata')->format('d M Y, h:i:s A'),
                    'metadata' => $log->metadata,
                ];
            });

        return response()->json([
            'server_time' => [
                'utc' => $nowUtc->toIso8601String(),
                'ist' => $nowIst->format('d M Y, h:i:s A') . ' IST',
            ],
            'follow_ups' => [
                'total' => $totalFollowUps,
                'pending' => $pendingFollowUps,
                'overdue' => $overdueFollowUps,
                'pending_reminders_due' => $pendingRemindersDue,
                'overdue_unsent_reminders' => $overdueUnsentReminders,
            ],
            'queue' => [
                'pending_jobs' => $queueBacklog,
                'failed_jobs' => $failedJobs,
                'scheduler_interval' => '1 minute (* * * * *)',
            ],
            'whatsapp' => $whatsAppStatus,
            'recent_logs' => $recentLogs,
        ]);
    }
}
