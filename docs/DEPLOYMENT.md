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
└── radiant/                  <- Plesk Git deploys the whole repo here
    ├── app/  bootstrap/  config/  database/  routes/  storage/  vendor/
    ├── .env                   <- lives only on the server, never in git
    ├── artisan
    └── public/                <- the domain's Document Root points HERE
        ├── index.php
        └── build/             <- Vite output, shipped prebuilt
```

The domain's Document Root is **`radiant/public`**, not `radiant`. That single
setting is what keeps `.env`, `storage/` and the application code out of the web root
while letting `public/index.php` stay stock Laravel — it resolves
`__DIR__.'/../vendor/autoload.php'` to `radiant/vendor/autoload.php` with no
modification.

## What this application does *not* need

Worth stating, because a normal Laravel runbook would tell you to set these up and
every one of them is wasted effort here:

| Not needed | Why |
|---|---|
| A cron entry for `schedule:run` | Nothing is scheduled. `routes/console.php` holds only the stock `inspire`. |
| A queue worker | Nothing is ever queued. `app/Jobs` does not exist. The `database` queue tables are created and stay empty. |
| A queue worker for mail | Nothing sends mail *yet* — no Mailables, no notifications, no password reset. SMTP is configured as groundwork and proved with `mail:test`; see [Mail](#mail). |
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

Assets are built in the **main** checkout and copied across. The worktree has no
`node_modules` — it is git-ignored, so it never gets checked out — and installing a
second copy of it there just to run Vite would be several hundred megabytes for
nothing. Composer has no such problem and runs in the worktree directly.

```bash
# 1. build assets where node_modules already lives
cd /path/to/HarmeLaboratorySystem
git checkout main
npm run build

# 2. bring code forward, then carry the assets over
cd ../radiantdx-production
git merge main --no-edit        # resolve .gitignore/.gitattributes toward production, once
cp -r ../HarmeLaboratorySystem/public/build/. public/build/

# 3. production dependencies, in the worktree so the main checkout keeps its dev packages
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 4. ship
git add -A
git commit -m "Release $(date +%Y-%m-%d)"
git push origin production
```

Before pushing, confirm the artifact actually works — a broken `vendor/` is much
cheaper to catch here than on the server:

```bash
php artisan --version                     # proves the autoloader is intact
php -r "echo count(json_decode(file_get_contents('public/build/manifest.json'),true));"
```

The manifest must contain `resources/css/app.css` and `resources/js/app.js`.

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

### 1. TLS — done, but read the DNS note

**Status: complete.** `pulsecore.med.et` serves a valid certificate naming both the
apex and `www`. Kept here because it is step one for any future rebuild: the git
remote is `https://…/plesk-git/pulsecore.git`, so until a certificate matches the
hostname, `git push` fails TLS verification and nothing can be deployed at all.

DNS: `pulsecore.med.et` and `www` are both A records to **213.55.96.150** —
`lin2.ethiotelecom.et`, the node the subscription lives on. There is no CNAME, and an
apex CNAME would not be legal DNS anyway.

> **The trap that cost an afternoon.** Ethio Telecom's own resolvers can serve a stale
> answer for this domain long after it is correct everywhere else — pointing at
> `213.55.96.153` (`lin5`), a node with no certificate for it. The site then looks
> broken from inside the network while being perfectly healthy from outside, and
> `git push` fails with *"no alternative certificate subject name matches"*.
>
> Diagnose by comparing resolvers, not by trusting the local one:
> ```bash
> nslookup pulsecore.med.et 1.1.1.1      # the truth
> nslookup pulsecore.med.et              # what this machine believes
> ```
> If they disagree, point the machine at `1.1.1.1` or `8.8.8.8`. Flushing the Windows
> cache does nothing, because the stale answer is upstream.

Either a Plesk-issued Let's Encrypt certificate or an externally issued one works.

**If using an external CA with HTTP file validation** (Sectigo/Comodo hand you a file
named like `7F0093E59B8E863AC57CCABF422E29F3.txt` containing a hash, `comodoca.com`
and a token), it must be reachable at exactly:

```
http://pulsecore.med.et/.well-known/pki-validation/<THE-FILE>.txt
```

Two ordering traps, both easy to trip:

- **Validate before changing the Document Root.** While the root is still `radiant`,
  the file goes in `radiant/.well-known/pki-validation/`. After the root moves to
  `radiant/public` (step 2) the same file must live in
  `radiant/public/.well-known/pki-validation/` or it 404s. Doing validation first
  avoids the question entirely.
- **Do not enable the HTTP→HTTPS redirect until validation has completed.** The CA
  fetches that URL over plain HTTP. Redirecting it to an HTTPS endpoint that is still
  serving a mismatched certificate is a good way to fail validation for reasons that
  look like nothing.

