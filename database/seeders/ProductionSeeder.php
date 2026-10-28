<?php

namespace Database\Seeders;

use App\Models\CrmSetting;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed or Verify Primary Administrator Account
        $admin = User::firstOrCreate(
            ['email' => 'admin@vistaboundintegrated.com'],
            [
                'name' => 'CRM Administrator',
                'username' => 'admin',
                'phone' => '+916361232225',
                'role' => 'admin',
                'password' => Hash::make('Admin123'),
                'is_active' => true,
                'must_change_password' => true,
                'notification_preferences' => [
                    'whatsapp_enabled' => true,
                    'email_enabled' => true,
                    'in_app_enabled' => true,
                    'phone_for_notifications' => '+916361232225',
                ],
            ]
        );

        // Ensure username is attached if it was missing
        if (empty($admin->username)) {
            $admin->username = 'admin';
            $admin->save();
        }

        // 2. Seed Official WhatsApp Templates
        $templates = [
            [
                'title' => 'Initial Introduction & Welcome',
                'template_key' => 'initial_intro',
                'content' => "Hello {lead_name}, thank you for connecting with Vistabound Integrated Solutions LLP. We received your requirement for {service_interest}. Our specialist team is reviewing your details and will guide you through our enterprise services. Best regards, {employee_name} - Vistabound.",
                'variables' => ['lead_name', 'service_interest', 'employee_name'],
                'is_active' => true,
            ],
            [
                'title' => 'Executive Follow-up Check-in',
                'template_key' => 'executive_followup',
                'content' => "Hi {lead_name}, following up on our discussion regarding {service_interest} for {company_name}. We have prepared an executive scope tailored to your requirements. Would you prefer to review it over WhatsApp or on a brief call?",
                'variables' => ['lead_name', 'service_interest', 'company_name', 'employee_name'],
                'is_active' => true,
            ],
            [
                'title' => 'Consultation / Meeting Reminder',
                'template_key' => 'meeting_reminder',
                'content' => "Dear {lead_name}, your consultation session with Vistabound Integrated Solutions is scheduled for {followup_date}. Looking forward to connecting! Best regards, {employee_name} ({employee_phone}).",
                'variables' => ['lead_name', 'followup_date', 'employee_name', 'employee_phone'],
                'is_active' => true,
            ],
            [
                'title' => 'Proposal & Service Agreement Status',
                'template_key' => 'proposal_status',
                'content' => "Hello {lead_name}, this is {employee_name} from Vistabound. Just checking in to see if you had a chance to review our proposed IT infrastructure & enterprise support agreement. Please let us know if you need any adjustments.",
                'variables' => ['lead_name', 'employee_name', 'company_name'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $tmpl) {
            WhatsappTemplate::firstOrCreate(
                ['template_key' => $tmpl['template_key']],
                $tmpl
            );
        }

        // 3. Seed Reference Dictionaries
        $settings = [
            [
                'key' => 'lead_sources',
                'value' => [
                    'Website',
                    'LinkedIn',
                    'Referral',
                    'Direct Calling',
                    'Partner Vertical',
                    'WhatsApp Inbound',
                    'Cold Outreach',
                    'Event / Expo',
                ],
                'description' => 'Configured Inbound & Outbound Lead Acquisition Channels',
            ],
            [
                'key' => 'service_offerings',
                'value' => [
                    'IT Infrastructure & Hardware AMC',
                    'Cloud & DevOps Engineering',
                    'Enterprise Software Development',
                    'Business Incubation & Advisory',
                    'Hospitality Operations Consulting',
                    'Commerce & Multi-channel Solutions',
                ],
                'description' => 'Core Business Verticals and Service Categories',
            ],
            [
                'key' => 'lost_reasons',
                'value' => [
                    'Budget / Pricing Constraint',
                    'Selected Competitor',
                    'Project Postponed / Shelved',
                    'No Response / Cold Lead',
                    'Scope Outside Current Capabilities',
                    'Duplicate / Ineligible Requirement',
                ],
                'description' => 'Standardized Disqualification & Lost Outcome Categories',
            ],
        ];

        foreach ($settings as $setting) {
            CrmSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['key' => $setting['key'], 'value' => $setting['value']]
            );
        }
    }
}
