# Deploying Fluro Tech to sajith.rf.gd (InfinityFree)

Everything lives on the **one origin** `https://sajith.rf.gd` so there's no CORS.
When a real browser loads any page, it solves InfinityFree's `aes.js` challenge
once and sets the `__test` cookie; after that, same-origin `/api` XHR calls carry
the cookie and reach Laravel normally.

## Target layout (InfinityFree `htdocs/`)

```
htdocs/
├── index.html, assets/, favicon-*    ← customer-site/dist/*   (https://sajith.rf.gd/)
├── .htaccess                          ← customer-site/dist/.htaccess (root SPA fallback)
├── admin/                             ← admin-panel/dist/*      (https://sajith.rf.gd/admin/)
│   ├── index.html, assets/
│   └── .htaccess                      ← admin SPA fallback
└── api/                               ← the Laravel app        (https://sajith.rf.gd/api/)
```

## What's already built (ready to upload)

| Upload this folder's CONTENTS | …into this InfinityFree folder |
|-------------------------------|--------------------------------|
| `customer-site/dist/`         | `htdocs/`                      |
| `admin-panel/dist/`           | `htdocs/admin/`                |

> Both builds bake in `VITE_API_URL=https://sajith.rf.gd/api` (same origin once
> served from rf.gd). The `.htaccess` files are **hidden** — make sure your FTP
> client shows hidden files so they upload too.

## How to upload

1. InfinityFree control panel → **FTP Accounts** (note host/user/password), or use the **Online File Manager**.
2. With FileZilla (or File Manager):
   - Upload everything inside `customer-site/dist/` → `htdocs/`
   - Create `htdocs/admin/`, upload everything inside `admin-panel/dist/` → `htdocs/admin/`
3. Confirm the Laravel API is reachable at `https://sajith.rf.gd/api/...` (see prerequisite below).
4. Visit `https://sajith.rf.gd/` and `https://sajith.rf.gd/admin/` in a browser.

## Prerequisite — the Laravel API must actually be live at /api

Open `https://sajith.rf.gd/api/products` in a normal browser. After the brief
InfinityFree challenge, you should see **JSON**. If you instead see a 404, a
directory listing, or the challenge loops, the Laravel backend isn't deployed/
configured under `/api` yet — the frontends can't work until it is. (Laravel on
InfinityFree needs: upload the app, point the `/api` doc-root at Laravel's
`public/`, set `.env` `APP_URL=https://sajith.rf.gd`, run migrations via their
DB tools. Free-tier PHP limits apply.)

## Re-building after code changes

```
cd customer-site && npm run build      # → customer-site/dist
cd admin-panel  && npm run build        # → admin-panel/dist  (auto base = /admin/)
```
Then re-upload the changed `dist/` contents.

## Note on local development

The baked-in API URL is the remote one, so `npm run dev` on localhost will hit
CORS again (cross-origin to rf.gd). For local dev, switch the `.env` files back to
`http://127.0.0.1:8001/api` and run the Laravel backend locally.
