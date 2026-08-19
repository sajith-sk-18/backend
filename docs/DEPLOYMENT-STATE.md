# Deployment state — Fluro Tech (laptop-store)

As of **2026-08-19**. Records what is live, what is verified, and what is still outstanding.

## Live URLs

| Part | URL | State |
|---|---|---|
| **API** | https://backend-production-e5e1.up.railway.app/api | ✅ live, 29 products |
| **Storefront** | https://fluro-tech.pages.dev | ✅ live |
| **Admin panel** | https://fluro-tech.pages.dev/admin/ | ⚠️ works, but see *Known issues* |

Seeded admin login: `admin@example.com` / `password` — **not yet changed. Do this.**

## Architecture

```
Cloudflare Pages (static)            Railway (container)
  fluro-tech.pages.dev  ──XHR──▶  backend-production-e5e1.up.railway.app
    /            storefront            php:8.3-apache + Laravel 11
    /admin/      admin panel           ├── MySQL service (own volume)
    /products/*  prerendered           └── volume at storage/app/public
```

- Both frontends are **static builds**; no Node process runs in production.
- Product pages are **prerendered at build time** — `npm run build` fetches products from
  the live API and writes `dist/products/<id>/index.html` with real title, description,
  OG image and `Product` JSON-LD.
- The API container is built from `backend/Dockerfile`; `backend/deploy/railway-start.sh`
  is its entrypoint.

## Verified working

Checked against the live deployments, not assumed:

- `/api/categories`, `/api/products` → 200 with real data; `/api/announcements`,
  `/api/upcoming` → `[]` as seeded.
- Storefront renders live category counts (Accessories 8, Audio 4, Laptops 7,
  Monitors 5, Storage & Networking 5) with **zero console errors**.
- `https://fluro-tech.pages.dev/products/1/` view-source contains
  `<title>Dell XPS 13 9340 · Fluro Tech`, a real description, `"@type":"Product"` and
  `"price":"1399.00"` — SEO prerendering genuinely works in production.
- `sitemap.xml` and `robots.txt` carry the correct domain; robots has `Disallow: /admin/`.
- Admin responses carry `x-robots-tag: noindex, nofollow, noarchive`.
- CORS: `Origin: https://fluro-tech.pages.dev` is allowed; `https://pages.dev.attacker.com`
  and plain `http://` are refused.
- Migrations applied and catalogue seeded (`Nothing to migrate` /
  `Catalogue already populated` on subsequent boots).
- `APP_URL` is set to `https://backend-production-e5e1.up.railway.app`. Setting it
  triggered a redeploy automatically, so the boot-time `config:cache` picked it up.
  **Not proven end to end:** every seeded product uses an external Unsplash image URL,
  so nothing in the current API response is generated from `APP_URL`. First real proof
  comes from an admin uploading an image — its URL should begin with the Railway host,
  not `localhost`. If such an image fails to display, suspect `FILESYSTEM_DISK` or the
  volume mount rather than `APP_URL`.

## Known issues

### 1. Refreshing a deep admin route lands on the storefront

`/admin/products` served directly returns the **storefront** bundle. Entering at
`/admin/` and clicking through works, because routing is client-side from there; only a
refresh or a bookmarked deep link breaks. Invisible to customers.

Cause: nesting two SPAs behind one Pages catch-all. `_headers` is honoured but the
`/admin/*` rule in `_redirects` loses to `/* → /index.html`. Apache handles this correctly
via `.htaccess`; Cloudflare Pages does not behave the same way.

Attempted fix (option B) is **built but not live** — see next item.

### 2. `fluro-admin.pages.dev` returns 404 "Deployment Not Found"

The Pages project exists but has no deployment on its production branch. `--branch main`
did not change this, so the project's production branch is probably named something else.

To finish it: check **Workers & Pages → fluro-admin → Settings → Builds & deployments →
Production branch**, then

```bash
cd admin-panel
wrangler pages deploy dist --project-name=fluro-admin --branch <that-exact-name>
```

The builds are ready on disk: `admin-panel/dist` (root-relative assets, `ADMIN_BASE_PATH=/`)
and `customer-site/dist` (nested admin removed, `/admin/*` 302s to `fluro-admin.pages.dev`).
The storefront also needs redeploying for that redirect to take effect.

### 3. Stale Vercel deployment still public

`https://admin-panel-ecru.vercel.app` serves a **72-day-old** admin build pointing at an
old API URL. Delete that Vercel project so nobody stumbles onto it.

Vercel was abandoned because **builds never execute on that account** — every deployment
sits in `Queued`, including three attempts that predate this work, while Vercel reported
all systems operational. Not diagnosed further.

### 4. Cloudflare API token is exposed

The `wrangler-pages-deploy` token appeared in full in a screenshot shared into the working
session. **Delete it** (My Profile → API Tokens → ⋯ → Delete) and create a fresh one when
next needed.

## Redeploying

**API** — from `backend/`, deploys the local directory (the GitHub webhook does not fire):

```bash
railway up --ci
```

**Frontends** — rebuild, then upload. `VITE_*` values are inlined at build time, so they
must be set before building. Use **PowerShell**, not Git Bash: bash rewrites
`ADMIN_BASE_PATH="/"` into a Windows path.

```powershell
cd customer-site
$env:VITE_API_URL  = "https://backend-production-e5e1.up.railway.app/api"
$env:VITE_SITE_URL = "https://fluro-tech.pages.dev"
npm run build
wrangler pages deploy dist --project-name=fluro-tech --branch main
```

Watch the build log for `[prerender] Wrote N routes (N products)`. If it warns
`Could not fetch products`, the API was unreachable and product-page SEO is lost — fix and
rebuild rather than shipping it.

**Re-run the storefront build whenever products change**, or the prerendered pages and
sitemap go stale.

## Local development

| | URL |
|---|---|
| Storefront | http://localhost:5173 |
| Admin | http://localhost:5173/admin/ (proxied to 5174) |
| API | http://127.0.0.1:8001/api (SQLite) |

Ports are pinned with `strictPort` in both Vite configs because `config/cors.php`
whitelists exact origins. Standalone admin is `http://localhost:5174/admin/` — not the
bare port, since `base` is `/admin/`.

## Repositories

Three separate GitHub repos, all on branch **`QA`**. These docs live in the `backend`
repo under `docs/`, because the project root is not a git repository -- anything left
there is untracked and would be lost with the machine.

| Repo | Remote |
|---|---|
| `backend` | github.com/sajith-sk-18/backend |
| `customer-site` | github.com/sajith-sk-18/customer-site-recovered |
| `admin-panel` | github.com/sajith-sk-18/admin-panel |

⚠️ Pushes need the **`sajith-sk-18`** account. Windows Credential Manager also holds a
`sajithsk-18` credential for the GPC work; the remotes embed the username to disambiguate.

## Other deploy guides in this folder

`DEPLOY-FREE.md` (Oracle VM + DuckDNS + Cloudflare Pages, genuinely free),
`DEPLOY-HOSTINGER.md`, `DEPLOY-VERCEL.md`, `DEPLOY-rfgd.md`. Each host reads different
config files and they do **not** inherit fixes from one another:

| File | Read by |
|---|---|
| `_redirects`, `_headers` | Cloudflare Pages, Netlify |
| `vercel.json` | Vercel |
| `.htaccess` | Apache — Hostinger, InfinityFree, the Oracle VM |
| `Dockerfile`, `railway.json` | Railway, any Docker host |
