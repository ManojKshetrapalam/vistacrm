<?php

namespace Database\Seeders;

use App\Models\CrmSetting;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@vistaboundintegrated.com'],
            [
                'name' => 'CRM Administrator',
                'phone' => '+916361232225',
                'role' => 'admin',
                'password' => Hash::make('Vistabound@2026'),
                'is_active' => true,
                'notification_preferences' => [
                    'whatsapp_enabled' => true,
                    'email_enabled' => true,
                    'in_app_enabled' => true,
                    'phone_for_notifications' => '+916361232225',
                ],
            ]
        );

        // 2. Seed 4 Initial Employees
        $employeesData = [
            [
                'name' => 'Ananya Sharma',
                'email' => 'ananya@vistaboundintegrated.com',
                'phone' => '+919880111221',
            ],
            [
                'name' => 'Rohit Verma',
                'email' => 'rohit@vistaboundintegrated.com',
                'phone' => '+919880222332',
            ],
            [
                'name' => 'Priya Nair',
                'email' => 'priya@vistaboundintegrated.com',
                'phone' => '+919880333443',
            ],
            [
                'name' => 'Karthik Rao',
                'email' => 'karthik@vistaboundintegrated.com',
                'phone' => '+919880444554',
            ],
        ];

        $employees = [];
        foreach ($employeesData as $emp) {
            $employees[] = User::firstOrCreate(
                ['email' => $emp['email']],
                [
                    'name' => $emp['name'],
                    'phone' => $emp['phone'],
                    'role' => 'employee',
                    'password' => Hash::make('Vistabound@2026'),
                    'is_active' => true,
                    'notification_preferences' => [
                        'whatsapp_enabled' => true,
                        'email_enabled' => true,
                        'in_app_enabled' => true,
                        'phone_for_notifications' => $emp['phone'],
                    ],
                ]
            );
        }

        // 3. Seed CRM Settings
        CrmSetting::set('lead_sources', [
            'Website',
            'WhatsApp Inbound',
            'LinkedIn',
            'Referral',
            'Cold Outreach',
            'Partner Vertical',
            'Exhibition / Event'
        ]);

        CrmSetting::set('service_categories', [
            'IT Infrastructure & Hardware AMC',
            'Cloud & DevOps Solutions',
            'Enterprise Software Engineering',
            'Incubation & Strategic Investment',
            'Hospitality Management',
            'Retail & Commerce Solutions'
        ]);

        CrmSetting::set('company_info', [
            'name' => 'Vistabound Integrated Solutions LLP',
            'tagline' => 'Building Businesses Beyond Boundaries',
            'website' => 'https://vistaboundintegrated.com/',
            'email' => 'vistaboundintegrated@gmail.com',
            'phone' => '+916361232225',
            'location' => 'RR Nagar, Bangalore',
        ]);

        // 4. Seed WhatsApp Templates
        $templates = [
            [
                'title' => 'Initial Introduction & Discovery',
                'template_key' => 'initial_intro',
                'content' => "Hello {lead_name}, greetings from Vistabound Integrated Solutions LLP! Thank you for reaching out regarding {service_interest}. I am {employee_name} from our client solutions desk. When would be a convenient time for a brief 10-minute discovery call?",
                'variables' => ['lead_name', 'service_interest', 'employee_name', 'company_name', 'employee_phone'],
            ],
            [
                'title' => 'Executive Follow-up Check-in',
                'template_key' => 'executive_followup',
                'content' => "Hi {lead_name}, following up on our discussion regarding {service_interest} for {company_name}. We have prepared an executive scope tailored to your requirements. Would you prefer to review it over WhatsApp or on a brief call?",
                'variables' => ['lead_name', 'service_interest', 'company_name', 'employee_name'],
            ],
            [
                'title' => 'Consultation / Meeting Reminder',
                'template_key' => 'meeting_reminder',
                'content' => "Dear {lead_name}, your consultation session with Vistabound Integrated Solutions is scheduled for {followup_date}. Looking forward to connecting! Best regards, {employee_name} ({employee_phone}).",
                'variables' => ['lead_name', 'followup_date', 'employee_name', 'employee_phone'],
            ],
            [
                'title' => 'Proposal & Service Agreement Status',
                'template_key' => 'proposal_status',
                'content' => "Hello {lead_name}, this is {employee_name} from Vistabound. Just checking in to see if you had a chance to review our proposed IT infrastructure & enterprise support agreement. Please let us know if you need any adjustments.",
                'variables' => ['lead_name', 'employee_name', 'company_name'],
            ],
        ];

        foreach ($templates as $tmpl) {
            WhatsappTemplate::firstOrCreate(
                ['template_key' => $tmpl['template_key']],
                [
                    'title' => $tmpl['title'],
                    'content' => $tmpl['content'],
                    'variables' => $tmpl['variables'],
                    'is_active' => true,
                ]
            );
        }

        // 5. Seed Realistic Sample Leads
        $sampleLeads = [
            [
                'name' => 'Rajesh Nambiar',
                'phone' => '+919845012345',
                'email' => 'rajesh.n@technex-systems.com',
                'company_name' => 'TechNex Systems Pvt Ltd',
                'source' => 'Website',
                'service_interest' => 'IT Infrastructure & Hardware AMC',
                'additional_details' => 'Needs 24/7 hardware AMC and server maintenance for 120 workstations.',
                'status' => 'follow_up',
                'priority' => 'high',
                'assigned_to' => $employees[0]->id,
                'created_by' => $admin->id,
                'next_follow_up_date' => Carbon::today()->setTime(14, 30),
                'last_contact_date' => Carbon::now()->subDays(1),
            ],
            [
                'name' => 'Sunita Rao',
                'phone' => '+919876543210',
                'email' => 'sunita@apexlogistics.in',
                'company_name' => 'Apex Logistics India',
                'source' => 'WhatsApp Inbound',
                'service_interest' => 'Enterprise Software Engineering',
                'additional_details' => 'Looking to automate fleet tracking dispatch and custom ERP module.',
                'status' => 'contacted',
                'priority' => 'urgent',
                'assigned_to' => $employees[1]->id,
                'created_by' => $admin->id,
                'next_follow_up_date' => Carbon::today()->setTime(16, 0),
                'last_contact_date' => Carbon::now()->subHours(4),
            ],
            [
                'name' => 'Vikramaditya Hegde',
                'phone' => '+919900112233',
                'email' => 'v.hegde@serenestay.com',
                'company_name' => 'Serene Stay Hospitality',
                'source' => 'Partner Vertical',
                'service_interest' => 'Hospitality Management',
                'additional_details' => 'New boutique resort opening in Coorg. Interested in operational frameworks and central PMS.',
                'status' => 'interested',
                'priority' => 'high',
                'assigned_to' => $employees[0]->id,
                'created_by' => $admin->id,
                'next_follow_up_date' => Carbon::tomorrow()->setTime(11, 0),
                'last_contact_date' => Carbon::now()->subDays(2),
            ],
            [
                'name' => 'Deepak Shenoy',
                'phone' => '+919844055667',
                'email' => 'deepak@cloudscale.co',
                'company_name' => 'CloudScale Labs',
                'source' => 'LinkedIn',
                'service_interest' => 'Cloud & DevOps Solutions',
                'additional_details' => 'Kubernetes multi-cloud cost optimization and zero-trust security audit.',
                'status' => 'new',
                'priority' => 'medium',
                'assigned_to' => $employees[2]->id,
                'created_by' => $admin->id,
                'next_follow_up_date' => null,
                'last_contact_date' => null,
            ],
            [
                'name' => 'Kavita Menon',
                'phone' => '+919741098765',
                'email' => 'kavita@greengrovestores.com',
                'company_name' => 'GreenGrove Organics',
                'source' => 'Referral',
                'service_interest' => 'Retail & Commerce Solutions',
                'additional_details' => 'Expanding omnichannel POS across 6 retail outlets in Bangalore.',
                'status' => 'converted',
                'priority' => 'high',
                'assigned_to' => $employees[3]->id,
                'created_by' => $admin->id,
                'next_follow_up_date' => null,
                'last_contact_date' => Carbon::now()->subDays(3),
            ],
            [
                'name' => 'Manoj Kulkarni',
                'phone' => '+919611223344',
                'email' => 'manoj@kulkarnitraders.in',
                'company_name' => 'Kulkarni Traders',
                'source' => 'Cold Outreach',
                'service_interest' => 'IT Infrastructure & Hardware AMC',
                'additional_details' => 'Follow up on hardware upgrade proposal.',
                'status' => 'not_reachable',
                'priority' => 'low',
                'assigned_to' => $employees[1]->id,
                'created_by' => $admin->id,
                'next_follow_up_date' => Carbon::today()->addDays(2)->setTime(10, 0),
                'last_contact_date' => Carbon::now()->subDays(4),
            ],
        ];

        foreach ($sampleLeads as $leadData) {
            $normalizedPhone = Lead::normalizePhone($leadData['phone']);
            $lead = Lead::create(array_merge($leadData, [
                'reference_id' => Lead::generateReferenceId(),
                'phone_normalized' => $normalizedPhone,
            ]));

            // Seed initial activity
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $admin->id,
                'activity_type' => 'lead_created',
                'title' => 'Lead created in CRM',
                'description' => "Source: {$lead->source} | Service: {$lead->service_interest}",
                'metadata' => ['source' => $lead->source],
            ]);

            if ($lead->assigned_to) {
                $assignedUser = User::find($lead->assigned_to);
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $admin->id,
                    'activity_type' => 'assignment_change',
                    'title' => "Assigned to {$assignedUser->name}",
                    'description' => "Initial assignment to {$assignedUser->name}",
                    'metadata' => ['assigned_to' => $assignedUser->id, 'assignee_name' => $assignedUser->name],
                ]);

                // Create initial assignment notification
                NotificationLog::create([
                    'lead_id' => $lead->id,
                    'user_id' => $assignedUser->id,
                    'channel' => 'in_app',
                    'recipient' => $assignedUser->email,
                    'title' => 'New Lead Assigned',
                    'message' => "You have been assigned lead {$lead->name} ({$lead->reference_id}) for {$lead->service_interest}.",
                    'status' => 'delivered',
                    'is_read' => false,
                ]);
            }

            if ($lead->next_follow_up_date) {
                FollowUp::create([
                    'lead_id' => $lead->id,
                    'user_id' => $lead->assigned_to ?: $admin->id,
                    'scheduled_at' => $lead->next_follow_up_date,
                    'status' => 'pending',
                    'notes' => 'Discuss proposal and clarify service requirements.',
                ]);
            }
        }
    }
}
