#!/usr/bin/env bash

# Vistabound CRM - Automated VPS Deployment Script
# Usage: ./scripts/deploy.sh

set -euo pipefail

echo "==> [1/6] Navigating to application root..."
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${APP_DIR}"

echo "==> [2/6] Putting application into maintenance mode..."
php artisan down --render="errors::503" || true

echo "==> [3/6] Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> [4/6] Running database migrations..."
php artisan migrate --force

echo "==> [5/6] Building frontend assets..."
npm ci
npm run build

echo "==> [6/6] Caching configurations & restarting services..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue worker via supervisor if installed
if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl restart vistabound-worker:* || true
fi

php artisan up

echo "==> Deployment of Vistabound CRM completed successfully!"
