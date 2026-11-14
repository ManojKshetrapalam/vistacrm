-- ==============================================================================
-- Vistabound CRM — Staging Database Provisioning Script
-- Target: Dedicated Staging Database Server (Local or Isolated VPS)
-- Invariant 9 Guard: NEVER execute against production host 31.97.225.172
-- ==============================================================================

-- 1. Create Dedicated Staging Database Schema
CREATE DATABASE IF NOT EXISTS vista_crm_staging 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- 2. Create Dedicated Staging User with Scoped Privileges
-- Note: Replace '<STRONG_STAGING_PASSWORD>' with a generated secure secret prior to execution.
CREATE USER IF NOT EXISTS 'vista_crm_staging_user'@'localhost' IDENTIFIED BY '<STRONG_STAGING_PASSWORD>';
CREATE USER IF NOT EXISTS 'vista_crm_staging_user'@'127.0.0.1' IDENTIFIED BY '<STRONG_STAGING_PASSWORD>';

-- 3. Grant Least-Privilege Access Restricted Exclusively to Staging Database
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, LOCK TABLES 
    ON vista_crm_staging.* 
    TO 'vista_crm_staging_user'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, LOCK TABLES 
    ON vista_crm_staging.* 
    TO 'vista_crm_staging_user'@'127.0.0.1';

-- 4. Apply Privilege Table Changes
FLUSH PRIVILEGES;

-- Verification Query:
-- SELECT user, host FROM mysql.user WHERE user = 'vista_crm_staging_user';
