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
db_ready=0
for i in $(seq 1 30); do
  if php artisan db:monitor >/dev/null 2>&1; then
    db_ready=1
    break
  fi
  echo "waiting for database (${i}/30)"
  sleep 2
done
if [[ "$db_ready" != "1" ]]; then
  echo "ERROR: database not reachable after 60s -- check the DB_* variables reference the MySQL service" >&2
  exit 1
fi

echo "==> Migrating"
php artisan migrate --force

# Seed only when the catalogue is empty, so a redeploy never duplicates products.
# deploy/db-needs-seed.php rather than `artisan tinker --execute`: psysh swallows exit()
# and always returns 1, so a tinker-based check reports "populated" even on a fresh
# database and the catalogue would silently never be seeded.
#   0 = empty, seed it   1 = already populated   2 = the check itself failed
set +e
php deploy/db-needs-seed.php
seed_rc=$?
set -e
case "$seed_rc" in
  0)
    echo "==> Empty database, seeding"
    php artisan db:seed --force
    ;;
  1)
    echo "==> Catalogue already populated, not seeding"
    ;;
  *)
    # Never treat an unreadable database as "already populated" -- that would boot a
    # working API serving an empty catalogue, which looks like a frontend bug.
    echo "ERROR: could not determine whether the database needs seeding (rc=${seed_rc})" >&2
    exit 1
    ;;
esac

echo "==> Caching config and routes"
php artisan config:clear
php artisan config:cache
php artisan route:cache

# Apache refuses to start at all if more than one MPM is loaded:
#   AH00534: apache2: Configuration error: More than one MPM loaded.
# Doing this at BUILD time with a2dismod proved unreliable, so force it here where it
# always applies to the container that is actually about to run. mod_php requires prefork.
echo "==> MPM before: $(ls /etc/apache2/mods-enabled/ | grep -i mpm | tr '
' ' ')"
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf       /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
if [[ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]]; then
  ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
  ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
fi
echo "==> MPM after:  $(ls /etc/apache2/mods-enabled/ | grep -i mpm | tr '
' ' ')"

# Surface the real reason rather than a bare exit code if the config is still invalid.
apache2ctl configtest || true

echo "==> Apache on :${PORT}"
exec apache2-foreground
