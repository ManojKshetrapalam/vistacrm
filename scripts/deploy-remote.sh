#!/usr/bin/env bash

# Vistabound CRM - Remote SSH Deployment Script
# Usage: ./scripts/deploy-remote.sh [user@host] [remote_port] [remote_path]
# Example: ./scripts/deploy-remote.sh u123456789@31.97.225.172 65002 /home/u123456789/domains/vistaboundintegrated.com/public_html/CRM

set -euo pipefail

SSH_TARGET="${1:-${DEPLOY_SSH_TARGET:-}}"
SSH_PORT="${2:-${DEPLOY_SSH_PORT:-65002}}"
REMOTE_PATH="${3:-${DEPLOY_REMOTE_PATH:-}}"

if [ -z "${SSH_TARGET}" ] || [ -z "${REMOTE_PATH}" ]; then
  echo "Usage: $0 <user@host> <port> <remote_path>"
  echo "Example: $0 u123456789@31.97.225.172 65002 /home/u123456789/domains/vistaboundintegrated.com/public_html/CRM"
  exit 1
fi

echo "==> [1/5] Verifying local production build..."
npm run build
php artisan test

echo "==> [2/5] Packaging application release (excluding .env, git, node_modules, sqlite databases, android)..."
RELEASE_ARCHIVE="/tmp/vistabound-crm-release-$(date +%Y%m%d%H%M%S).tar.gz"
tar --exclude='.git' \
    --exclude='.env' \
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

echo "==> [3/5] Uploading release archive to ${SSH_TARGET}:${REMOTE_PATH}..."
ssh -p "${SSH_PORT}" "${SSH_TARGET}" "mkdir -p ${REMOTE_PATH}/storage/framework/{cache,sessions,views} ${REMOTE_PATH}/storage/logs ${REMOTE_PATH}/bootstrap/cache"
scp -P "${SSH_PORT}" "${RELEASE_ARCHIVE}" "${SSH_TARGET}:${REMOTE_PATH}/release.tar.gz"

echo "==> [4/5] Extracting release and preserving production environment on server..."
ssh -p "${SSH_PORT}" "${SSH_TARGET}" bash -c "'
  set -e
  cd ${REMOTE_PATH}
  tar -xzf release.tar.gz
  rm -f release.tar.gz

  # Set correct permissions
  chmod -R 775 storage bootstrap/cache

  # Run migrations using PHP 8.4 runtime
  PHP_BIN=\"/opt/alt/php84/usr/bin/php\"
  if [ ! -f \"\$PHP_BIN\" ]; then
    PHP_BIN=\"php\"
  fi

  if [ -f artisan ]; then
    \$PHP_BIN artisan migrate --force
    \$PHP_BIN artisan config:clear
    \$PHP_BIN artisan route:clear
    \$PHP_BIN artisan view:clear
  fi
'"

rm -f "${RELEASE_ARCHIVE}"
echo "==> [5/5] Remote deployment completed successfully!"
