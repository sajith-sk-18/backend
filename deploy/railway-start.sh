#!/usr/bin/env bash
#
# Container entrypoint (see ../Dockerfile). Runs on every deploy and every restart, so
# every step here must be idempotent.
#
set -euo pipefail

# Railway assigns the port at runtime and it is NOT 80. Apache must be told, or the
# healthcheck fails with no useful error.
PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s!<VirtualHost \*:[0-9]+>!<VirtualHost *:${PORT}>!" /etc/apache2/sites-available/000-default.conf

# A mounted volume starts EMPTY and shadows whatever the image had at this path, so
# Laravel's expected directory tree has to be recreated rather than assumed.
mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# public/storage is gitignored, so it never exists in a fresh build. Without it every
# uploaded product image 404s while sitting perfectly safe on disk.
php artisan storage:link --force || true

# Wait for the database: on a cold start the app container is often ready before the
# managed MySQL service accepts connections, and migrate would fail the whole deploy.
for i in $(seq 1 30); do
  if php -r 'exit(0);' && php artisan db:monitor >/dev/null 2>&1; then
    break
  fi
  echo "waiting for database (${i}/30)"
  sleep 2
done

echo "==> Migrating"
php artisan migrate --force

# Seed only when the catalogue is empty, so a redeploy never duplicates products.
if [[ "$(php artisan tinker --execute='echo \App\Models\Product::count();' 2>/dev/null | tail -1)" == "0" ]]; then
  echo "==> Empty database, seeding"
  php artisan db:seed --force || true
fi

echo "==> Caching config and routes"
php artisan config:clear
php artisan config:cache
php artisan route:cache

echo "==> Apache on :${PORT}"
exec apache2-foreground
