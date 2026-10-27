<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\NotificationLog;
use App\Models\WhatsappTemplate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function getTemplates(): JsonResponse
    {
        $templates = WhatsappTemplate::where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($templates);
    }

    public function previewMessage(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $lead->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $templateId = $request->input('template_id');
        $customText = $request->input('custom_text');

        $templateContent = '';
        if ($templateId) {
            $template = WhatsappTemplate::find($templateId);
            if ($template) {
                $templateContent = $template->content;
            }
        }

        if (empty($templateContent)) {
            $templateContent = $customText ?: "Hello {lead_name}, greetings from Vistabound Integrated Solutions! How can we assist you today?";
        }

        $followupDateStr = $lead->next_follow_up_date
            ? Carbon::parse($lead->next_follow_up_date)->format('d M Y, h:i A')
            : 'soon';

        $assignee = $lead->assignedEmployee ?: $user;

        $replacements = [
            '{lead_name}' => $lead->name,
            '{company_name}' => $lead->company_name ?: 'your organization',
            '{service_interest}' => $lead->service_interest ?: 'our enterprise solutions',
            '{employee_name}' => $assignee->name,
            '{employee_phone}' => $assignee->phone ?: '+91 63 61 232225',
            '{followup_date}' => $followupDateStr,
            '{lead_reference}' => $lead->reference_id,
        ];

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        // International format with 91 prefix for wa.me
        $phoneDigits = preg_replace('/\D+/', '', $lead->phone_normalized);
        if (strlen($phoneDigits) === 10) {
            $waPhone = '91' . $phoneDigits;
        } else {
            $waPhone = $phoneDigits;
        }

        $encodedText = rawurlencode($rendered);
        $waMeUrl = "https://wa.me/{$waPhone}?text={$encodedText}";
        $webWhatsAppUrl = "https://web.whatsapp.com/send?phone={$waPhone}&text={$encodedText}";

        return response()->json([
            'rendered_message' => $rendered,
            'whatsapp_phone' => $waPhone,
            'wa_me_url' => $waMeUrl,
            'web_whatsapp_url' => $webWhatsAppUrl,
        ]);
    }

    public function logMessageDispatch(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $lead->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'template_key' => 'nullable|string',
        ]);

        $phoneDigits = preg_replace('/\D+/', '', $lead->phone_normalized);
        $waPhone = strlen($phoneDigits) === 10 ? '91' . $phoneDigits : $phoneDigits;

        // Log notification
        $log = NotificationLog::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'recipient' => '+' . $waPhone,
            'title' => 'WhatsApp Outreach Dispatched',
            'message' => $validated['message'],
            'status' => 'sent',
            'metadata' => [
                'template_key' => $validated['template_key'] ?? 'custom',
                'dispatched_by' => $user->name,
            ],
            'is_read' => true,
        ]);

        // Record lead activity
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'whatsapp_sent',
            'title' => 'WhatsApp Message Sent',
            'description' => mb_strimwidth($validated['message'], 0, 150, '...'),
            'metadata' => [
                'notification_log_id' => $log->id,
                'recipient' => '+' . $waPhone,
            ],
        ]);

        $lead->last_contact_date = Carbon::now();
        if ($lead->status === 'new' || $lead->status === 'assigned') {
            $lead->status = 'contacted';
        }
        $lead->save();

        return response()->json([
            'message' => 'WhatsApp activity logged successfully',
            'log' => $log,
        ]);
    }
}
