<?php

namespace Database\Seeders;

use App\Models\CrmSetting;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StagingSeeder extends Seeder
{
    /**
     * Staging Environment Dedicated Seeder.
     * Guaranteed 100% idempotent.
     * Seeds only verified baseline configuration, QA accounts, and permissions.
     * Strictly avoids injecting dummy/synthetic leads or altering business data.
     */
    public function run(): void
    {
        // 1. Seed Staging Administrator Account
        $admin = User::firstOrCreate(
            ['email' => 'admin@vistaboundintegrated.com'],
            [
                'name' => 'Staging Administrator',
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

        // 2. Seed Staging QA Staff Accounts
        $qaAccounts = [
            [
                'name' => 'Staging Sales Manager',
                'username' => 'staging_manager',
                'email' => 'sales.manager@vistaboundintegrated.com',
                'phone' => '+919880000111',
                'role' => 'employee',
            ],
            [
                'name' => 'Staging Sales Rep 1',
                'username' => 'staging_rep1',
                'email' => 'ananya@vistaboundintegrated.com',
                'phone' => '+919880111221',
                'role' => 'employee',
            ],
            [
                'name' => 'Staging Sales Rep 2',
                'username' => 'staging_rep2',
                'email' => 'rohit@vistaboundintegrated.com',
                'phone' => '+919880222332',
                'role' => 'employee',
            ],
        ];

        foreach ($qaAccounts as $acc) {
            $user = User::firstOrCreate(
                ['email' => $acc['email']],
                [
                    'name' => $acc['name'],
                    'username' => $acc['username'],
                    'phone' => $acc['phone'],
                    'role' => $acc['role'],
                    'password' => Hash::make('Vistabound@2026'),
                    'is_active' => true,
                    'must_change_password' => false,
                    'notification_preferences' => [
                        'whatsapp_enabled' => true,
                        'email_enabled' => true,
                        'in_app_enabled' => true,
                        'phone_for_notifications' => $acc['phone'],
                    ],
                ]
            );

            if (empty($user->username)) {
                $user->username = $acc['username'];
                $user->save();
            }
        }

        // 3. Seed Permissions, Roles, Teams, and Assignments
        $this->call(PermissionsSeeder::class);

        // 4. Seed Reference Dictionaries & Settings
        CrmSetting::set('company_info', [
            'name' => 'Vistabound Integrated Solutions LLP (Staging)',
            'tagline' => 'Building Businesses Beyond Boundaries - QA Test Node',
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
            'Exhibition / Event',
        ]);

        CrmSetting::set('service_categories', [
            'IT Infrastructure & Hardware AMC',
            'Cloud & DevOps Solutions',
            'Enterprise Software Engineering',
            'Incubation & Strategic Investment',
            'Hospitality Management',
            'Retail & Commerce Solutions',
            'Accounting & Compliance Solutions',
        ]);

        // 5. Seed Official WhatsApp Templates
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
    }
}
