# Free deployment — Fluro Tech (laptop-store)

Fully free, no trial clock, and everything on real HTTPS:

| Part | Host | Cost |
|---|---|---|
| Laravel API (`backend/`) | **Oracle Cloud "Always Free" VM** + free **DuckDNS** hostname | free indefinitely |
| Customer site + admin panel | **Cloudflare Pages** (one project, both apps) | free |

Result:

```
https://<project>.pages.dev/          → storefront
https://<project>.pages.dev/admin/    → admin panel
https://<you>.duckdns.org/api/...     → Laravel API
```

## Why this combination

- The API **writes uploaded product images to disk** (`ProductController.php:287` →
  `storage/app/public/products/`). That rules out every serverless free tier (Vercel,
  Render, Koyeb, Railway) — their filesystems are ephemeral, so images vanish on redeploy.
  An Oracle VM has a real persistent disk.
- The API **must be HTTPS**. Pages is served over HTTPS, and browsers block plain-HTTP
  XHR from an HTTPS page (mixed content). A bare IP can't get a certificate, hence the
  free DuckDNS hostname — Let's Encrypt will issue for it.
- Pages **rebuilds on git push**, which keeps the prerendered product pages and
  `sitemap.xml` current. That's the one chore the Hostinger route leaves you with.

> **Order matters: deploy the API first.** The customer-site build fetches products from
> the live API to prerender product pages. If the API isn't publicly reachable when
> Pages builds, you get a site with no prerendered product pages and no SEO.

---

## Part 1 — Free hostname (DuckDNS, 2 minutes)

Do this first; you need the hostname while configuring the server.

1. Go to **https://duckdns.org**, sign in with Google/GitHub.
2. Create a subdomain, e.g. `flurotech` → gives you `flurotech.duckdns.org`.
3. Leave the IP blank for now. Keep the page open — you'll paste the VM's IP in Part 2.

---

## Part 2 — Oracle Cloud VM

> **Fast path.** Steps 2.2 to 2.9 are automated by `backend/deploy/setup-api.sh` --
> firewall, packages, database, .env, migrations, permissions, nginx and TLS in one
> idempotent pass. Create the instance (2.1), clone the repo, then:
>
> ```bash
> cd backend
> sudo DOMAIN=flurotech.duckdns.org DB_PASS='a-strong-password' bash deploy/setup-api.sh
> ```
>
> It refuses to overwrite an existing `.env`, verifies PHP is 8.2+, and smoke-tests
> `/api/categories` before and after issuing the certificate. Redeploys later:
> `bash deploy/update.sh`. The manual steps below remain the reference for what it does
> and for troubleshooting -- and **2.1 and the cloud Security List half of 2.2 still have
> to be done in the Oracle web console**, which no script can reach.


### 2.1 Create the instance

1. Sign up at **https://cloud.oracle.com** (a credit card is required for identity
   verification; Always Free resources are not charged). Choose your home region carefully —
   it cannot be changed later.
2. **Compute → Instances → Create instance**
   - **Image:** Ubuntu **24.04** — it ships PHP 8.3, which satisfies the project's `php: ^8.2`.
     (Ubuntu 22.04 ships PHP 8.1 and would need a third-party PPA.)
   - **Shape:** `VM.Standard.A1.Flex` (Ampere ARM) with **1 OCPU / 6 GB** — comfortably
     within Always Free. If ARM capacity is unavailable in your region, use
     `VM.Standard.E2.1.Micro` instead.
   - **SSH keys:** save the private key it offers. You cannot download it later.
3. Note the instance's **public IP address**, then paste it into DuckDNS and press
   **update domain**.
4. Confirm DNS resolves (from your PC):
   ```bash
   nslookup flurotech.duckdns.org
   ```

### 2.2 Open ports — BOTH firewalls

This is the single most common Oracle mistake. There are **two** firewalls and you must
open ports in both, or the site is simply unreachable with no error.

**a) Oracle's cloud firewall:** Instance → *Virtual cloud network* → **Security Lists** →
default list → **Add Ingress Rules**, twice:

| Source CIDR | IP Protocol | Destination Port |
|---|---|---|
| `0.0.0.0/0` | TCP | 80 |
| `0.0.0.0/0` | TCP | 443 |

