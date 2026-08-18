#!/usr/bin/env bash
#
# Provision the Fluro Tech API on a fresh Ubuntu 24.04 VM (Oracle Always Free, or any VPS).
# Implements DEPLOY-FREE.md part 2 in one idempotent pass -- safe to re-run.
#
# Usage, from inside the cloned repo's backend/ directory:
#
#     sudo DOMAIN=flurotech.duckdns.org DB_PASS='a-strong-password' bash deploy/setup-api.sh
#
# Optional:
#     SEED=0          skip --seed (use when importing laptop_store.sql instead)
#     SKIP_TLS=1      skip certbot (e.g. DNS not pointed at this box yet)
#     DB_NAME/DB_USER override the defaults below
#
set -euo pipefail

DOMAIN="${DOMAIN:-}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-laptop_store}"
DB_USER="${DB_USER:-laptop}"
SEED="${SEED:-1}"
SKIP_TLS="${SKIP_TLS:-0}"

die() { echo "ERROR: $*" >&2; exit 1; }
step() { echo; echo "==> $*"; }

[[ $EUID -eq 0 ]] || die "run with sudo"
[[ -n "$DOMAIN" ]] || die "DOMAIN is required, e.g. DOMAIN=flurotech.duckdns.org"
[[ -n "$DB_PASS" ]] || die "DB_PASS is required"
[[ -f artisan && -f composer.json ]] || die "run this from the backend/ directory"

APP_DIR="$(pwd)"
# The user who owns the checkout, not root -- artisan runs as them so new files
# do not end up root-owned.
OWNER="$(stat -c '%U' "$APP_DIR")"

step "Opening ports 80/443 on the instance firewall"
# Oracle's Ubuntu images drop everything except SSH. This is only HALF the job:
# ingress rules must ALSO be added in the cloud Security List via the web console.
for port in 80 443; do
  if ! iptables -C INPUT -m state --state NEW -p tcp --dport "$port" -j ACCEPT 2>/dev/null; then
    iptables -I INPUT 6 -m state --state NEW -p tcp --dport "$port" -j ACCEPT
    echo "    opened $port"
  else
    echo "    $port already open"
  fi
done
command -v netfilter-persistent >/dev/null && netfilter-persistent save >/dev/null || \
  echo "    NOTE: netfilter-persistent missing -- rules will not survive reboot"

step "Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq nginx mysql-server git unzip curl composer \
  php-fpm php-mysql php-mbstring php-xml php-curl php-zip php-gd php-bcmath \
  certbot python3-certbot-nginx

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
echo "    PHP $PHP_VER"
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
  || die "PHP 8.2+ required (composer.json says ^8.2); this box has $PHP_VER. Use Ubuntu 24.04."
[[ -S "$FPM_SOCK" ]] || die "php-fpm socket not found at $FPM_SOCK"

step "Creating database and user"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

step "Installing PHP dependencies"
sudo -u "$OWNER" composer install --no-dev --optimize-autoloader --no-interaction

step "Writing .env"
# Never overwrite an existing .env -- it holds APP_KEY, and regenerating that
# invalidates every encrypted value and session already in the database.
if [[ -f .env ]]; then
  echo "    .env exists, leaving it alone"
else
  sudo -u "$OWNER" cp .env.example .env
  sudo -u "$OWNER" tee -a /dev/null >/dev/null <<< ""
  set_env() { sudo -u "$OWNER" sed -i "s#^${1}=.*#${1}=${2}#" .env; }
  set_env APP_ENV production
  set_env APP_DEBUG false
  set_env APP_URL "https://${DOMAIN}"
  set_env DB_CONNECTION mysql
  set_env DB_HOST 127.0.0.1
  set_env DB_PORT 3306
  set_env DB_DATABASE "${DB_NAME}"
  set_env DB_USERNAME "${DB_USER}"
  set_env DB_PASSWORD "${DB_PASS}"
  sudo -u "$OWNER" php artisan key:generate --force
  echo "    written"
fi

step "Running migrations"
if [[ "$SEED" == "1" ]]; then
  sudo -u "$OWNER" php artisan migrate --force --seed
else
  sudo -u "$OWNER" php artisan migrate --force
  echo "    skipped seeding (SEED=0) -- import laptop_store.sql yourself"
fi

step "Linking storage and caching config"
sudo -u "$OWNER" php artisan storage:link || true
sudo -u "$OWNER" php artisan config:cache
sudo -u "$OWNER" php artisan route:cache

# Laravel writes logs, cached views and UPLOADED PRODUCT IMAGES here, as php-fpm's user.
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;

step "Configuring nginx"
cat > /etc/nginx/sites-available/laptop-store <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;          # product image uploads

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${FPM_SOCK};
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sfn /etc/nginx/sites-available/laptop-store /etc/nginx/sites-enabled/laptop-store
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

step "Smoke test over HTTP"
code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${DOMAIN}" http://127.0.0.1/api/categories || true)"
[[ "$code" == "200" ]] || die "expected 200 from /api/categories, got ${code}. Check storage/logs/laravel.log"
echo "    200 OK"

if [[ "$SKIP_TLS" == "1" ]]; then
  step "Skipping TLS (SKIP_TLS=1)"
else
  step "Issuing the TLS certificate"
  # HTTP-01 challenge: ${DOMAIN} must already resolve to THIS machine's public IP
  # and port 80 must be reachable from the internet (cloud Security List included).
  certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos --redirect \
    --register-unsafely-without-email
  code="$(curl -s -o /dev/null -w '%{http_code}' "https://${DOMAIN}/api/categories" || true)"
  [[ "$code" == "200" ]] || die "HTTPS smoke test returned ${code}"
  echo "    https://${DOMAIN}/api/categories -> 200"
fi

cat <<DONE

======================================================================
API is live:  https://${DOMAIN}/api

Next:
  1. Set VITE_API_URL=https://${DOMAIN}/api in BOTH Vercel projects.
  2. After Vercel assigns the domains, add them to config/cors.php
     (allowed_origins + the *.vercel.app pattern), then:
         php artisan config:cache
  3. Log in and CHANGE the seeded password: admin@example.com / password

Redeploys: bash deploy/update.sh
======================================================================
DONE
