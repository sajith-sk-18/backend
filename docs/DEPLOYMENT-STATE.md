# Deployment state — Fluro Tech (laptop-store)

As of **2026-08-19**. Records what is live, what is verified, and what is still outstanding.

**Status: complete.** All three parts are live and verified, and the storefront is verified
in Google Search Console with its homepage indexed.

## Live URLs

| Part | URL | State |
|---|---|---|
| **API** | https://backend-production-e5e1.up.railway.app/api | ✅ live, 29 products |
| **Storefront** | https://fluro-tech.pages.dev | ✅ live |
| **Admin panel** | https://fluro-admin.pages.dev | ✅ live, its own Pages project |

`fluro-tech.pages.dev/admin` and `/admin/*` **302 to the admin project** with the path
preserved, so old bookmarks keep working.

Admin login: `admin@example.com`. The seeded password `password` has been **changed and
verified** — see *Verified working*. To change it again, see *Rotating the admin password*.

## Architecture

```
Cloudflare Pages (static)                    Railway (container)
                                          backend-production-e5e1.up.railway.app
  fluro-tech.pages.dev          ──XHR──▶     php:8.3-apache + Laravel 11
    /            storefront                  ├── MySQL service (own volume)
    /products/*  prerendered                 └── volume at storage/app/public
                                                  (uploaded product images)
  fluro-admin.pages.dev         ──XHR──▶
    /*           admin panel

Two Pages PROJECTS, not one site. /admin* on the storefront 302s to the admin
project. Nesting both behind one catch-all did NOT work -- see Gotchas.
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
- **Admin panel on its own domain**, verified on `fluro-admin.pages.dev`: `/`, `/products`,
  `/enquiries`, `/categories` and `/offers` all return 200 on a **direct load**, so a refresh
  or bookmarked deep link stays in the admin app. That was the original bug.
- `x-robots-tag: noindex, nofollow, noarchive` on the admin, and CORS accepts its origin.
- Storefront `/admin/products` 302s to `fluro-admin.pages.dev/products` — splat preserved.
- **Google Search Console** (verified 2026-08-24, via the **HTML file** method --
  `public/google33fd6ed204d674f4.html`, which ships with every build; confirm it with
  `curl -L`, not a bare status check, because Pages 308-redirects the `.html` URL):
  - property `https://fluro-tech.pages.dev` **verified**
  - `sitemap.xml` **submitted** -- 34 URLs, served as `application/xml`, valid, all on the
    property domain. Console showed *"Couldn't fetch"* immediately after submitting with an
    empty *Last read*; that means **not yet fetched**, not failed, and clears by itself.
  - homepage **indexed** ("URL is on Google" / "Page is indexed"), and re-indexing requested
    after the title change so Google picks up the brand-leading title.

  An earlier verification attempt failed against the **HTML tag** method, which was never
  implemented on the site -- only the file method was. Use the file.

## Open items

Nothing blocking. One housekeeping item:

**Rotate the Cloudflare API token.** Two tokens have now been pasted in full into a working
session (`wrangler-pages-deploy`, since deleted, and its replacement). Delete the current
one — the deploys are done and nothing needs it: **My Profile → API Tokens → ⋯ → Delete**.
Create a fresh one when next deploying.

Optional: the admin password `Fluro@2026` was also typed into that session. See
*Rotating the admin password*.

## Gotchas that cost real time

Each of these was diagnosed the hard way. Read before deploying.

### `--branch` must match the project's production branch

Wrangler's creation prompt **defaults to your current git branch**, which is `QA` in all
three repos — so both Pages projects recorded **`QA`** as production, not `main`.

A deploy whose `--branch` does not match is treated as a **preview**: it prints
`✨ Deployment complete!`, returns a `<branch>.<project>.pages.dev` alias, and leaves the
apex domain **untouched**. Nothing in the output says the live site did not change. This
cost six or seven attempts.

```bash
wrangler pages deploy dist --project-name=fluro-tech  --branch QA
wrangler pages deploy dist --project-name=fluro-admin --branch QA
```

If a project seems not to exist, check first — `fluro-tech` was absent from this account for
part of the work, so deploys naming it were never going to update anything:

```bash
wrangler pages project list
```

### Pages 308-redirects `.html` URLs

`/google33fd6ed204d674f4.html` answers **308 → `/google33fd6ed204d674f4`**. A `curl` without
`-L` returns the redirect, which was briefly misread as a stale deployment. Always use
`curl -L`, and check the **response body**, not just the status: while the old build was
live, that URL returned `200` serving the storefront homepage via the SPA catch-all — a
status-only check would have called that success.

### Two SPAs cannot share one catch-all

While the admin was nested at `/admin/` on the storefront, a direct load of
`/admin/<route>` fell through `/* → /index.html` and served the **storefront** app. Ordering
the `/admin/*` rule first in `_redirects` did not help. Apache handles this via `.htaccess`;
Pages does not. Fixed by giving the admin its own project.

### Build in PowerShell, not Git Bash

Git Bash rewrote `ADMIN_BASE_PATH="/"` into `/Program Files/Git/`, silently breaking every
asset path in the admin build.

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
wrangler pages deploy dist --project-name=fluro-tech --branch QA
```

Watch the build log for `[prerender] Wrote N routes (N products)`. If it warns
`Could not fetch products`, the API was unreachable and product-page SEO is lost — fix and
rebuild rather than shipping it.

**Re-run the storefront build whenever products change**, or the prerendered pages and
sitemap go stale.

⚠️ **Use `--branch QA`** — that is the production branch of both Pages projects. A
mismatch silently produces a preview. See *Gotchas*.

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
