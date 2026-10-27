<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
