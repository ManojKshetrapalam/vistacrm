<?php

namespace Database\Seeders;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\NotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run synthetic demo leads and activities seeder for local development only.
     * Strictly prohibited on staging and production environments.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            return;
        }

        $users = User::all()->keyBy('username')->all();

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

            if ($lead->assigned_to && $assignedUser) {
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
