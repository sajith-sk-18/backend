# Deploy the frontends to Vercel — Fluro Tech (laptop-store)

Vercel hosts the **storefront** and **admin panel** only. The Laravel API must live
elsewhere — see `DEPLOY-FREE.md` (Oracle + DuckDNS) or `DEPLOY-HOSTINGER.md`.

> **The API is a hard prerequisite, not a later step.** Vercel builds in the cloud, so:
> 1. `VITE_API_URL` must be a **public HTTPS** URL. Browsers block plain-HTTP XHR from an
>    HTTPS page, and `127.0.0.1` means nothing to Vercel's build container.
> 2. `npm run build` runs `scripts/prerender.mjs`, which **fetches products from the API
>    during the build**. No reachable API means no prerendered product pages and no SEO.

## Why the API cannot go on Vercel

`ProductController.php:287` stores uploaded product images on disk
(`storage/app/public/products/`). Vercel functions have an ephemeral filesystem, so every
upload would vanish on the next deploy. Same applies to Render, Koyeb and Railway free
tiers. Use a VM or shared PHP hosting.

---

## Layout: two projects, because these are two repos

`customer-site` and `admin-panel` are **separate GitHub repositories**, and one Vercel
project builds one repo. So you get two projects. Two ways to arrange the URLs:

### Option A — two URLs (simplest)

```
https://<store>.vercel.app/          storefront
https://<admin>.vercel.app/          admin panel
```

The admin project sets `ADMIN_BASE_PATH=/` so it serves from its own root.

### Option B — one URL, admin proxied at /admin/

```
https://<store>.vercel.app/          storefront
https://<store>.vercel.app/admin/    admin panel (proxied to the admin project)
```

Keep the admin's default base (`/admin/`) and add a rewrite to the **storefront's**
`vercel.json`, above the SPA catch-all — order matters, first match wins:

```json
"rewrites": [
  { "source": "/admin", "destination": "https://<admin>.vercel.app/admin/index.html" },
  { "source": "/admin/(.*)", "destination": "https://<admin>.vercel.app/admin/$1" },
  { "source": "/(.*)", "destination": "/index.html" }
]
```

Costs one extra network hop per admin request. Option A is less machinery; pick B only if
one domain matters to you.

---

## 1) Storefront project

1. **https://vercel.com/new** → import the `customer-site` repo.
2. Settings are read from `customer-site/vercel.json` (build command, output dir, SPA
   rewrite, security headers). Leave the framework preset as **Vite** or **Other**.
3. **Environment variables** — Vite inlines these at build time, so set them *before* the
   first deploy:

   | Name | Value |
   |---|---|
   | `VITE_API_URL` | `https://your-api-host/api` |
   | `VITE_SITE_URL` | `https://<store>.vercel.app` |
   | `VITE_ADMIN_WHATSAPP` | your number, digits only, country code, no `+` or spaces |

   `VITE_SITE_URL` is circular on the first deploy — Vercel assigns the domain as it
   builds. Deploy once, then set the real value and redeploy so `sitemap.xml` and the
   canonical/OG tags are correct.

4. Deploy, then **read the build log**. If it says `[prerender] Could not fetch products…`
   the API was unreachable and only static routes were prerendered — fix the API and
   redeploy, or you have shipped a store with no product-page SEO.

## 2) Admin project

1. **https://vercel.com/new** → import the `admin-panel` repo.
2. Environment variables:

   | Name | Value |
   |---|---|
   | `VITE_API_URL` | `https://your-api-host/api` |
   | `ADMIN_BASE_PATH` | `/` — **Option A only.** Omit entirely for Option B. |

   `ADMIN_BASE_PATH` is read by `vite.config.js` at build time. Without it the base stays
   `/admin/`, assets are requested from `/admin/assets/…`, and a root-domain deploy
   404s on load.

3. `admin-panel/vercel.json` already sends `X-Robots-Tag: noindex, nofollow, noarchive`
   on every response, so the admin stays out of search results.

## 3) Point the API at both origins

In `backend/config/cors.php`:

```php
'allowed_origins' => [
    'https://<store>.vercel.app',
    'https://<admin>.vercel.app',   // omit under Option B
],

// Vercel gives every branch and preview deployment its own subdomain.
'allowed_origins_patterns' => [
    '#^https://[a-z0-9-]+\.vercel\.app$#',
],
```

Then `php artisan config:cache` on the API host.

Under **Option B** the admin's XHR still goes directly to the API from the browser, and
its `Origin` is the storefront's domain — the rewrite proxies HTML and assets, not your
API calls.

---

## Host config files, and which host reads which

Three sets now coexist in the repo. Each host ignores the others' files:

| File | Read by |
|---|---|
| `vercel.json` | **Vercel** |
| `public/_redirects`, `public/_headers` | **Cloudflare Pages** / Netlify |
| `public/.htaccess` | **Apache** — Hostinger, InfinityFree, the Oracle VM |

So switching hosts needs no edits, but a routing fix made in one file is **not** applied
to the others.

## Verify

| Check | Expected |
|---|---|
| `https://<store>.vercel.app/` | storefront with products |
| `https://<store>.vercel.app/products/1` | **view source shows the real title/description** — proves prerender |
| `https://<store>.vercel.app/products` | direct load works (SPA rewrite) |
| admin URL (per your option) | login page |
| admin deep route, e.g. `…/products` | **paste in the address bar and reload** |
| devtools → Network | no CORS errors, no mixed-content warnings |
| response headers on the admin | `X-Robots-Tag: noindex` |

Seeded admin login: `admin@example.com` / `password` — change it immediately.

## Redeploying

Frontend changes deploy on `git push`. Both repos are on the **`QA`** branch — set that as
each project's Production Branch, or Vercel will treat pushes as preview deployments.

**Products changed?** The prerendered pages and `sitemap.xml` are build-time snapshots, so
trigger a rebuild: Vercel → Deployments → **Redeploy**, or create a Deploy Hook and `curl`
it whenever the catalogue changes. This is the one real advantage over Apache hosting —
worth wiring up.
