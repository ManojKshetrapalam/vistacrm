<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeployPreflightCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'deploy:preflight 
                            {--target= : Deployment target environment (must be explicitly "staging")}
                            {--check-database : Attempt connection to verified staging database}
                            {--db-name= : Override database name for test simulation}
                            {--db-host= : Override database host for test simulation}
                            {--db-user= : Override database user for test simulation}
                            {--db-pass= : Override database password for test simulation}
                            {--env-name= : Override APP_ENV for test simulation}';

    /**
     * The console command description.
     */
    protected $description = 'Enforce Invariant 9 fail-closed pre-flight validation before staging deployment';

    /**
     * Prohibited production hosts (IPs, domains, hostnames).
     */
    protected const PROHIBITED_HOSTS = [
        '31.97.225.172',
        'vistaboundintegrated.com',
        'crm.vistaboundintegrated.com',
        'www.vistaboundintegrated.com',
    ];

    /**
     * Prohibited production database identifiers.
     */
    protected const PROHIBITED_DATABASES = [
        'vistabound_crm',
        'crm_prod',
        'production',
        'database.sqlite',
        'crm_production',
    ];

    /**
     * Approved staging database identifiers.
     */
    protected const APPROVED_STAGING_DATABASES = [
        'vista_crm_staging',
        'vistabound_crm_staging',
        'u996219523_vista_crm_staging',
        'vista_crm_staging.sqlite',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('===============================================================');
        $this->info('  Vistabound CRM - Invariant 9 Deployment Pre-Flight Gate      ');
        $this->info('===============================================================');

        // 1. Validate Target Parameter
        $target = $this->option('target');
        if (empty($target)) {
            $this->error('FAIL: Missing required --target option. Deployment target must be explicitly specified.');
            return 1;
        }

        if (strtolower(trim($target)) !== 'staging') {
            $this->error("FAIL: Invalid deployment target '{$target}'. Only 'staging' is permitted by this deployment gate.");
            return 1;
        }

        // 2. Validate Application Environment (APP_ENV)
        $appEnv = $this->option('env-name') !== null 
            ? strtolower(trim((string)$this->option('env-name')))
            : strtolower(trim((string)app()->environment()));

        if ($appEnv === 'production') {
            $this->error("CRITICAL SAFETY VIOLATION: APP_ENV is 'production'! Invariant 9 strictly forbids staging deployment tooling against production.");
            return 1;
        }

        if ($appEnv !== 'staging') {
            $this->error("FAIL: APP_ENV is '{$appEnv}'. Staging deployment requires APP_ENV to be exactly 'staging'.");
            return 1;
        }
        $this->line('  ✓ APP_ENV is verified as "staging"');

        // 3. Resolve Database Settings
        $connection = config('database.default', 'mysql');
        $dbName = $this->option('db-name') !== null
            ? trim((string)$this->option('db-name'))
            : trim((string)config("database.connections.{$connection}.database"));

        $dbHost = $this->option('db-host') !== null
            ? trim((string)$this->option('db-host'))
            : trim((string)config("database.connections.{$connection}.host"));

        $dbUser = $this->option('db-user') !== null
            ? (string)$this->option('db-user')
            : (string)config("database.connections.{$connection}.username");

        $dbPass = $this->option('db-pass') !== null
            ? (string)$this->option('db-pass')
            : (string)config("database.connections.{$connection}.password");

        // 4. Validate Database Host
        $normalizedHost = strtolower($dbHost);
        foreach (self::PROHIBITED_HOSTS as $prohibitedHost) {
            if ($normalizedHost === $prohibitedHost || str_contains($normalizedHost, $prohibitedHost)) {
                $this->error("CRITICAL SAFETY VIOLATION: Database host '{$dbHost}' matches prohibited production host '{$prohibitedHost}'! Invariant 9 strictly blocks execution.");
                return 1;
            }
        }
        $this->line("  ✓ Database host '{$dbHost}' is verified non-production");

        // 5. Validate Database Name
        $normalizedDb = strtolower(basename($dbName));
        foreach (self::PROHIBITED_DATABASES as $prohibitedDb) {
            if ($normalizedDb === $prohibitedDb || str_contains($normalizedDb, $prohibitedDb)) {
                $this->error("CRITICAL SAFETY VIOLATION: Database name '{$dbName}' matches prohibited production database identifier '{$prohibitedDb}'!");
                return 1;
            }
        }

        if (!in_array($normalizedDb, self::APPROVED_STAGING_DATABASES, true)) {
            $approvedList = implode(', ', self::APPROVED_STAGING_DATABASES);
            $this->error("FAIL: Database name '{$dbName}' is not in the approved staging database list ({$approvedList}).");
            return 1;
        }
        $this->line("  ✓ Database name '{$dbName}' is verified as an approved isolated staging database");

        // 6. Validate Staging Database Credentials (for network databases)
        $isSqlite = $connection === 'sqlite' || str_ends_with($normalizedDb, '.sqlite');
        if (!$isSqlite) {
            if (empty(trim($dbUser))) {
                $this->error('FAIL: Staging database username (DB_USERNAME) is missing or empty.');
                return 1;
            }

            if ($dbPass === '' && !in_array($dbHost, ['127.0.0.1', 'localhost'], true)) {
                $this->error('FAIL: Staging database password (DB_PASSWORD) is empty for non-local host.');
                return 1;
            }
            $this->line('  ✓ Staging database credentials are present');
        } else {
            $this->line('  ✓ Staging SQLite database driver verified');
        }

        // 7. Optional Staging Database Connectivity Check
        if ($this->option('check-database')) {
            try {
                $pdo = DB::connection()->getPdo();
                $currentDb = DB::connection()->getDatabaseName();
                if (strtolower($currentDb) !== $normalizedDb) {
                    $this->error("FAIL: Connected database name '{$currentDb}' does not match expected staging database '{$normalizedDb}'!");
                    return 1;
                }
                $this->line("  ✓ Live connection established to staging database '{$currentDb}'");
            } catch (\Throwable $e) {
                $this->error("FAIL: Staging database connectivity check failed: " . $e->getMessage());
                return 1;
            }
        }

        $this->info('---------------------------------------------------------------');
        $this->info('  PRE-FLIGHT STATUS: PASS — Safe for staging migration/deployment');
        $this->info('---------------------------------------------------------------');
        return 0;
    }
}
