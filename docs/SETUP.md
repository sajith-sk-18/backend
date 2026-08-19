# Setup & Run Guide

## Prerequisites

- **PHP 8.2+** with extensions: `pdo_mysql`, `mbstring`, `xml`, `gd`, `fileinfo`
- **Composer 2.x**
- **Node.js 18+** + **npm**
- **MySQL 8.x** (or MariaDB 10.6+)

## Step 1 — Database

```sql
CREATE DATABASE laptop_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Step 2 — Backend API (Laravel)

```bash
cd backend
composer install
cp .env.example .env
# edit .env → set DB_DATABASE / DB_USERNAME / DB_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

API is at **http://127.0.0.1:8000**.

Default admin: `admin@example.com` / `password`

Quick smoke test:
```bash
curl http://127.0.0.1:8000/api/categories
```

## Step 3 — Customer site (React @ 5173)

In a **new terminal**:
```bash
cd customer-site
npm install
cp .env.example .env
npm run dev
```

Open **http://localhost:5173** — the public storefront. No login.

## Step 4 — Admin panel (React @ 5174)

In a **third terminal**:
```bash
cd admin-panel
npm install
cp .env.example .env
npm run dev
```

Open **http://localhost:5174** → you'll be redirected to `/login`.

Log in with `admin@example.com` / `password`.

## End-to-end smoke test

1. **Customer site** (5173):
   - Home shows seeded products.
   - Click a product → details + reviews load.
   - Contact form → submit. See success toast.
   - On a product → submit a review. See "thanks, waiting for approval" message.
2. **Admin panel** (5174):
   - Dashboard → counts (incl. your pending review + enquiry).
   - Manage Products → add a product with image upload → it appears on customer site.
   - Manage Reviews → approve the test review → refresh customer product page → review now visible.
   - Manage Enquiries → mark resolved → status updates.

## Production builds

Backend:
```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
# point php-fpm / Apache / nginx at backend/public/
```

Customer site:
```bash
cd customer-site
npm run build      # → customer-site/dist/
```

Admin panel:
```bash
cd admin-panel
npm run build      # → admin-panel/dist/
```

Each `dist/` is a static bundle — host on Nginx / Vercel / S3 / etc. Each should be served from its own domain or path (e.g. `shop.example.com` + `admin.example.com`).

## Common issues

| Issue | Fix |
|---|---|
| `SQLSTATE[HY000] [1045]` on `migrate` | Wrong DB credentials in `backend/.env` |
| Frontend shows "Network error" | Backend not running, or `VITE_API_URL` wrong |
| Images return 404 | Run `php artisan storage:link` in `backend/` |
| Login returns 422 on admin panel | Use exactly `admin@example.com` / `password` after seeding |
| CORS error | `backend/config/cors.php` allows ports 5173 + 5174. Add your origin if different. |
| Port 5173 already used | Vite auto-picks next free port — or set `--port` in package.json |

## Where things live

```
backend/
├── app/
│   ├── Http/Controllers/      ← 6 controllers (Auth, Products, Categories, ...)
│   └── Models/                 ← 6 Eloquent models
├── database/
│   ├── migrations/             ← Schema (5 tables + users + sanctum)
│   └── seeders/                ← Default admin + 8 sample products
├── routes/api.php              ← All REST routes
├── config/cors.php             ← CORS whitelist
└── .env.example

customer-site/
├── src/
│   ├── api.js                  ← Axios setup
│   ├── pages/                  ← Home / Products / ProductDetails / About / Contact
│   ├── components/             ← Navbar / Footer / ProductCard / RatingStars / Pagination
│   ├── App.jsx                 ← Router
│   └── main.jsx                ← Entry
└── package.json

admin-panel/
├── src/
│   ├── api.js                  ← Axios setup with auth interceptor
│   ├── auth.js                 ← Token storage helpers
│   ├── pages/                  ← Login / Dashboard / Products / Categories / Reviews / Enquiries
│   ├── components/             ← AdminLayout / PrivateRoute / ProductForm / DataTable
│   ├── App.jsx                 ← Router with auth guard
│   └── main.jsx                ← Entry
└── package.json
```
