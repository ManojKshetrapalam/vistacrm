#!/usr/bin/env bash

# Vistabound CRM - Hardened Remote SSH Staging Deployment Script
# Usage: ./scripts/deploy-remote.sh --target=staging <user@staging-host> <ssh_port> <remote_path> [--check-only]
# Enforces AGENTS.md Invariant 9: Permanent Production Database Protection Rule

set -euo pipefail

TARGET=""
CHECK_ONLY=false
SSH_TARGET=""
SSH_PORT="22"
REMOTE_PATH=""

# Prohibited production identifiers (IPs, domains, paths)
PROHIBITED_HOSTS=("31.97.225.172" "vistaboundintegrated.com" "crm.vistaboundintegrated.com" "www.vistaboundintegrated.com")
PROHIBITED_PATHS=("/public_html/CRM" "/domains/vistaboundintegrated.com/crm-app")
APPROVED_STAGING_HOSTS=("127.0.0.1" "localhost" "staging.myworks.sbs" "myworks.sbs")

# Parse arguments
POSITIONAL_ARGS=()
for arg in "$@"; do
  case $arg in
    --target=*)
      TARGET="${arg#*=}"
      ;;
    --check-only)
      CHECK_ONLY=true
      ;;
    --help|-h)
      echo "Usage: $0 --target=staging <user@staging-host> <ssh_port> <remote_path> [--check-only]"
      echo "  --target=staging : Required explicit deployment target environment."
      echo "  --check-only     : Run remote pre-flight checks without uploading or deploying."
      exit 0
      ;;
    *)
      POSITIONAL_ARGS+=("$arg")
      ;;
  esac
done

