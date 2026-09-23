# Deploying RadiantDx to Plesk

The production site is **pulsecore.med.et**, on an Ethio Telecom Plesk shared host.

The constraint that shapes everything below: **there is no SSH.** Only the Plesk
panel, its Git integration and its Scheduled Tasks. There is no Node and no usable
Composer on the server, so anything that has to be *built* is built here and shipped.

If you read nothing else, read [Why a `production` branch](#why-a-production-branch)
and [Running artisan without SSH](#running-artisan-without-ssh). Everything else is
mechanical.

---

## How the pieces fit

```
subscription root
└── httpdocs/                  <- Plesk Git deploys the whole repo here
    ├── app/  bootstrap/  config/  database/  routes/  storage/  vendor/
    ├── .env                   <- lives only on the server, never in git
    ├── artisan
    └── public/                <- the domain's Document Root points HERE
        ├── index.php
        └── build/             <- Vite output, shipped prebuilt
```

The domain's Document Root is **`httpdocs/public`**, not `httpdocs`. That single
setting is what keeps `.env`, `storage/` and the application code out of the web root
while letting `public/index.php` stay stock Laravel — it resolves
`__DIR__.'/../vendor/autoload.php'` to `httpdocs/vendor/autoload.php` with no
modification.

## What this application does *not* need

Worth stating, because a normal Laravel runbook would tell you to set these up and
every one of them is wasted effort here:

| Not needed | Why |
|---|---|
| A cron entry for `schedule:run` | Nothing is scheduled. `routes/console.php` holds only the stock `inspire`. |
| A queue worker | Nothing is ever queued. `app/Jobs` does not exist. The `database` queue tables are created and stay empty. |
| SMTP credentials | Nothing sends mail. `app/Mail` and `app/Notifications` do not exist. |
| `php artisan storage:link` | Uploads go to the **private** disk and are streamed by an authorised controller. A public symlink would make every staff photograph reachable by guessing a URL. |
| Redis | Sessions, cache and queue all use MySQL. |

## What it does need

- **PHP 8.3+** (`composer.json` requires `^8.3`).
- **GD with JPEG, PNG *and* WebP.** `StaffPhotoService` calls `imagecreatefromwebp()`
  unconditionally; without it every photo upload throws.
- `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `ctype`, `fileinfo`, `curl`, `xml`,
  `bcmath`. (`intl` is *not* required.)
- A MySQL database and user.
- Writable `storage/` and `bootstrap/cache/`. Both arrive from git as empty trees —
  their `.gitignore` placeholders are tracked precisely so the directories exist.

---

## Why a `production` branch

`.gitignore` excludes `/vendor` (67 MB) and `/public/build` (328 KB). Six Blade views
call `@vite(...)`, so a site deployed without `public/build` throws
`ViteManifestNotFoundException` on **every page, including login**. And with no
Composer on the server, a site without `vendor/` cannot boot at all.

So `main` stays clean and a long-lived **`production`** branch carries the built
artifacts. Its `.gitignore` drops those two rules; its `.gitattributes` adds

```
vendor/**        -text
public/build/**  -text
```

so vendored code ships byte-for-byte instead of being run through the repo-wide
`* text=auto eol=lf` normalisation.

It is built through a **git worktree**, so building a release never disturbs the dev
`vendor/` in your main checkout and local tests keep their dev packages.

### One-time setup

```bash
git config http.postBuffer 524288000   # the first push is ~67 MB; the default is 1 MB
git worktree add ../radiantdx-production production
```

### Each release

```bash
cd ../radiantdx-production
git merge main --no-edit        # resolve .gitignore/.gitattributes toward production, once

npm run build
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

git add -A
git commit -m "Release $(date +%Y-%m-%d)"
git push origin production
```

Confirm the Plesk PHP version **before** running `composer install` — the `vendor/`
you build is resolved against your local PHP, and shipping one built for the wrong
minor is a slow thing to debug.

### Windows → Linux

The artifacts are built on Windows and run on Linux. Checked, and fine:
Composer's autoload files are fully relative (`dirname(__DIR__)`), so no Windows paths
leak. Two things to know:

- `core.filemode` is false on Windows, so `artisan` and `vendor/bin/*` arrive without
  the executable bit. Harmless — every command here is run as `php artisan …`, never
  `./artisan`.
- Windows is case-insensitive and Linux is not. A view or class referenced with the
  wrong case works locally and 500s in production. The smoke tests below are the
  practical catch.

---

## First deployment

Do these in order. Steps 1–3 in particular are not interchangeable.

### 1. TLS first

Issue a Let's Encrypt certificate for `pulsecore.med.et` (and `www`) in
**Plesk → SSL/TLS Certificates**, then turn on **Permanent SEO-safe 301 redirect from
HTTP to HTTPS**.

This is genuinely step one: the git remote is `https://…/plesk-git/pulsecore.git`, so
until the certificate matches the hostname, `git push` fails TLS verification and
nothing can be deployed at all.

> Do not work around this with `http.sslVerify=false`. That sends the git credentials
> over a connection you have stopped authenticating.

### 2. Panel configuration

- **PHP handler** → 8.3+, extensions as above.
- **Document Root** → `httpdocs/public` (Websites & Domains → Hosting Settings).
- **Database** → create the database and user; note the host (usually `localhost`).
- **Git** → deployment path `httpdocs`, branch `production`, mode **Manual** for the
  first deploy.

### 3. Push and deploy

Push `production`, then hit Deploy in Plesk.

### 4. Create `.env` — before running anything

Through File Manager, create `httpdocs/.env`. Generate the key locally with
`php artisan key:generate --show` and paste it in.

`SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` **must** be set before you seed —
`SuperAdminSeeder` throws outright in production without them.

Production values that differ from the example file:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pulsecore.med.et
SESSION_SECURE_COOKIE=true
```

`APP_URL` must be the apex, matching the canonical domain, or every signed URL and
asset redirects once before it resolves.

### 5. Migrate, then seed

```
php artisan migrate --force     # 29 migrations
php artisan db:seed --force     # permissions + the super admin
```

In that order, and only after the full migrate: one migration makes `users.staff_id`
NOT NULL, so a partially-migrated database fails the seeder.

`db:seed` is what creates the only way to log in. There is no registration route. The
first sign-in is forced through a password change.

### 6. Cache what is safe to cache

```
php artisan optimize --except=config
```

See [the config-cache trap](#the-config-cache-trap) for why `config` is excluded.

Between steps 3 and 5 the site cannot serve a usable page. Leave the default page or
maintenance mode up until the seed completes.

---

## Running artisan without SSH

In order of preference:

1. **Plesk → Scheduled Tasks → "Run a PHP script."** Script `httpdocs/artisan`,
   arguments e.g. `migrate --force`. Give it a schedule that will not fire on its own,
   press **Run Now**, read the output, then delete the task. Repeat per command.
2. **Plesk → Git → "Additional deployment actions,"** if the provider exposes it.
3. *Last resort:* a temporary token-guarded route calling `Artisan::call()`, removed
   in the very next push. Note that this puts a privileged operation on a public URL;
   it is a stopgap, not a pattern.

## The config-cache trap

`config:cache` bakes `.env` into `bootstrap/cache/config.php` and `.env` stops being
read at runtime. On a host with no shell that is a bad trade: every later `.env` edit
then needs a Scheduled Task round-trip to rebuild the cache, and until you do, the
site silently keeps serving the old values.

So run **`optimize --except=config`**. Routes, views and events still get cached —
which is where the real win is, since Blade compilation dominates — and `.env` stays
live. Revisit once the deployment has been stable for a while.

For the record, `config:cache` is otherwise *safe* here: there are no `env()` calls
anywhere outside `config/`.

---

## Per-release runbook

1. Build and push `production`.
2. Deploy in Plesk.
3. `php artisan optimize:clear`, then `php artisan optimize --except=config`.
4. `php artisan migrate --force` — only if migrations changed.

If the release changes which routes the service worker caches, bump `VERSION` in
`public/sw.js`. Build assets are content-hashed, so a stale worker is otherwise
harmless.

## Rollback

Plesk Git can deploy a specific commit: redeploy the previous `production` commit.
Because the artifacts live *in* the branch, that restores code and assets together.

Migrations are not reversed by this. The schema is additive throughout except
`2026_02_01_000500_require_staff_id_on_users.php`, which `docs/INTEGRATION-PLAN.md`
flags as the one one-way door.

## Smoke tests

Run in order — each one proves the layer under it.

| # | Check | Proves |
|---|---|---|
| 1 | `GET /up` returns 200 | front controller, autoloader, `.env`, database all reachable |
| 2 | The login page renders **styled** | `public/build` arrived |
| 3 | Page source shows `https://` asset URLs | the proxy trust in `bootstrap/app.php` works |
| 4 | Sign in as the super admin | migrate and seed both succeeded |
| 5 | The forced password change appears | first-login path |
| 6 | Upload a staff photo | GD with WebP, and `storage/` is writable |
| 7 | Redeploy; `.env` and that photo survive | Plesk's checkout leaves untracked files alone |
| 8 | `curl -I http://pulsecore.med.et` → 301 | the HTTPS redirect |

To confirm GD before you need it, run this once as a PHP script task:

```php
<?php print_r(array_intersect_key(gd_info(),
    array_flip(['GD Version', 'JPEG Support', 'PNG Support', 'WebP Support'])));
```

All three format flags must be `1`.

**Do not** test the database-unavailable page by breaking production. It is covered by
`tests/Feature/DatabaseUnavailableTest.php` and was verified locally against a dead
port. If you want to see it, do that on a local copy.

## When the database goes down

You will get a styled 503 page, not a stack trace — see `App\Support\DatabaseUnavailable`.
Because `SESSION_DRIVER=database`, an unreachable database takes out *every* page
including sign-in, so that page is the whole site until MySQL answers again. It never
names the host, port or database unless `APP_DEBUG` is on.

Logging is file-based, so `storage/logs/laravel.log` survives the outage that produced
it. The timestamp printed on the page matches the log line.