**b) The VM's own iptables** — Oracle's Ubuntu images block everything except SSH:
```bash
ssh -i /path/to/key.key ubuntu@flurotech.duckdns.org

sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

### 2.3 Install the stack

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip \
  php-fpm php-mysql php-mbstring php-xml php-curl php-zip php-gd php-bcmath \
  composer certbot python3-certbot-nginx

php -v          # expect 8.3.x
```

### 2.4 Create the database

```bash
sudo mysql
```
```sql
CREATE DATABASE laptop_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laptop'@'localhost' IDENTIFIED BY 'CHANGE_ME_strong_password';
GRANT ALL PRIVILEGES ON laptop_store.* TO 'laptop'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2.5 Upload the app

From your **local machine** — push the repo to GitHub, then on the VM:
```bash
sudo mkdir -p /var/www && sudo chown -R ubuntu:ubuntu /var/www
cd /var/www
git clone https://github.com/<you>/<repo>.git laptop-store
cd laptop-store/backend
composer install --no-dev --optimize-autoloader
```

*(No GitHub repo? `scp -i key.key -r backend ubuntu@flurotech.duckdns.org:/var/www/laptop-store/`
— but exclude `vendor/` and `node_modules/` first.)*

### 2.6 Configure Laravel

```bash
cd /var/www/laptop-store/backend
cp .env.example .env
nano .env
```
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://flurotech.duckdns.org

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laptop_store
DB_USERNAME=laptop
DB_PASSWORD=CHANGE_ME_strong_password
```

⚠️ **Do not copy your local `.env`** — it is set to `DB_CONNECTION=sqlite` with a Windows
path (`C:/claude/...`) that cannot work on Linux.

```bash
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache && php artisan route:cache

# Laravel must be able to write these
sudo chown -R www-data:www-data storage bootstrap/cache
```

> Want your existing local data instead of the seeder? Skip `--seed` and import the dump:
> `mysql -u laptop -p laptop_store < /var/www/laptop-store/laptop_store.sql`
> — but check that `laptop_store.sql` is current first; your recent work was on SQLite.

### 2.7 CORS — point at the Pages origin

Edit `backend/config/cors.php`. Replace the localhost dev ports with:

```php
'allowed_origins' => [
    'https://<project>.pages.dev',
],

// Pages gives every branch/preview build its own subdomain; this keeps them working.
'allowed_origins_patterns' => [
    '#^https://[a-z0-9-]+\.<project>\.pages\.dev$#',
],
```
Then `php artisan config:cache`.