if [ ${#POSITIONAL_ARGS[@]} -ge 1 ]; then
  SSH_TARGET="${POSITIONAL_ARGS[0]}"
fi
if [ ${#POSITIONAL_ARGS[@]} -ge 2 ]; then
  SSH_PORT="${POSITIONAL_ARGS[1]}"
fi
if [ ${#POSITIONAL_ARGS[@]} -ge 3 ]; then
  REMOTE_PATH="${POSITIONAL_ARGS[2]}"
fi

echo "==============================================================="
echo "  Vistabound CRM - Remote Staging Deployment Gate (Invariant 9)"
echo "==============================================================="

# 1. Target Environment Check
if [ -z "${TARGET}" ]; then
  echo "[-] ERROR: Missing required '--target' option."
  echo "[-] Deployment target must be explicitly specified as '--target=staging'."
  exit 1
fi

if [ "${TARGET}" != "staging" ]; then
  echo "[-] ERROR: Invalid deployment target '${TARGET}'."
  echo "[-] Remote deployment is strictly restricted to 'staging'. Production is OUT OF SCOPE."
  exit 1
fi

if [ -z "${SSH_TARGET}" ] || [ -z "${REMOTE_PATH}" ]; then
  echo "[-] ERROR: Missing required parameters."
  echo "Usage: $0 --target=staging <user@staging-host> <ssh_port> <remote_path> [--check-only]"
  exit 1
fi

# 2. Extract Hostname / IP from SSH_TARGET
REMOTE_HOST="${SSH_TARGET#*@}"

# 3. Production Blacklist Inspection
for badHost in "${PROHIBITED_HOSTS[@]}"; do
  if [[ "${REMOTE_HOST}" == *"${badHost}"* ]]; then
    echo "[-] CRITICAL SAFETY VIOLATION: SSH target host '${REMOTE_HOST}' matches prohibited production host '${badHost}'!"
    echo "[-] Invariant 9 strictly forbids deployment tooling against production. Aborting."
    exit 1
  fi
done

for badPath in "${PROHIBITED_PATHS[@]}"; do
  if [[ "${REMOTE_PATH}" == *"${badPath}"* ]]; then
    echo "[-] CRITICAL SAFETY VIOLATION: Remote path '${REMOTE_PATH}' matches prohibited production path '${badPath}'!"
    echo "[-] Invariant 9 strictly blocks execution."
    exit 1
  fi
done

# 4. Approved Staging Host Allowlist
ALLOWLIST_PASSED=false
if [ -n "${STAGING_HOST_ALLOWLIST:-}" ]; then
  IFS=',' read -ra CUSTOM_ALLOWLIST <<< "${STAGING_HOST_ALLOWLIST}"
  for allowed in "${CUSTOM_ALLOWLIST[@]}"; do
    if [[ "${REMOTE_HOST}" == *"${allowed}"* ]]; then
      ALLOWLIST_PASSED=true
      break
    fi
  done
else
  for allowed in "${APPROVED_STAGING_HOSTS[@]}"; do
    if [[ "${REMOTE_HOST}" == *"${allowed}"* ]]; then
      ALLOWLIST_PASSED=true
      break
    fi
  done
fi

if [ "${ALLOWLIST_PASSED}" != true ]; then
  echo "[-] ERROR: Remote host '${REMOTE_HOST}' is not in the approved staging host allowlist."
  echo "[-] Approved hosts: ${APPROVED_STAGING_HOSTS[*]}"
  exit 1
fi

echo "[+] Staging host verified against allowlist: ${REMOTE_HOST}"
echo "[+] Remote staging path: ${REMOTE_PATH}"

# 5. Remote Environment Pre-Flight Verification over SSH
echo "==> [1/5] Executing remote environment pre-flight check..."
ssh -p "${SSH_PORT}" "${SSH_TARGET}" bash -c "'
  set -euo pipefail
  if [ ! -f \"${REMOTE_PATH}/.env\" ]; then
    echo \"[-] Remote .env file not found at ${REMOTE_PATH}/.env\"
    exit 1
  fi

  REMOTE_ENV=\$(grep -E \"^APP_ENV=\" \"${REMOTE_PATH}/.env\" | cut -d \"=\" -f2 | tr -d \" \\\"\" | tr \"[:upper:]\" \"[:lower:]\" || true)
  if [ \"\${REMOTE_ENV}\" = \"production\" ]; then
    echo \"[-] CRITICAL SAFETY VIOLATION: Remote APP_ENV is production!\"
    exit 1
  fi
  if [ \"\${REMOTE_ENV}\" != \"staging\" ]; then
    echo \"[-] ERROR: Remote APP_ENV is \${REMOTE_ENV}. Expected staging.\"
    exit 1
  fi

  REMOTE_DB=\$(grep -E \"^DB_DATABASE=\" \"${REMOTE_PATH}/.env\" | cut -d \"=\" -f2 | tr -d \" \\\"\" || true)
  if [[ \"\${REMOTE_DB}\" =~ ^(vistabound_crm|crm_prod|production|database\.sqlite)$ ]]; then
    echo \"[-] CRITICAL SAFETY VIOLATION: Remote DB matches prohibited production database!\"
    exit 1
  fi
  if [[ ! \"\${REMOTE_DB}\" =~ ^(vista_crm_staging|vistabound_crm_staging)$ ]]; then
    echo \"[-] ERROR: Remote DB \${REMOTE_DB} is not an approved staging database.\"
    exit 1
  fi

  echo \"[+] Remote pre-flight check passed: APP_ENV=\${REMOTE_ENV}, DB=\${REMOTE_DB}\"
'"

if [ "${CHECK_ONLY}" = true ]; then
  echo "==============================================================="
  echo "  Remote pre-flight checks passed successfully. (--check-only) "
  echo "==============================================================="
  exit 0
fi

# 6. Local Staging Build Verification
echo "==> [2/5] Building local assets..."
npm run build

# 7. Packaging Staging Release
echo "==> [3/5] Packaging staging release archive..."
RELEASE_ARCHIVE="/tmp/vistabound-staging-release-$(date +%Y%m%d%H%M%S).tar.gz"
tar --exclude='.git' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='android' \
    --exclude='database/database.sqlite*' \
    --exclude='*.sqlite*' \
    --exclude='storage/backups' \
    --exclude='storage/logs/*.log' \
    --exclude='test-results' \
    --exclude='release' \
    --exclude='.DS_Store' \
    --exclude='._*' \
    --exclude='.phpunit.result.cache' \
    -czf "${RELEASE_ARCHIVE}" .

# 8. Upload Archive to Remote Staging Host
echo "==> [4/5] Uploading release archive to ${SSH_TARGET}:${REMOTE_PATH}..."
ssh -p "${SSH_PORT}" "${SSH_TARGET}" "mkdir -p ${REMOTE_PATH}/storage/framework/{cache,sessions,views} ${REMOTE_PATH}/storage/logs ${REMOTE_PATH}/bootstrap/cache"
scp -P "${SSH_PORT}" "${RELEASE_ARCHIVE}" "${SSH_TARGET}:${REMOTE_PATH}/staging-release.tar.gz"
rm -f "${RELEASE_ARCHIVE}"

# 9. Extract and Execute Staging Migrations
echo "==> [5/5] Extracting release and executing staging migrations..."
ssh -p "${SSH_PORT}" "${SSH_TARGET}" bash -c "'
  set -euo pipefail
  cd ${REMOTE_PATH}
  tar -xzf staging-release.tar.gz
  rm -f staging-release.tar.gz

  chmod -R 775 storage bootstrap/cache

  PHP_BIN=\"/opt/alt/php84/usr/bin/php\"
  if [ ! -f \"\$PHP_BIN\" ]; then
    PHP_BIN=\"php\"
  fi

  if [ -f artisan ]; then
    # Run preflight verification command on remote
    \$PHP_BIN artisan deploy:preflight --target=staging
    \$PHP_BIN artisan migrate --force
    \$PHP_BIN artisan config:clear
    \$PHP_BIN artisan route:clear
    \$PHP_BIN artisan view:clear
  fi
'"

echo "==============================================================="
echo "  Remote Staging Deployment completed successfully!            "
echo "==============================================================="
