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
    /**
     * Run the database seeds.
     * Guaranteed 100% idempotent and repeatable without duplicating records.
     */
    public function run(): void
    {
        // 1. Seed Administrator / Tenant Owner
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

        if (empty($admin->username)) {
            $admin->username = 'admin';
            $admin->save();
        }

        // 2. Seed Team Roles (Sales Manager, Sales Executives, Accounts Executive)
        $teamMembersData = [
            [
                'name' => 'Suresh Menon',
                'username' => 'salesmanager',
                'email' => 'sales.manager@vistaboundintegrated.com',
                'phone' => '+919880000111',
                'role' => 'employee',
                'title' => 'Sales Manager',
            ],
            [
                'name' => 'Ananya Sharma',
                'username' => 'ananya',
                'email' => 'ananya@vistaboundintegrated.com',
                'phone' => '+919880111221',
                'role' => 'employee',
                'title' => 'Sales Executive',
            ],
            [
                'name' => 'Rohit Verma',
                'username' => 'rohit',
                'email' => 'rohit@vistaboundintegrated.com',
                'phone' => '+919880222332',
                'role' => 'employee',
                'title' => 'Sales Executive',
            ],
            [
                'name' => 'Priya Nair',
                'username' => 'priya',
                'email' => 'priya@vistaboundintegrated.com',
                'phone' => '+919880333443',
                'role' => 'employee',
                'title' => 'Sales Executive',
            ],
            [
                'name' => 'Karthik Rao',
                'username' => 'karthik',
                'email' => 'karthik@vistaboundintegrated.com',
                'phone' => '+919880444554',
                'role' => 'employee',
                'title' => 'Sales Executive',
            ],
            [
                'name' => 'Deepa Joshi',
                'username' => 'accounts',
                'email' => 'accounts@vistaboundintegrated.com',
                'phone' => '+919880555665',
                'role' => 'employee',
                'title' => 'Accounts Executive',
            ],
        ];

        $users = ['admin' => $admin];
        foreach ($teamMembersData as $emp) {
            $user = User::firstOrCreate(
                ['email' => $emp['email']],
                [
                    'name' => $emp['name'],
                    'username' => $emp['username'],
                    'phone' => $emp['phone'],
                    'role' => $emp['role'],
                    'password' => Hash::make('Vistabound@2026'),
                    'is_active' => true,
                    'must_change_password' => false,
                    'notification_preferences' => [
                        'whatsapp_enabled' => true,
                        'email_enabled' => true,
                        'in_app_enabled' => true,
                        'phone_for_notifications' => $emp['phone'],
                    ],
                ]
            );

            if (empty($user->username)) {
                $user->username = $emp['username'];
                $user->save();
            }

            $users[$emp['username']] = $user;
        }

        // 2.5 Seed Permissions, Roles, Teams, and Assignments
        $this->call(PermissionsSeeder::class);

        // 3. Seed CRM Settings & Tenant / Company Configuration
        CrmSetting::set('company_info', [
            'name' => 'Vistabound Integrated Solutions LLP',
            'tagline' => 'Building Businesses Beyond Boundaries',
            'website' => 'https://vistaboundintegrated.com/',
            'email' => 'vistaboundintegrated@gmail.com',
            'phone' => '+916361232225',
            'location' => 'RR Nagar, Bangalore',
        ]);

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
            'Retail & Commerce Solutions',
            'Accounting & Compliance Solutions'
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

        // 5. Seed Synthetic Demo Leads and Converted Customers
        $sampleLeads = [
            [
                'ref' => 'VB-LD-2026-0001',
                'name' => 'Rajesh Nambiar',
                'phone' => '+919845012345',
                'email' => 'rajesh.n@technex-systems.com',
                'company_name' => 'TechNex Systems Pvt Ltd',
                'source' => 'Website',
                'service_interest' => 'IT Infrastructure & Hardware AMC',
                'additional_details' => 'Needs 24/7 hardware AMC and server maintenance for 120 workstations.',
                'status' => 'follow_up',
                'priority' => 'high',
                'assigned_username' => 'ananya',
                'next_follow_up_date' => Carbon::today()->setTime(14, 30),
                'last_contact_date' => Carbon::now()->subDays(1),
            ],
            [
                'ref' => 'VB-LD-2026-0002',
                'name' => 'Sunita Rao',
                'phone' => '+919876543210',
                'email' => 'sunita@apexlogistics.in',
                'company_name' => 'Apex Logistics India',
                'source' => 'WhatsApp Inbound',
                'service_interest' => 'Enterprise Software Engineering',
                'additional_details' => 'Looking to automate fleet tracking dispatch and custom ERP module.',
                'status' => 'contacted',
                'priority' => 'urgent',
                'assigned_username' => 'rohit',
                'next_follow_up_date' => Carbon::today()->setTime(16, 0),
                'last_contact_date' => Carbon::now()->subHours(4),
            ],
            [
                'ref' => 'VB-LD-2026-0003',
                'name' => 'Vikramaditya Hegde',
                'phone' => '+919900112233',
                'email' => 'v.hegde@serenestay.com',
                'company_name' => 'Serene Stay Hospitality',
                'source' => 'Partner Vertical',
                'service_interest' => 'Hospitality Management',
                'additional_details' => 'New boutique resort opening in Coorg. Interested in operational frameworks and central PMS.',
                'status' => 'interested',
                'priority' => 'high',
                'assigned_username' => 'ananya',
                'next_follow_up_date' => Carbon::tomorrow()->setTime(11, 0),
                'last_contact_date' => Carbon::now()->subDays(2),
            ],
            [
                'ref' => 'VB-LD-2026-0004',
                'name' => 'Deepak Shenoy',
                'phone' => '+919844055667',
                'email' => 'deepak@cloudscale.co',
                'company_name' => 'CloudScale Labs',
                'source' => 'LinkedIn',
                'service_interest' => 'Cloud & DevOps Solutions',
                'additional_details' => 'Kubernetes multi-cloud cost optimization and zero-trust security audit.',
                'status' => 'new',
                'priority' => 'medium',
                'assigned_username' => 'priya',
                'next_follow_up_date' => null,
                'last_contact_date' => null,
            ],
            [
                'ref' => 'VB-LD-2026-0005',
                'name' => 'Kavita Menon',
                'phone' => '+919741098765',
                'email' => 'kavita@greengrovestores.com',
                'company_name' => 'GreenGrove Organics (Existing Customer)',
                'source' => 'Referral',
                'service_interest' => 'Retail & Commerce Solutions',
                'additional_details' => 'Omnichannel POS active across 6 retail outlets. Converted into active customer contract.',
                'status' => 'converted',
                'priority' => 'high',
                'assigned_username' => 'karthik',
                'next_follow_up_date' => null,
                'last_contact_date' => Carbon::now()->subDays(3),
            ],
            [
                'ref' => 'VB-LD-2026-0006',
                'name' => 'Manoj Kulkarni',
                'phone' => '+919611223344',
                'email' => 'manoj@kulkarnitraders.in',
                'company_name' => 'Kulkarni Traders',
                'source' => 'Cold Outreach',
                'service_interest' => 'IT Infrastructure & Hardware AMC',
                'additional_details' => 'Follow up on hardware upgrade proposal.',
                'status' => 'not_reachable',
                'priority' => 'low',
                'assigned_username' => 'rohit',
                'next_follow_up_date' => Carbon::today()->addDays(2)->setTime(10, 0),
                'last_contact_date' => Carbon::now()->subDays(4),
            ],
            [
                'ref' => 'VB-LD-2026-0007',
                'name' => 'Arun Balakrishnan',
                'phone' => '+919448011223',
                'email' => 'arun@zenithcorp.in',
                'company_name' => 'Zenith Corp India (Customer)',
                'source' => 'Website',
                'service_interest' => 'Enterprise Software Engineering',
                'additional_details' => 'Enterprise annual maintenance contract and compliance audit signed.',
                'status' => 'converted',
                'priority' => 'high',
                'assigned_username' => 'salesmanager',
                'next_follow_up_date' => null,
                'last_contact_date' => Carbon::now()->subDays(7),
            ],
        ];

        foreach ($sampleLeads as $leadData) {
            $normalizedPhone = Lead::normalizePhone($leadData['phone']);
            $assignedUser = isset($users[$leadData['assigned_username']]) ? $users[$leadData['assigned_username']] : null;

            $lead = Lead::firstOrCreate(
                ['phone_normalized' => $normalizedPhone],
                [
                    'reference_id' => $leadData['ref'],
                    'name' => $leadData['name'],
                    'phone' => $leadData['phone'],
                    'email' => $leadData['email'],
                    'company_name' => $leadData['company_name'],
                    'source' => $leadData['source'],
                    'service_interest' => $leadData['service_interest'],
                    'additional_details' => $leadData['additional_details'],
                    'status' => $leadData['status'],
                    'priority' => $leadData['priority'],
                    'assigned_to' => $assignedUser ? $assignedUser->id : null,
                    'created_by' => $admin->id,
                    'next_follow_up_date' => $leadData['next_follow_up_date'],
                    'last_contact_date' => $leadData['last_contact_date'],
                ]
            );

            // Seed initial activity idempotently
            LeadActivity::firstOrCreate(
                [
                    'lead_id' => $lead->id,
                    'activity_type' => 'lead_created',
                ],
                [
                    'user_id' => $admin->id,
                    'title' => 'Lead created in CRM',
                    'description' => "Source: {$lead->source} | Service: {$lead->service_interest}",
                    'metadata' => ['source' => $lead->source],
                ]
            );

            if ($lead->assigned_to) {
                LeadActivity::firstOrCreate(
                    [
                        'lead_id' => $lead->id,
                        'activity_type' => 'assignment_change',
                    ],
                    [
                        'user_id' => $admin->id,
                        'title' => "Assigned to {$assignedUser->name}",
                        'description' => "Initial assignment to {$assignedUser->name}",
                        'metadata' => ['assigned_to' => $assignedUser->id, 'assignee_name' => $assignedUser->name],
                    ]
                );

                // Create initial assignment notification idempotently
                NotificationLog::firstOrCreate(
                    [
                        'lead_id' => $lead->id,
                        'user_id' => $assignedUser->id,
                        'channel' => 'in_app',
                    ],
                    [
                        'recipient' => $assignedUser->email,
                        'title' => 'New Lead Assigned',
                        'message' => "You have been assigned lead {$lead->name} ({$lead->reference_id}) for {$lead->service_interest}.",
                        'status' => 'delivered',
                        'is_read' => false,
                    ]
                );
            }

            if ($lead->next_follow_up_date) {
                FollowUp::firstOrCreate(
                    [
                        'lead_id' => $lead->id,
                        'scheduled_at' => $lead->next_follow_up_date->format('Y-m-d H:i:s'),
                    ],
                    [
                        'user_id' => $lead->assigned_to ?: $admin->id,
                        'status' => 'pending',
                        'notes' => 'Discuss proposal and clarify service requirements.',
                    ]
                );
            }
        }
    }
}
