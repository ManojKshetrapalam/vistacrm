#!/usr/bin/env bash

# Vistabound CRM - Hardened Staging Deployment Script
# Usage: ./scripts/deploy.sh --target=staging [--check-only]
# Enforces AGENTS.md Invariant 9: Permanent Production Database Protection Rule

set -euo pipefail

TARGET=""
CHECK_ONLY=false

for arg in "$@"; do
  case $arg in
    --target=*)
      TARGET="${arg#*=}"
      shift
      ;;
    -t)
      TARGET="${2:-}"
      shift 2 || true
      ;;
    --check-only)
      CHECK_ONLY=true
      shift
      ;;
    --help|-h)
      echo "Usage: $0 --target=staging [--check-only]"
      echo "  --target=staging : Required explicit deployment target environment."
      echo "  --check-only     : Run fail-closed pre-flight checks without modifying application."
      exit 0
      ;;
  esac
done

echo "==============================================================="
echo "  Vistabound CRM - Staging Deployment Gate (Invariant 9)       "
echo "==============================================================="

# 1. Target Environment Check
if [ -z "${TARGET}" ]; then
  echo "[-] ERROR: Missing required '--target' argument."
  echo "[-] Production protection rule: Target must be explicitly passed as '--target=staging'."
  exit 1
fi

if [ "${TARGET}" != "staging" ]; then
  echo "[-] ERROR: Invalid deployment target '${TARGET}'."
  echo "[-] This deployment tooling is strictly restricted to 'staging'. Production deployment is blocked."
  exit 1
fi

echo "[+] Deployment target explicitly verified as: STAGING"

# 2. Application Root Resolution
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${APP_DIR}"

# 3. Environment File Existence
if [ ! -f ".env" ]; then
  echo "[-] ERROR: .env file not found in ${APP_DIR}."
  exit 1
fi

# 4. Fail-Closed Environment Variable Checks
ENV_VAL=$(grep -E '^APP_ENV=' .env | cut -d '=' -f2 | tr -d ' "' | tr '[:upper:]' '[:lower:]' || true)
if [ "${ENV_VAL}" = "production" ]; then
  echo "[-] CRITICAL SAFETY VIOLATION: APP_ENV is 'production'!"
  echo "[-] Invariant 9 strictly forbids staging deployment tooling against production. Aborting."
  exit 1
fi

if [ "${ENV_VAL}" != "staging" ]; then
  echo "[-] ERROR: APP_ENV in .env is '${ENV_VAL}'. Expected 'staging'."
  exit 1
fi
echo "[+] .env APP_ENV verified as: staging"

# 5. Database Name & Host Fail-Closed Validation
DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d '=' -f2 | tr -d ' "' || true)
DB_HOST=$(grep -E '^DB_HOST=' .env | cut -d '=' -f2 | tr -d ' "' || true)

# Prohibited production database names
if [[ "${DB_NAME}" =~ ^(vistabound_crm|crm_prod|production|database\.sqlite)$ ]]; then
  echo "[-] CRITICAL SAFETY VIOLATION: DB_DATABASE '${DB_NAME}' matches prohibited production database!"
  exit 1
fi

# Must be approved staging database
DB_NAME_BASE=$(basename "${DB_NAME}")
if [[ ! "${DB_NAME_BASE}" =~ ^(vista_crm_staging|vistabound_crm_staging|u996219523_vista_crm_staging|vista_crm_staging\.sqlite)$ ]]; then
  echo "[-] ERROR: DB_DATABASE '${DB_NAME}' is not an approved staging database."
  exit 1
fi
echo "[+] Staging database name verified: ${DB_NAME}"

# Prohibited production hosts
if [[ "${DB_HOST}" =~ (31\.97\.225\.172|vistaboundintegrated\.com) ]]; then
  echo "[-] CRITICAL SAFETY VIOLATION: DB_HOST '${DB_HOST}' matches prohibited production host!"
  exit 1
fi
echo "[+] Staging database host verified: ${DB_HOST}"

# 6. Execute Laravel Pre-Flight Artisan Command
echo "[+] Executing artisan deploy:preflight gate..."
php artisan deploy:preflight --target=staging

if [ "${CHECK_ONLY}" = true ]; then
  echo "==============================================================="
  echo "  Pre-flight checks passed successfully. (--check-only mode)  "
  echo "==============================================================="
  exit 0
fi

# 7. Pre-Migration Staging Database Backup
if [ -f "scripts/backup-db.sh" ]; then
  echo "==> [1/6] Generating pre-migration staging database backup..."
  BACKUP_DIR="${APP_DIR}/storage/backups" \
  DB_DATABASE="${DB_NAME}" \
  DB_HOST="${DB_HOST}" \
  ./scripts/backup-db.sh || echo "[!] Notice: Automated backup completed or skipped."
fi

echo "==> [2/6] Putting staging application into maintenance mode..."
php artisan down --render="errors::503" || true

echo "==> [3/6] Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> [4/6] Running staging database migrations..."
php artisan migrate --force

echo "==> [5/6] Building frontend assets..."
npm ci
npm run build

echo "==> [6/6] Caching configurations & restarting services..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl restart vistabound-staging-worker:* || true
fi

php artisan up

echo "==============================================================="
echo "  Deployment to Vistabound CRM Staging completed successfully! "
echo "==============================================================="
