#!/usr/bin/env bash
#
# Redeploy the API after a git push. Run from the backend/ directory:
#
#     bash deploy/update.sh
#
set -euo pipefail

[[ -f artisan ]] || { echo "run this from the backend/ directory" >&2; exit 1; }

echo "==> Pulling"
git pull --ff-only

echo "==> Dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Migrations"
php artisan migrate --force

echo "==> Rebuilding caches"
# config:clear first: a stale cached config silently keeps serving old values,
# which is the classic "I changed cors.php and nothing happened" trap.
php artisan config:clear
php artisan config:cache
php artisan route:cache

echo "==> Permissions"
# composer and git may have created files as the deploying user; php-fpm needs them.
sudo chown -R www-data:www-data storage bootstrap/cache

echo "==> Reloading php-fpm"
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
sudo systemctl reload "php${PHP_VER}-fpm"

echo "==> Smoke test"
APP_URL="$(grep -E '^APP_URL=' .env | cut -d= -f2-)"
code="$(curl -s -o /dev/null -w '%{http_code}' "${APP_URL}/api/categories" || true)"
[[ "$code" == "200" ]] || { echo "FAILED: ${APP_URL}/api/categories returned ${code}" >&2; exit 1; }
echo "    ${APP_URL}/api/categories -> 200"
echo "Done."
