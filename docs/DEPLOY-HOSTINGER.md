# Deploy to Hostinger — Fluro Tech (laptop-store)

Three parts: **Laravel API** (PHP + MySQL), **Customer site** (static), **Admin panel** (static).
Suggested domains:
- Storefront → `https://yourdomain.com`
- Admin → `https://admin.yourdomain.com`
- API → `https://api.yourdomain.com`

> **Deploy the API first.** The frontends bake their config in at **build time**, and the customer
> site's prerender **fetches products from the API during build** — so the API must be live and
> reachable before you build the frontends.

---

## 1) Laravel API  (`backend/`)

**hPanel → Databases:** create a MySQL database + user; note name/user/password.

**Domain:** point `api.yourdomain.com`'s document root to the project's **`/public`** folder
(hPanel → Domains/Subdomains; or place the app outside `public_html` and set the subdomain root to its `public/`).

**Upload & install** (SSH on Business+ plans, or upload a zip and use Terminal):
```bash
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env        # then edit .env (see below)
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache && php artisan route:cache
```

**`backend/.env`:**
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_pass
```

**CORS — `backend/config/cors.php`:** set the real frontend origins:
```php
'allowed_origins' => [
    'https://yourdomain.com',
    'https://admin.yourdomain.com',
],
```
(then re-run `php artisan config:cache`)

**Smoke test:** open `https://api.yourdomain.com/api/categories` → should return JSON.

Default admin login (from the seeder): `admin@example.com` / `password` — **change it after first login.**

---

## 2) Customer site  (`customer-site/`)  → storefront domain

Set **`customer-site/.env`** (build-time values):
```
VITE_API_URL=https://api.yourdomain.com/api
VITE_SITE_URL=https://yourdomain.com
VITE_ADMIN_WHATSAPP=<real WhatsApp number, digits only, e.g. 919876543210>
```

Build (API must be reachable so product pages get prerendered):
```bash
cd customer-site
npm install
npm run build      # runs vite build + scripts/prerender.mjs
```

Upload **everything inside `customer-site/dist/`** to the storefront's `public_html`.
This includes the SPA, the **prerendered** `products/<id>/index.html` pages, `sitemap.xml`,
`robots.txt`, and `.htaccess` (SPA fallback + caching) — all generated for you.

> Re-run `npm run build` and re-upload whenever products change, so the prerendered product
> pages and sitemap stay current.

---

## 3) Admin panel  (`admin-panel/`)  → admin subdomain

Set **`admin-panel/.env`:**
```
VITE_API_URL=https://api.yourdomain.com/api
```
Build and upload:
```bash
cd admin-panel
npm install
npm run build
```
Upload everything in `admin-panel/dist/` to `admin.yourdomain.com`'s root. The included
`.htaccess` adds SPA fallback **and** a `noindex` header; `robots.txt` disallows crawling.

---

## After launch
- Enable **SSL** for all three domains (hPanel → SSL) and turn on **Force HTTPS**.
- Google **Search Console**: add `https://yourdomain.com` and submit `…/sitemap.xml`.
- Test a product link in WhatsApp/Facebook — the preview should show that product's title,
  description and image (from the prerendered HTML).

## Quick gotcha checklist
- [ ] API deployed & reachable **before** building frontends.
- [ ] `VITE_*` values set **before** `npm run build` (Vite inlines them at build time).
- [ ] `config/cors.php` lists the real storefront + admin origins.
- [ ] `.htaccess` present in each uploaded `dist/` (ships automatically from `public/`).
- [ ] `VITE_ADMIN_WHATSAPP` = real number (digits only, country code, no `+`/spaces).
- [ ] Changed the seeded admin password.
