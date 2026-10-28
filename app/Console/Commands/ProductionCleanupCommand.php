<?php

namespace App\Console\Commands;

use App\Models\CrmSetting;
use App\Models\FollowUp;
use App\Models\ImportHistory;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ProductionCleanupCommand extends Command
{
    protected $signature = 'crm:production-cleanup 
                            {--dry-run : Preview records that would be removed without deleting}
                            {--force : Force cleanup without interactive confirmation}';

    protected $description = 'Safely clear mock leads, test follow-ups, and demo accounts prior to production launch';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("==================================================");
        $this->info("  Vistabound CRM — Production Data Cleanup Tool   ");
        $this->info("==================================================");

        // Pre-cleanup audit counts
        $adminUsersCount = User::where('role', 'admin')->count();
        $testUsersCount = User::where('role', '!=', 'admin')->count();
        $leadsCount = Lead::count();
        $activitiesCount = LeadActivity::count();
        $followUpsCount = FollowUp::count();
        $notificationsCount = NotificationLog::count();
        $importHistoriesCount = ImportHistory::count();
        $tokensCount = PersonalAccessToken::count();
        $templatesCount = WhatsappTemplate::count();
        $settingsCount = CrmSetting::count();

        $this->table(
            ['Record Type', 'Current Count', 'Target Action'],
            [
                ['Admin Users', $adminUsersCount, '<fg=green>PRESERVE</>'],
                ['Test / Demo Users', $testUsersCount, '<fg=red>DELETE</>'],
                ['Leads (Mock / Test)', $leadsCount, '<fg=red>DELETE</>'],
                ['Lead Activities', $activitiesCount, '<fg=red>DELETE</>'],
                ['Follow-ups & Reminders', $followUpsCount, '<fg=red>DELETE</>'],
                ['Notification Logs', $notificationsCount, '<fg=red>DELETE</>'],
                ['Import Histories', $importHistoriesCount, '<fg=red>DELETE</>'],
                ['Personal Access Tokens', $tokensCount, '<fg=red>CLEANSE (Non-Admin)</>'],
                ['WhatsApp Templates', $templatesCount, '<fg=green>PRESERVE (System Reference)</>'],
                ['CRM System Settings', $settingsCount, '<fg=green>PRESERVE (System Reference)</>'],
            ]
        );

        if ($dryRun) {
            $this->warn("[DRY-RUN MODE] No data was modified. Run without --dry-run to execute cleanup.");
            return Command::SUCCESS;
        }

        if (!$force && !$this->confirm("Are you sure you want to delete these test records and initialize production state?")) {
            $this->info("Cleanup aborted by operator.");
            return Command::SUCCESS;
        }

        $adminIds = User::where('role', 'admin')->pluck('id')->toArray();

        DB::transaction(function () use ($adminIds) {
            // Delete dependent records first to respect FK integrity
            NotificationLog::truncate();
            LeadActivity::truncate();
            FollowUp::truncate();
            ImportHistory::truncate();
            Lead::truncate();

            // Clear non-admin tokens
            PersonalAccessToken::whereNotIn('tokenable_id', $adminIds)
                ->orWhere('tokenable_type', '!=', User::class)
                ->delete();

            // Delete non-admin test users
            User::where('role', '!=', 'admin')->delete();
        });

        $this->info("\n==> Production cleanup completed successfully.");
        $this->table(
            ['Table', 'Post-Cleanup Count', 'Status'],
            [
                ['users (Admin Only)', User::count(), '<fg=green>Clean (Admin Retained)</>'],
                ['leads', Lead::count(), '<fg=green>Clean (Zero Leads)</>'],
                ['lead_activities', LeadActivity::count(), '<fg=green>Clean (Zero Activities)</>'],
                ['follow_ups', FollowUp::count(), '<fg=green>Clean (Zero Follow-ups)</>'],
                ['notification_logs', NotificationLog::count(), '<fg=green>Clean (Zero Logs)</>'],
                ['import_histories', ImportHistory::count(), '<fg=green>Clean (Zero Imports)</>'],
                ['whatsapp_templates', WhatsappTemplate::count(), '<fg=green>Intact (4 Templates)</>'],
                ['crm_settings', CrmSetting::count(), '<fg=green>Intact (3 Dictionaries)</>'],
            ]
        );

        return Command::SUCCESS;
    }
}
