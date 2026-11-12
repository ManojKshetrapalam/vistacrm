#Requires -Version 5.1
<#
.SYNOPSIS
    Vistabound CRM - Automated Windows Local Development Environment Bootstrap
.DESCRIPTION
    Safely bootstraps dependencies, local MySQL database, migrations, repeatable seeders,
    and frontend builds for Vistabound CRM on Windows.
    Guards existing .env files and preserves existing database data unless explicitly requested.
.PARAMETER ResetDatabase
    When specified, drops and recreates the local development database. CAUTION: Destructive to local data.
.PARAMETER ForceEnv
    When specified, overwrites an existing .env with fresh development defaults.
#>

[CmdletBinding()]
param (
    [switch]$ResetDatabase = $false,
    [switch]$ForceEnv = $false
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  Vistabound CRM - Local Development Setup (Windows 11)   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Project Directory: $ProjectRoot" -ForegroundColor Gray

# 1. Verify Prerequisites
Write-Host "`n[1/7] Verifying System Prerequisites..." -ForegroundColor Yellow

function Assert-Tool($command, $name) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        Write-Error "Required tool '$name' ($command) was not found in PATH. Please install it before continuing."
        exit 1
    }
}

Assert-Tool "php" "PHP Runtime"
Assert-Tool "composer" "Composer Package Manager"
Assert-Tool "mysql" "MySQL Client"
Assert-Tool "node" "Node.js Runtime"
Assert-Tool "npm" "Node Package Manager"

$phpVersion = (php -r "echo PHP_VERSION;").Trim()
$composerVersion = (composer -V).Trim()
$nodeVersion = (node -v).Trim()
$npmVersion = (npm -v).Trim()

Write-Host "  ✓ PHP: $phpVersion" -ForegroundColor Green
Write-Host "  ✓ Composer: $composerVersion" -ForegroundColor Green
Write-Host "  ✓ Node.js: $nodeVersion" -ForegroundColor Green
Write-Host "  ✓ npm: $npmVersion" -ForegroundColor Green

# 2. Local MySQL Provisioning
Write-Host "`n[2/7] Checking MySQL Local Database..." -ForegroundColor Yellow
$DbName = "vista_crm_local"
$DbUser = "vista_crm_user"
$DbPass = "VbLocal#Db2026_x89q"

# Test MySQL server connectivity on port 3306
$portOpen = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -InformationLevel Quiet
if (-not $portOpen) {
    Write-Error "MySQL Server is not running or port 3306 is not reachable. Please start MySQL Server service."
    exit 1
}

if ($ResetDatabase) {
    Write-Host "  ! -ResetDatabase flag detected. Dropping and recreating '$DbName'..." -ForegroundColor Magenta
    $sqlReset = "DROP DATABASE IF EXISTS $DbName; CREATE DATABASE $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $sqlReset | mysql -u root
} else {
    $sqlInit = @"
CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';
CREATE USER IF NOT EXISTS '$DbUser'@'127.0.0.1' IDENTIFIED BY '$DbPass';
ALTER USER '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';
ALTER USER '$DbUser'@'127.0.0.1' IDENTIFIED BY '$DbPass';
GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'localhost';
GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
    $sqlInit | mysql -u root
}
Write-Host "  ✓ Local database '$DbName' and user '$DbUser' ready." -ForegroundColor Green

# 3. Environment Configuration (.env)
Write-Host "`n[3/7] Configuring Local Environment (.env)..." -ForegroundColor Yellow
$envPath = Join-Path $ProjectRoot ".env"

if ((Test-Path $envPath) -and (-not $ForceEnv)) {
    Write-Host "  ✓ Preserving existing .env file (use -ForceEnv to overwrite)." -ForegroundColor Green
} else {
    Write-Host "  Writing fresh local .env file..." -ForegroundColor Cyan
    $envContent = @"
APP_NAME="Vistabound CRM"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://127.0.0.1:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# MySQL Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DbName
DB_USERNAME=$DbUser
DB_PASSWORD="$DbPass"

# Sessions & Queues
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

QUEUE_CONNECTION=database
CACHE_STORE=database

FILESYSTEM_DISK=local

# Mail Notification Driver
MAIL_MAILER=log
MAIL_FROM_ADDRESS="local@vistaboundintegrated.com"
MAIL_FROM_NAME="\${APP_NAME}"

# Frontend Vite Variables
VITE_APP_NAME="\${APP_NAME}"
VITE_API_BASE_URL=http://127.0.0.1:8000/api
"@
    Set-Content -Path $envPath -Value $envContent -Encoding UTF8
    Write-Host "  ✓ Created new .env file." -ForegroundColor Green
}

# 4. Install PHP Dependencies & App Key
Write-Host "`n[4/7] Installing PHP Dependencies via Composer..." -ForegroundColor Yellow
Push-Location $ProjectRoot
try {
    if (-not (Test-Path "vendor")) {
        composer install --no-interaction --prefer-dist
    } else {
        Write-Host "  ✓ vendor/ directory already exists. Skipping composer install." -ForegroundColor Green
    }

    $currentKey = php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; echo config('app.key');"
    if ([string]::IsNullOrWhiteSpace($currentKey)) {
        Write-Host "  Generating application encryption key..." -ForegroundColor Cyan
        php artisan key:generate
    } else {
        Write-Host "  ✓ Application key already set." -ForegroundColor Green
    }

    # 5. Execute Migrations & Repeatable Seeders
    Write-Host "`n[5/7] Running Database Migrations & Repeatable Seeders..." -ForegroundColor Yellow
    php artisan migrate --force
    php artisan db:seed --force
    Write-Host "  ✓ Migrations and repeatable seeders successfully executed." -ForegroundColor Green

    # 6. Install & Build Frontend
    Write-Host "`n[6/7] Building Frontend & Mobile Assets..." -ForegroundColor Yellow
    if (-not (Test-Path "node_modules")) {
        npm install
    }
    npm run build
    npm run build:mobile
    Write-Host "  ✓ Web and mobile Vite assets built successfully." -ForegroundColor Green

    # 7. Verification & Next Steps
    Write-Host "`n[7/7] Environment Verification & Startup" -ForegroundColor Yellow
    Write-Host "  Local development environment is 100% READY!" -ForegroundColor Green
    Write-Host "`nTo start the web application:" -ForegroundColor Cyan
    Write-Host "  php artisan serve --host=127.0.0.1 --port=8000" -ForegroundColor White
    Write-Host "  URL: http://127.0.0.1:8000" -ForegroundColor White
    Write-Host "`nTo connect Android Emulator:" -ForegroundColor Cyan
    Write-Host "  API Endpoint: http://10.0.2.2:8000/api" -ForegroundColor White
    Write-Host "`nTo connect Physical Android Device on local Wi-Fi:" -ForegroundColor Cyan
    Write-Host "  1. Start server on all interfaces: php artisan serve --host=0.0.0.0 --port=8000" -ForegroundColor White
    Write-Host "  2. Point mobile app to your PC's LAN IP: http://<LAN_IP>:8000/api" -ForegroundColor White
    Write-Host "`nDefault Development Accounts:" -ForegroundColor Cyan
    Write-Host "  - Admin: admin@vistaboundintegrated.com (Username: admin / Password: Admin123 or Vistabound@2026)" -ForegroundColor White
    Write-Host "  - Sales Exec: ananya@vistaboundintegrated.com (Username: ananya / Password: Vistabound@2026)" -ForegroundColor White
    Write-Host "  - Sales Exec: rohit@vistaboundintegrated.com (Username: rohit / Password: Vistabound@2026)" -ForegroundColor White
}
finally {
    Pop-Location
}