Once Laravel is deployed the path keeps working without special handling: its
`.htaccess` only rewrites to the front controller when the target is not a real file
(`RewriteCond %{REQUEST_FILENAME} !-f`), so a genuine `.txt` on disk is served as-is.

**Only after the certificate is installed**, turn on **Permanent SEO-safe 301 redirect
from HTTP to HTTPS**, and verify from outside:

```bash
echo | openssl s_client -connect pulsecore.med.et:443 -servername pulsecore.med.et \
  2>/dev/null | openssl x509 -noout -subject -ext subjectAltName
```

The subject must name `pulsecore.med.et`. If it still reports a `linN.ethiotelecom.et`
default, the certificate was installed but not bound to this domain's vhost.

> Do not work around any of this with `http.sslVerify=false`. That sends the git
> credentials over a connection you have stopped authenticating.

### 2. Panel configuration

- **PHP handler** → 8.3+, extensions as above.
- **Document Root** → `radiant/public` (Websites & Domains → Hosting Settings).
- **Database** → create the database and user; note the host (usually `localhost`).
- **Git** → deployment path `radiant`, branch `production`, mode **Manual** for the
  first deploy.

### 3. Push and deploy

Push `production`, then hit Deploy in Plesk.

### 4. Create `.env` — before running anything

Through File Manager, create `radiant/.env`. Generate the key locally with
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

**Use the Laravel Toolkit extension.** Plesk ships one, and it runs artisan commands
from the panel UI — which is the whole problem solved properly rather than worked
around.

It discovers an application only when `public/` is the document root and `artisan`
sits in the parent directory, which is precisely the layout above. So after the first
deploy, press **Scan** and it will find the app at `radiant`. Then run, in order:

```
migrate --force
db:seed --force
optimize --except=config
```

Do not use **Install Application** to set the site up from git. That is a parallel
deployment mechanism with its own clone and its own checkout, and this project is
already wired to Plesk's Git integration with a `production` branch built for it.
Mixing the two gives you two things writing to the same directory.

Fallbacks, if the extension is unavailable on this subscription:

1. **Plesk → Scheduled Tasks → "Run a PHP script."** Script `radiant/artisan`,
   arguments e.g. `migrate --force`. Give it a schedule that will not fire on its own,
   press **Run Now**, read the output, then delete the task. Repeat per command.
2. **Plesk → Git → "Additional deployment actions,"** if the provider exposes it.
3. *Last resort:* a temporary token-guarded route calling `Artisan::call()`, removed
   in the very next push. Note that this puts a privileged operation on a public URL;
   it is a stopgap, not a pattern.

### If the Toolkit can also run Composer

It generally can, and that would make shipping `vendor/` in the branch unnecessary —
6,445 tracked files and 67 MB of it. Worth testing **after** the first deploy
succeeds, not during it: the current branch is built and verified, and swapping the
dependency strategy mid-deployment trades a known-good state for an unknown one.

If it works, drop `vendor/` from the production branch's `.gitignore` override and run
`composer install --no-dev --optimize-autoloader` from the Toolkit after each deploy.
Keep `public/build/` tracked regardless — the Toolkit does not build front-end assets,
there is no Node on the host, and at 328 KB it costs nothing.

## Mail

Enterprise mail is provisioned (`admin@pulsecore.med.et`, host `213.55.96.132`), but
the provider named neither a port nor an encryption mode and the server does not
answer from outside its own network. So the settings in `.env` are an assumption —
587 with STARTTLS — until something proves them from the server.

That is what `mail:test` is for. Run it from the Laravel Toolkit:

```
mail:test admin@pulsecore.med.et
```

It prints the settings it is about to use, sends one message, and on failure reports
what the transport actually objected to along with the next thing to try. Finding the
right combination usually takes two or three attempts, and the flags avoid an
edit-and-redeploy for each one:

```
mail:test admin@pulsecore.med.et --port=465 --scheme=smtps
mail:test admin@pulsecore.med.et --port=25  --scheme=
```

Once a combination works, write those values into `.env` by hand — the command never
does, deliberately.

Two things it will tell you that are easy to misread:

- It always tests the **smtp** mailer, even while `MAIL_MAILER=log`, and warns when
  those differ. Sending through the default mailer would mean that with `log` set it
  writes a line to a file and declares success, having proved nothing.
- Acceptance is not delivery. A server that accepts the message can still drop it.

Note `MAIL_FROM_ADDRESS` is the authenticated mailbox rather than a `no-reply@`
address. Most providers reject a sender that is not the mailbox that authenticated,
and `no-reply@pulsecore.med.et` is not a real mailbox here.

**Nothing in the application sends mail yet** — there are no Mailables, no
notifications and no password-reset flow. This configuration is groundwork, so that
whenever something does need to send, the transport is already known to work.

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
