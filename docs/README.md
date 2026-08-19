# Laptop & Accessories Store

Full-stack eCommerce demo with **two separate frontends** sharing one Laravel API.

- **Backend** (Laravel 11 + Sanctum + MySQL) — one REST API, port 8000
- **Customer site** (React 18 + Vite + Tailwind) — port 5173 — public shop
- **Admin panel** (React 18 + Vite + Tailwind) — port 5174 — staff dashboard

```
laptop-store/
├── README.md              ← you are here (high-level)
├── SETUP.md               ← step-by-step run guide
├── backend/               ← Laravel API
├── customer-site/         ← React customer-facing site
└── admin-panel/           ← React admin dashboard
```

## Why two separate frontends?

- **Different audiences** — customers and staff don't share the same UI shell, navigation, or hot-path code, so they shouldn't ship the same bundle.
- **Deploy independently** — customer site can scale on a CDN; admin panel lives behind VPN / SSO at a private subdomain.
- **Security** — admin code (forms for delete/approve) never reaches the public bundle. Easier to lock the admin domain to internal IPs.

Both frontends talk to the same Laravel backend over HTTP.

## Customer site (`customer-site/`)

| Page | Route | What |
|------|-------|------|
| Home | `/` | Hero, featured laptops, featured accessories |
| Products | `/products` | List + search + category/price/brand filter + pagination |
| Product details | `/products/:id` | Specs, gallery, reviews, "write a review" |
| About | `/about` | Shop story, location, contact |
| Contact | `/contact` | Enquiry form |

No login on the customer site — anyone can browse + submit enquiries / reviews.

## Admin panel (`admin-panel/`)

| Page | Route | What |
|------|-------|------|
| Login | `/login` | Email + password (Sanctum token) |
| Dashboard | `/` | Counts of products / enquiries / pending reviews |
| Products | `/products` | CRUD + image upload |
| Categories | `/categories` | CRUD |
| Reviews | `/reviews` | Approve / delete |
| Enquiries | `/enquiries` | Mark resolved / delete |

**Default admin** (created by seeder): `admin@example.com` / `password`

## API endpoints (Laravel — shared by both frontends)

Public (no auth, called by customer site):
```
POST   /api/auth/login
POST   /api/enquiries
POST   /api/reviews
GET    /api/categories
GET    /api/products            ?q=&category_id=&brand=&min_price=&max_price=&page=
GET    /api/products/{id}
GET    /api/products/{id}/reviews
```

Admin (Bearer token required, called by admin panel):
```
POST   /api/auth/logout
GET    /api/dashboard
GET    /api/admin/products      (CRUD)
POST   /api/admin/products
PUT    /api/admin/products/{id}
DELETE /api/admin/products/{id}
GET    /api/admin/categories    (CRUD)
POST   /api/admin/categories
PUT    /api/admin/categories/{id}
DELETE /api/admin/categories/{id}
GET    /api/admin/reviews
POST   /api/admin/reviews/{id}/approve
DELETE /api/admin/reviews/{id}
GET    /api/admin/enquiries
POST   /api/admin/enquiries/{id}/resolve
DELETE /api/admin/enquiries/{id}
```

## How to run

See **[SETUP.md](SETUP.md)** for the step-by-step.

Short version (3 terminals):
```bash
# Terminal 1 — Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve

# Terminal 2 — Customer site
cd customer-site
cp .env.example .env
npm install
npm run dev

# Terminal 3 — Admin panel
cd admin-panel
cp .env.example .env
npm install
npm run dev
```

Open:
- **Customer site** → http://localhost:5173
- **Admin panel** → http://localhost:5174
- **API health check** → http://127.0.0.1:8000/api/categories
