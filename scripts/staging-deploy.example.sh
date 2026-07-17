#!/usr/bin/env bash
# Example staging deployment helper — customize paths on the server.
# Do NOT commit real credentials or server-specific secrets.
#
# Usage:
#   cp scripts/staging-deploy.example.sh scripts/staging-deploy.sh
#   chmod +x scripts/staging-deploy.sh
#   ./scripts/staging-deploy.sh

set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/torino-moda-style}"
RELEASE_REF="${RELEASE_REF:-main}"

cd "$APP_ROOT"

echo "==> Pulling $RELEASE_REF"
git fetch --all --prune
git checkout "$RELEASE_REF"
git pull --ff-only

echo "==> Installing dependencies"
composer install --no-dev --prefer-dist --optimize-autoloader

echo "==> Configuration checks"
php artisan app:production-check || true
php artisan payments:thawani-check || true

echo "==> Maintenance mode"
php artisan down --secret="${MAINTENANCE_SECRET:-staging-bypass}"

echo "==> Migrations"
php artisan migrate --force

echo "==> Cache"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Storage link"
php artisan storage:link || true

echo "==> Scheduler heartbeat"
php artisan ops:scheduler-heartbeat

echo "==> Bring app up"
php artisan up

echo "==> Smoke test"
php artisan app:smoke-test --with-auth

echo "Staging deploy complete."