*(You'll know the exact `<project>` name after Part 3 — come back and fill it in.)*

### 2.8 nginx

```bash
sudo nano /etc/nginx/sites-available/laptop-store
```
```nginx
server {
    listen 80;
    server_name flurotech.duckdns.org;
    root /var/www/laptop-store/backend/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;          # product image uploads

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/laptop-store /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Smoke test over plain HTTP:
```bash
curl -i http://flurotech.duckdns.org/api/categories     # expect 200 + JSON
```

### 2.9 HTTPS

```bash
sudo certbot --nginx -d flurotech.duckdns.org
```
Choose **redirect HTTP → HTTPS**. Certbot installs the certificate and a renewal timer.

```bash
curl -i https://flurotech.duckdns.org/api/categories     # expect 200 + JSON
```

**The API must return JSON here before you continue.** Everything downstream depends on it.

---

## Part 3 — Cloudflare Pages (both frontends, one project)

One project serves both apps on one domain, reusing the `base: '/admin/'` already set in
`admin-panel/vite.config.js`.

1. Push your repo to GitHub if you haven't.
2. **https://dash.cloudflare.com** → **Workers & Pages → Create → Pages →
   Connect to Git** → pick the repo.
3. Build settings:

   | Field | Value |
   |---|---|
   | Framework preset | **None** |
   | Root directory | *(leave empty — repo root)* |
   | Build command | see below |
   | Build output directory | `customer-site/dist` |

   ```
   cd admin-panel && npm ci && npm run build && cd ../customer-site && npm ci && npm run build && cp -r ../admin-panel/dist ./dist/admin
   ```

   The order is deliberate: Vite **empties** its output directory on build, so the admin
   must be copied in *after* the customer site is built, or it would be deleted.

4. **Environment variables** (Settings → Environment variables → Production). Vite inlines
   these at build time, so they must exist before the first build:

   | Name | Value |
   |---|---|
   | `VITE_API_URL` | `https://flurotech.duckdns.org/api` |
   | `VITE_SITE_URL` | `https://<project>.pages.dev` |
   | `VITE_ADMIN_WHATSAPP` | your number, digits only, country code, no `+` or spaces |
   | `NODE_VERSION` | `20` |

5. **Save and Deploy.** Then go back to Part 2.7, put the real `<project>.pages.dev`
   origin into `config/cors.php`, and re-run `php artisan config:cache`.

### Watch the build log

If you see `[prerender] Could not fetch products…`, the API was not reachable from
Cloudflare's build container. Only static routes were prerendered — product pages have no
SEO. Fix the API, then **Retry deployment**.

### Routing files

Already in the repo, copied into `dist/` by Vite — Pages ignores `.htaccess`, so these do
the equivalent job:

- **`customer-site/public/_redirects`** — SPA fallback, with `/admin/*` routed to the
  admin's own `index.html` *first* (first match wins).
- **`customer-site/public/_headers`** — `X-Robots-Tag: noindex` on `/admin/*` plus
  security headers.
- **`customer-site/public/robots.txt`** — `Disallow: /admin/`. On a shared domain only the
  root `robots.txt` is honoured, so the admin's own copy is ignored by crawlers.

---

## Part 4 — Verify

| Check | Expected |
|---|---|
| `https://flurotech.duckdns.org/api/categories` | JSON |
| `https://<project>.pages.dev/` | storefront, products visible |
| `https://<project>.pages.dev/products/1` | product page, **view source shows real title/description** (proves prerender) |
| `https://<project>.pages.dev/admin/` | admin login |
| `https://<project>.pages.dev/admin/products` | **paste directly in the address bar and reload** — if you get the storefront, `_redirects` is wrong |
| `https://<project>.pages.dev/robots.txt` | contains `Disallow: /admin/` |
| Browser devtools → Network | no CORS errors, no mixed-content warnings |
| Log into admin, upload a product image | image displays, and **still displays after `sudo systemctl restart php8.3-fpm`** |

Seeded admin login: `admin@example.com` / `password` — **change it immediately.**

---

## Redeploying

**Frontend changes:** `git push` → Pages rebuilds automatically. Nothing else to do.

**Products changed and you want fresh SEO:** the prerendered pages are build-time
snapshots, so trigger a rebuild — Pages dashboard → **Retry deployment**, or create a
**Deploy Hook** (Settings → Builds & deployments) and `curl` that URL whenever the
catalogue changes.

**API changes:**
```bash
cd /var/www/laptop-store && git pull
cd backend && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
sudo systemctl reload php8.3-fpm
```

---

## Ongoing free-tier upkeep

- **Oracle reclaims idle Always Free ARM instances.** An instance averaging under ~10% CPU,
  ~10% network and ~20% memory across a 7-day window can be flagged as idle and reclaimed.
  A live site with any traffic is normally fine, but don't leave it completely unvisited for
  weeks. (Paid instances are never reclaimed.)
- **DuckDNS records expire if not updated for ~30 days.** On a static Oracle IP nothing
  changes, but a monthly refresh costs nothing:
  ```bash
  ( crontab -l 2>/dev/null; echo '0 3 * * * curl -s "https://www.duckdns.org/update?domains=flurotech&token=YOUR_TOKEN&ip=" >/dev/null' ) | crontab -
  ```
- **Certificates** renew automatically via certbot's systemd timer. Verify once with
  `sudo certbot renew --dry-run`.
- **Back up the database** — it's on a single VM with no managed backups:
  ```bash
  mysqldump -u laptop -p laptop_store > ~/backup-$(date +%F).sql
  ```
  Uploaded images live in `backend/storage/app/public/` — include that directory too.

## Gotcha checklist

- [ ] Ingress rules added in **both** the Oracle Security List **and** the VM's iptables
- [ ] `.env` is MySQL, **not** the local SQLite/Windows path
- [ ] `storage` + `bootstrap/cache` owned by `www-data`
- [ ] API returns JSON over **HTTPS** before the first Pages build
- [ ] `VITE_*` variables set in Pages **before** the first build (Vite inlines them)
- [ ] `config/cors.php` lists the real `.pages.dev` origin, then `config:cache` re-run
- [ ] Build log shows products were prerendered, not the fallback warning
- [ ] `/admin/products` survives a direct reload
- [ ] Seeded admin password changed
