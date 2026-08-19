# Deployment state — Fluro Tech (laptop-store)

As of **2026-08-19**. Records what is live, what is verified, and what is still outstanding.

## Live URLs

| Part | URL | State |
|---|---|---|
| **API** | https://backend-production-e5e1.up.railway.app/api | ✅ live, 29 products |
| **Storefront** | https://fluro-tech.pages.dev | ✅ live |
| **Admin panel** | https://fluro-tech.pages.dev/admin/ | ⚠️ works, but see *Known issues* |

Admin login: `admin@example.com`. The seeded password `password` has been **changed and
verified** — see *Verified working*. To change it again, see *Rotating the admin password*.

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
- **No stale Vercel deployments remain.** Three projects were serving the app publicly and
  have been deleted: `admin-panel` (a 72-day-old build against an old API URL) and
  `customer-site-recovered` plus `customer-site-recovered-nbom` — two indexable duplicates
  of the storefront, 18 hours old, competing with `fluro-tech.pages.dev` for the same
  content and pointing at a stale API. All three now return 404. Unrelated Vercel projects
  (`skillgraph`, `sajith-sk-18-cricketgraph`, `goldloan`) were left untouched.
- **Admin password changed**, verified end to end against the live API: the new password
  returns 200 with a token, and the old seeded `password` returns 422 "Invalid credentials".
  Still works after `ADMIN_PASSWORD` was removed from Railway, confirming it is stored in
  the database rather than derived from the environment.

  Vercel was abandoned because **builds never execute on that account** — every deployment
  sat in `Queued`, including three attempts that predate this work, while Vercel reported
  all systems operational. Never diagnosed; if it is ever revisited, start at the account
  billing/spend-management settings rather than the project config.

## Known issues

### 1. `fluro-tech.pages.dev` serves the FIRST build, not the latest

The live storefront is the very first Cloudflare Pages deployment. It works correctly --
live data, prerendered SEO, no console errors -- but it still contains the **nested admin
panel** at `/admin/`, and a direct load of `/admin/<route>` serves the storefront bundle
rather than the admin one. Entering at `/admin/` and clicking through works, because
routing is client-side from there; only a refresh or a bookmarked deep link breaks.
Invisible to customers.

`fluro-admin.pages.dev` exists as a project but returns **404 "Deployment Not Found"** --
no deployment is attached to its production branch.

#### Root cause (diagnosed, not yet fixed)

**Wrangler's production-branch prompt defaults to the current git branch, which is `QA` in
all three repos.** So a project created interactively records `QA` as its production
branch. Every later deploy passing `--branch main` therefore does not match, is treated as
a **preview**, and leaves the apex domain frozen on whatever was production before.

This explains all three observations together:

| Observation | Why |
|---|---|
| First storefront deploy reached the apex | it created the project, so it *was* production |
| Later `--branch main` deploys changed nothing | branch mismatch -> preview, apex untouched |
| `fluro-admin` apex 404s | created the same way, so it has no production deployment at all |

#### To fix

Deploy with the branch the project actually treats as production -- almost certainly `QA`:

```bash
cd admin-panel
wrangler pages deploy dist --project-name=fluro-admin --branch QA

cd ../customer-site
wrangler pages deploy dist --project-name=fluro-tech --branch QA
```

Confirm the branch first if in doubt; this prints an **Environment** column per deployment:

```bash
wrangler pages deployment list --project-name=fluro-tech
```

Both builds are ready on disk and verified: `admin-panel/dist` has root-relative assets
(`ADMIN_BASE_PATH=/`) plus `_headers`/`_redirects`/`robots.txt`, and `customer-site/dist`
has 30 prerendered product pages, no nested admin, and `/admin/*` 302ing to
`fluro-admin.pages.dev` so old bookmarks keep working. Rebuild in **PowerShell**, not Git
Bash -- see *Redeploying*.

⚠️ Deploying needs a Cloudflare API token. Use a **fresh** one -- the previous token was
exposed (see issue 2) and should not be reused: **Create Custom Token** with
`Account -> Cloudflare Pages -> Edit`, and
also export `CLOUDFLARE_ACCOUNT_ID=ba197182225c420e0ce495225391cea1` -- a Pages-scoped
token cannot read the account list, and Wrangler's discovery call 403s without it.

### 2. Cloudflare API token is exposed

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

⚠️ **`--branch` must match the project's production branch**, or Cloudflare treats the
deploy as a *preview* and the apex domain does not change — silently, with a successful
"Deployment complete" message. Wrangler's creation prompt defaults to the current git
branch, so these projects most likely recorded **`QA`**. See issue 1.

## Rotating the admin password

There is no password-reset route and no admin UI for it, Railway's MySQL has no public
endpoint, and `railway ssh` needs an SSH key registered to the account. So the password is
set at container boot from an environment variable:

```bash
railway variables --set "ADMIN_PASSWORD=NewPassword"
railway up --ci                              # boot applies it
railway variables delete ADMIN_PASSWORD      # then remove it
```

`deploy/set-admin-password.php` does the work and is idempotent: exit 0 changed, 1 nothing
to do (unset, or already correct), 2 failed — which aborts the boot rather than running with
credentials that are not what was asked for. It never logs the password. `ADMIN_EMAIL`
overrides the target, defaulting to `admin@example.com`.

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
