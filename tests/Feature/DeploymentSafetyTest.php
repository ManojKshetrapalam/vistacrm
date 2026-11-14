<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeploymentSafetyTest extends TestCase
{
    /**
     * Scenario 1: APP_ENV=production -> Reject
     */
    public function test_scenario_01_rejects_when_app_env_is_production(): void
    {
        $exitCode = Artisan::call('deploy:preflight', [
            '--target' => 'staging',
            '--env-name' => 'production',
            '--db-name' => 'vista_crm_staging',
            '--db-host' => '127.0.0.1',
            '--db-user' => 'staging_user',
            '--db-pass' => 'valid_pass',
        ]);

        $output = Artisan::output();
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString("CRITICAL SAFETY VIOLATION: APP_ENV is 'production'", $output);
    }

    /**
     * Scenario 2: Production DB name -> Reject
     */
    public function test_scenario_02_rejects_production_database_names(): void
    {
        $badDatabases = ['vistabound_crm', 'crm_prod', 'production', 'database.sqlite'];

        foreach ($badDatabases as $badDb) {
            $exitCode = Artisan::call('deploy:preflight', [
                '--target' => 'staging',
                '--env-name' => 'staging',
                '--db-name' => $badDb,
                '--db-host' => '127.0.0.1',
                '--db-user' => 'staging_user',
                '--db-pass' => 'valid_pass',
            ]);

            $output = Artisan::output();
            $this->assertEquals(1, $exitCode);
            $this->assertStringContainsString("matches prohibited production database identifier", $output);
        }
    }

    /**
     * Scenario 3: Production host -> Reject
     */
    public function test_scenario_03_rejects_production_host_ip_or_domain(): void
    {
        $badHosts = ['31.97.225.172', 'vistaboundintegrated.com', 'crm.vistaboundintegrated.com'];

        foreach ($badHosts as $badHost) {
            $exitCode = Artisan::call('deploy:preflight', [
                '--target' => 'staging',
                '--env-name' => 'staging',
                '--db-name' => 'vista_crm_staging',
                '--db-host' => $badHost,
                '--db-user' => 'staging_user',
                '--db-pass' => 'valid_pass',
            ]);

            $output = Artisan::output();
            $this->assertEquals(1, $exitCode);
            $this->assertStringContainsString("matches prohibited production host", $output);
        }
    }

    /**
     * Scenario 4: Missing staging target -> Reject
     */
    public function test_scenario_04_rejects_when_target_is_missing_or_not_staging(): void
    {
        // Omitted target
        $exitCodeNoTarget = Artisan::call('deploy:preflight', [
            '--env-name' => 'staging',
            '--db-name' => 'vista_crm_staging',
            '--db-host' => '127.0.0.1',
            '--db-user' => 'staging_user',
            '--db-pass' => 'valid_pass',
        ]);
        $this->assertEquals(1, $exitCodeNoTarget);
        $this->assertStringContainsString('Missing required --target option', Artisan::output());

        // Target is production
        $exitCodeProdTarget = Artisan::call('deploy:preflight', [
            '--target' => 'production',
            '--env-name' => 'staging',
            '--db-name' => 'vista_crm_staging',
            '--db-host' => '127.0.0.1',
            '--db-user' => 'staging_user',
            '--db-pass' => 'valid_pass',
        ]);
        $this->assertEquals(1, $exitCodeProdTarget);
        $this->assertStringContainsString("Invalid deployment target 'production'", Artisan::output());
    }

    /**
     * Scenario 5: Missing staging DB credentials -> Reject
     */
    public function test_scenario_05_rejects_missing_staging_database_credentials(): void
    {
        $exitCode = Artisan::call('deploy:preflight', [
            '--target' => 'staging',
            '--env-name' => 'staging',
            '--db-name' => 'vista_crm_staging',
            '--db-host' => '127.0.0.1',
            '--db-user' => '',
            '--db-pass' => '',
        ]);

        $output = Artisan::output();
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Staging database username (DB_USERNAME) is missing or empty', $output);
    }

    /**
     * Scenario 6: Wrong staging DB name -> Reject
     */
    public function test_scenario_06_rejects_unapproved_staging_database_name(): void
    {
        $exitCode = Artisan::call('deploy:preflight', [
            '--target' => 'staging',
            '--env-name' => 'staging',
            '--db-name' => 'arbitrary_test_db',
            '--db-host' => '127.0.0.1',
            '--db-user' => 'staging_user',
            '--db-pass' => 'valid_pass',
        ]);

        $output = Artisan::output();
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString("is not in the approved staging database list", $output);
    }

    /**
     * Scenario 7: Valid staging configuration -> Pre-flight passes
     */
    public function test_scenario_07_passes_with_valid_staging_configuration(): void
    {
        $exitCode = Artisan::call('deploy:preflight', [
            '--target' => 'staging',
            '--env-name' => 'staging',
            '--db-name' => 'vista_crm_staging',
            '--db-host' => '127.0.0.1',
            '--db-user' => 'vista_crm_staging_user',
            '--db-pass' => 'ValidStagingPass#2026',
        ]);

        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('PRE-FLIGHT STATUS: PASS', $output);
        $this->assertStringContainsString('Safe for staging migration/deployment', $output);
    }

    /**
     * Scenario 7b: Valid isolated SQLite staging configuration -> Pre-flight passes
     */
    public function test_scenario_07b_passes_with_valid_sqlite_staging_configuration(): void
    {
        $exitCode = Artisan::call('deploy:preflight', [
            '--target' => 'staging',
            '--env-name' => 'staging',
            '--db-name' => '/home/u996219523/domains/myworks.sbs/vistacrm1-app/storage/database/vista_crm_staging.sqlite',
            '--db-host' => '',
            '--db-user' => '',
            '--db-pass' => '',
        ]);

        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('PRE-FLIGHT STATUS: PASS', $output);
        $this->assertStringContainsString('Safe for staging migration/deployment', $output);
    }

    /**
     * Scenario 8: Staging host unavailable -> Fail without production fallback
     */
    public function test_scenario_08_fails_when_staging_database_is_unreachable_without_production_fallback(): void
    {
        // Direct to a non-existent port on localhost where DB is guaranteed unreachable
        $exitCode = Artisan::call('deploy:preflight', [
            '--target' => 'staging',
            '--env-name' => 'staging',
            '--db-name' => 'vista_crm_staging',
            '--db-host' => '127.0.0.1',
            '--db-user' => 'vista_crm_staging_user',
            '--db-pass' => 'ValidStagingPass#2026',
            '--check-database' => true,
        ]);

        // When DB connection fails or connected DB does not match, it must fail with 1
        // and NEVER attempt to connect to production or fallback.
        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('FAIL:', $output);
        $this->assertStringNotContainsString('31.97.225.172', $output);
        $this->assertStringNotContainsString('vistabound_crm', $output);
    }

    /**
     * Test PHPUnit Invariant 9 Safety Assertion
     */
    public function test_phpunit_safety_guard_blocks_prohibited_database(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("SAFETY VIOLATION: Test suite attempted to connect to protected database 'vistabound_crm'");

        config(['database.connections.mysql.database' => 'vistabound_crm']);
        $this->assertSafeTestDatabase();
    }
}
