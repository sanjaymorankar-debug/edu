# Deployment (Hostinger)

**Live target:** `https://edutest.agtci.com` (domain root — not a subpath)
**Hosting account:** shared Hostinger hosting, no Node.js available server-side, PHP 8.3 + Composer + git available via SSH.
**Database:** PostgreSQL on Neon. MySQL is no longer supported.

## Database: Neon — read this first

Hostinger's web hosting has no PostgreSQL, so the database lives on Neon and the PHP app connects out to it. This site uses:

| | |
|---|---|
| Neon branch | `test` (this copy is a superseded test site; it has no `production` or `dev` database) |
| Database | `bk_edu_standalone` |
| Role | `bk_edu_standalone_app`, owner of that database |

The other tiers of the estate are on the other branches (`production` → bkesari.com, `test` → test.bkesari.com, `dev` → dev.bkesari.com; see `docs/NEON.md` in bkesari-platform). Each server's `.env` holds only its own tier's URLs.

**Where the URLs come from.** Neon console → the project → branch `test` → **Connect** → database `bk_edu_standalone`, role `bk_edu_standalone_app`. With **Connection pooling** on, the string is `DB_URL`; with it off, `DB_URL_UNPOOLED`. (bkesari-platform's `tools/neon_provision.mjs` writes the same two lines to `~/.bkesari_neon/test.env`.) The `.env` database block:

```
DB_CONNECTION=pgsql
DB_URL=postgresql://bk_edu_standalone_app:<password>@ep-<endpoint>-pooler.<region>.aws.neon.tech/bk_edu_standalone?sslmode=require&channel_binding=require
DB_URL_UNPOOLED=postgresql://bk_edu_standalone_app:<password>@ep-<endpoint>.<region>.aws.neon.tech/bk_edu_standalone?sslmode=require&channel_binding=require
```

`DB_URL` overrides the `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/`DB_SSLMODE` lines, which can stay as they are.

**Set both.** The pooled endpoint is PgBouncer in transaction mode. Web requests use `DB_URL`; when `DB_URL_UNPOOLED` is set, Laravel's pooler support (13.26+, which `composer.lock` pins) sends `php artisan migrate`, `migrate:fresh` and `db:wipe` over it instead, since schema work belongs on a direct connection. `config/database.php` makes the connection pooler-safe either way: every query goes out as one unnamed statement (PHP's named prepared statements break inside transactions on a pooler), and nothing is set per session on connect (the client encoding travels in the startup packet; no `search_path`, time zone or isolation `SET`, which the pooler would apply to one server connection only — the server's default `search_path` resolves to `public`, so don't create a schema named after the role). `channel_binding=require` is passed to libpq (`app/Database/PostgresConnector.php`), so it is enforced, not dropped.

**Scale to zero.** An idle Neon compute is suspended and its connections dropped; the first request afterwards waits while it wakes. Each PHP request opens its own connection, so a dropped idle connection costs nothing, and PHP's PostgreSQL driver waits up to 30 s to connect (its default), well above the wake-up time.

Before switching the site over, check on the PHP host (over SSH):

1. **`pdo_pgsql` is enabled for the PHP version that serves the site** — `php -m | grep -i pdo_pgsql`, and from the web side (hPanel → Advanced → PHP Configuration → Extensions). Without it the app cannot connect at all.
2. **libpq is 14 or newer** — `php -i | grep -i 'libpq'`. Neon routes connections on the TLS server name, which older libpq does not send ("Endpoint ID is not specified"); `channel_binding` needs 13+.
3. **Outbound port 5432 is open** — `nc -vz ep-<endpoint>.<region>.aws.neon.tech 5432`.

`citext` (one migration needs it) is created by the migrations; Neon lets the database owner create it, no superuser needed. These checks have not been run against the live account — they're what to confirm, not a record of what's been confirmed.

**Moving off the old MySQL database:** it held only seeded synthetic data, so don't copy it: run the migrations + seeders fresh on Neon (step 5 below), or import the `database/sql/` files.

## Why this layout

Standard shared-hosting Laravel pattern: the full app lives **outside** the web-servable directory, and only `public/`'s contents (plus an `index.php` with adjusted paths) live in `public_html`. This keeps `.env`, `app/`, `vendor/`, etc. unreachable over HTTP without needing to change the account's document root.

```
~/domains/edutest.agtci.com/
  ├── edu-app/          <- full Laravel app (git clone lives here), NOT web-servable
  │     ├── app/ database/ routes/ vendor/ .env  ...
  │     └── public/      <- source of truth for web assets
  └── public_html/       <- actual web root; contents copied from edu-app/public
        ├── index.php     (paths adjusted to point at ../edu-app/...)
        ├── build/        (compiled Vite assets — committed to git, no Node needed here)
        └── storage -> ../edu-app/storage/app/public   (symlink)
```

## One-time initial deploy

```bash
# 1. SSH in
ssh -p 65002 u879099820@93.127.208.167

# 2. Clone the app (public repo, no auth needed)
cd ~/domains/edutest.agtci.com
git clone https://github.com/sanjaymorankar-debug/edu.git edu-app
cd edu-app

# 3. Install PHP dependencies (production only)
composer install --no-dev --optimize-autoloader

# 4. Configure environment
cp .env.example .env
nano .env   # set DB_URL and DB_URL_UNPOOLED (see "Database: Neon" above),
            # confirm APP_URL=https://edutest.agtci.com
php artisan key:generate --force

# 5. Migrate + seed (first time only — NEVER migrate:fresh after this).
#    migrate runs over DB_URL_UNPOOLED, the seeders over DB_URL.
php artisan migrate --force
php artisan db:seed --force
#    (Or, instead of these two commands, import database/sql/01-schema.sql,
#     02-reference-data.sql and, on a test site only, 03-demo-data.sql into the
#     empty PostgreSQL database with psql, pgAdmin or the provider's SQL
#     console — see database/sql/README.md)

# 6. Wire up public_html to serve the app
rm -f ~/domains/edutest.agtci.com/public_html/default.php
cp -r public/. ~/domains/edutest.agtci.com/public_html/
ln -s ../edu-app/storage/app/public ~/domains/edutest.agtci.com/public_html/storage

# 7. Fix public_html/index.php to point one level up
# Change:
#   require __DIR__.'/../vendor/autoload.php';
#   require_once __DIR__.'/../bootstrap/app.php';
# To:
#   require __DIR__.'/../edu-app/vendor/autoload.php';
#   require_once __DIR__.'/../edu-app/bootstrap/app.php';

# 8. Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Redeploying after changes

```bash
cd ~/domains/edutest.agtci.com/edu-app
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
cp -r public/. ~/domains/edutest.agtci.com/public_html/
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If frontend assets changed, rebuild them **locally first** (`npm run build`) and commit `public/build/` before pulling on the server — there's no Node.js on Hostinger to build them there.

## Rollback

```bash
cd ~/domains/edutest.agtci.com/edu-app
git log --oneline -5        # find the last-good commit
git checkout <commit-hash>
composer install --no-dev --optimize-autoloader
cp -r public/. ~/domains/edutest.agtci.com/public_html/
php artisan config:cache
```

Database migrations are additive in this build (no destructive migrations shipped) — a code rollback doesn't require a matching DB rollback unless a specific migration is known to be destructive. Always back up before any migration:

```bash
pg_dump --format=custom --no-owner "$DB_URL_UNPOOLED" -f backup-$(date +%Y%m%d-%H%M%S).dump
```

Use the direct URL, not the pooled one. `pg_dump` must be the same major version as the server or newer; if the Hostinger shell doesn't have a suitable `pg_dump`, run it from another machine, or use Neon's own history (console → Restore, to any point in the branch's history window). See [`DATABASE.md`](DATABASE.md#backups) for restore.

## Mail delivery

Production `.env` uses `MAIL_MAILER=sendmail` (Hostinger's local relay — no external SMTP credentials needed) with `MAIL_FROM_ADDRESS=noreply@edutest.agtci.com`. This was chosen over a third-party SMTP provider specifically to avoid managing external credentials, at the cost of no delivery guarantee — shared-hosting sendmail relays are commonly rate-limited or land in spam. Every send is wrapped in try/catch (`App\Livewire\Concerns\SendsMailSafely`), so a mail failure never breaks registration/invite flows, and all three mail-sending flows (school-staff invites, school-to-member invitations, parent-registered child accounts) still show the credential/invite-link on screen as the reliable fallback. If deliverability proves too unreliable in practice, revisit with a transactional provider — see `ROADMAP.md`.

## Scheduled analytics recalculation

`php artisan analytics:recalculate` is scheduled hourly in `routes/console.php`, but Laravel's scheduler only fires when something calls `php artisan schedule:run` — on a real server that means an actual OS cron entry, once per minute:

```bash
* * * * * cd ~/domains/edutest.agtci.com/edu-app && php artisan schedule:run >> /dev/null 2>&1
```

Register this via `crontab -e` over SSH (Hostinger's shared-hosting `disable_functions` doesn't block `crontab` itself, only the process-spawning PHP functions listed below). Confirm it's registered with `crontab -l`. If it can't be confirmed working, the platform still functions correctly without it: the National/Researcher dashboards compute-and-save a snapshot on the spot if none exists, and the National/State dashboards both have a manual "Recalculate now" button.

## Known Hostinger PHP restrictions

This account's PHP has `disable_functions` including `proc_open`, `symlink`, `link`, `exec`, `shell_exec`. Consequences:

- `php artisan storage:link` **fails silently-ish** (uses `symlink()`). Create the symlink from the shell instead: `ln -sfn ../edu-app/storage/app/public public_html/storage`.
- Some Composer post-install scripts that shell out (observed once with `laravel/pail`'s discovery step) can throw a `proc_open` error mid-`composer install`. It's usually non-fatal — verify with `php artisan --version` and check `bootstrap/cache/packages.php` exists; re-run `php artisan package:discover --ansi` directly if needed.
- Don't rely on `Process`/`Symfony\Process`-based artisan features (e.g. `artisan serve`, anything that shells out) in production — they won't work here. The app runs through Apache/PHP-FPM normally, which doesn't need any of this.

## Safety notes

- `edu-app/` is outside `public_html`, so `.env` and `storage/logs` are never web-reachable.
- The app's role (`bk_edu_standalone_app`) owns only this database — never use the Neon project owner role — and both URLs keep `sslmode=require&channel_binding=require`.
- `APP_DEBUG=false` in production `.env` — never flip this on live, it would leak stack traces (including DB credentials in error contexts) to any visitor.
- The `edutest.agtci.com` subdomain was newly created for this project and had no prior content beyond Hostinger's default placeholder page (`public_html/default.php`) — no existing site was overwritten.

## Branches and promotion

| Branch | Environment |
|---|---|
| `staging` | A separate staging subdomain, deployed from `staging` the same way (`git pull origin staging`). It needs its own database; no Neon database is provisioned for it, and it must never use `bk_edu_standalone` |
| `main` | `edutest.agtci.com` (current live site) |

Work happens on feature branches. Open a PR into `staging`; CI (`.github/workflows/ci.yml`, which migrates, seeds and runs the full test suite against PostgreSQL 16) must pass before merging. Test on staging, then promote with a PR from `staging` into `main`. Don't commit directly to `staging` or `main`.
