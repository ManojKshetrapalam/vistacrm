<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prohibited production and staging databases for PHPUnit test execution.
     */
    protected const PROHIBITED_TEST_DATABASES = [
        'vistabound_crm',
        'crm_prod',
        'production',
        'crm_production',
        'vista_crm_staging',
        'vistabound_crm_staging',
    ];

    /**
     * Prohibited production hosts for PHPUnit test execution.
     */
    protected const PROHIBITED_TEST_HOSTS = [
        '31.97.225.172',
        'vistaboundintegrated.com',
        'crm.vistaboundintegrated.com',
        'www.vistaboundintegrated.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSafeTestDatabase();
    }

    /**
     * Enforce AGENTS.md Invariant 9: Prevent accidental test execution
     * against production or staging databases.
     */
    protected function assertSafeTestDatabase(): void
    {
        $appEnv = strtolower(trim((string)app()->environment()));
        if ($appEnv === 'production') {
            throw new \RuntimeException("SAFETY VIOLATION: Test suite executed with APP_ENV='production'! Invariant 9 forbids running tests in production.");
        }

        $connection = config('database.default', 'mysql');
        $dbName = strtolower(trim((string)config("database.connections.{$connection}.database")));
        $dbHost = strtolower(trim((string)config("database.connections.{$connection}.host")));

        foreach (self::PROHIBITED_TEST_DATABASES as $prohibitedDb) {
            if ($dbName === $prohibitedDb || str_contains($dbName, $prohibitedDb)) {
                throw new \RuntimeException("SAFETY VIOLATION: Test suite attempted to connect to protected database '{$dbName}'! Invariant 9 prohibits test execution against production or staging.");
            }
        }

        foreach (self::PROHIBITED_TEST_HOSTS as $prohibitedHost) {
            if ($dbHost === $prohibitedHost || str_contains($dbHost, $prohibitedHost)) {
                throw new \RuntimeException("SAFETY VIOLATION: Test suite attempted to connect to prohibited host '{$dbHost}'! Invariant 9 prohibits test execution against production.");
            }
        }
    }
}
