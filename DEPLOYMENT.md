# Deployment

**Live target:** `https://edutest.agtci.com` (domain root — not a subpath)
**Hosting account (current):** shared Hostinger hosting, no Node.js available server-side, PHP 8.3 + Composer + git available via SSH.
**Database:** PostgreSQL 14+ (tested on 16). MySQL/MariaDB are no longer supported.

## ⚠️ Database requirement — read before the next deploy

The app now runs **only on PostgreSQL**. Hostinger *shared* hosting provides MySQL/MariaDB only — it has **no PostgreSQL**, so the current account cannot host this app's database as-is. Before deploying this version, the hosting target must provide:

1. **A PostgreSQL database** the web server can reach — either PostgreSQL on the same server (e.g. a VPS/dedicated plan where you install it), or a managed PostgreSQL service. If the database is on another machine, confirm the web host allows outbound connections to its port (shared hosts often block them), use `DB_SSLMODE=require`, and restrict database access to the web server's IP.
2. **PHP with the `pdo_pgsql` extension** enabled for both the CLI (artisan, migrations) and the web SAPI (PHP-FPM/Apache). Check over SSH with `php -m | grep -i pgsql` (expect `pdo_pgsql` and `pgsql`), and for the web side with a temporary `phpinfo()` page that you delete immediately afterwards.
3. **`pg_dump`/`pg_restore`** available somewhere that can reach the database, for backups (see Rollback below).

If any of these is missing, the site will fail with `could not find driver` or a connection error on the first request. The steps below assume they're in place; the file layout is unchanged from the shared-hosting setup, so they apply equally to a VPS with the same directory structure.

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
php -m | grep -i pgsql   # must list pdo_pgsql — see "Database requirement" above
cp .env.example .env
nano .env   # fill in DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD (+ DB_SSLMODE=require for a remote DB), confirm APP_URL=https://edutest.agtci.com
php artisan key:generate --force
php artisan migrate:status   # quick connectivity check — should list pending migrations, not a connection error

# 5. Migrate + seed (first time only — NEVER migrate:fresh after this)
php artisan migrate --force
php artisan db:seed --force
#    (Or import database/sql/01-schema.sql, 02-reference-data.sql and, on a test
#     site, 03-demo-data.sql with psql or pgAdmin instead — see database/sql/README.md)

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
pg_dump -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -Fc -f ~/backups/edu-$(date +%Y%m%d-%H%M%S).dump <DB_DATABASE>
```

Keep backups outside `public_html`. Restore (into an emptied database) with `pg_restore -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -d <DB_DATABASE> --no-owner --clean --if-exists <file>.dump`. See [`DATABASE.md`](DATABASE.md) for details, and for moving existing data off the old MySQL database.

## Mail delivery

Production `.env` uses `MAIL_MAILER=sendmail` (Hostinger's local relay — no external SMTP credentials needed) with `MAIL_FROM_ADDRESS=noreply@edutest.agtci.com`. This was chosen over a third-party SMTP provider specifically to avoid managing external credentials, at the cost of no delivery guarantee — shared-hosting sendmail relays are commonly rate-limited or land in spam. Every send is wrapped in try/catch (`App\Livewire\Concerns\SendsMailSafely`), so a mail failure never breaks registration/invite flows, and all three mail-sending flows (school-staff invites, school-to-member invitations, parent-registered child accounts) still show the credential/invite-link on screen as the reliable fallback. If deliverability proves too unreliable in practice, revisit with a transactional provider — see `ROADMAP.md`.

## Scheduled analytics recalculation

`php artisan analytics:recalculate` is scheduled hourly in `routes/console.php`, but Laravel's scheduler only fires when something calls `php artisan schedule:run` — on a real server that means an actual OS cron entry, once per minute:

```bash
* * * * * cd ~/domains/edutest.agtci.com/edu-app && php artisan schedule:run >> /dev/null 2>&1
```

Register this via `crontab -e` over SSH (Hostinger's shared-hosting `disable_functions` doesn't block `crontab` itself, only the process-spawning PHP functions listed below). Confirm it's registered with `crontab -l`. If it can't be confirmed working, the platform still functions correctly without it: the National/Researcher dashboards compute-and-save a snapshot on the spot if none exists, and the National/State dashboards both have a manual "Recalculate now" button.

## Known Hostinger PHP restrictions

These apply to the current shared Hostinger account; re-check them if the app moves to a host that provides PostgreSQL. This account's PHP has `disable_functions` including `proc_open`, `symlink`, `link`, `exec`, `shell_exec`. Consequences:

- `php artisan storage:link` **fails silently-ish** (uses `symlink()`). Create the symlink from the shell instead: `ln -sfn ../edu-app/storage/app/public public_html/storage`.
- Some Composer post-install scripts that shell out (observed once with `laravel/pail`'s discovery step) can throw a `proc_open` error mid-`composer install`. It's usually non-fatal — verify with `php artisan --version` and check `bootstrap/cache/packages.php` exists; re-run `php artisan package:discover --ansi` directly if needed.
- Don't rely on `Process`/`Symfony\Process`-based artisan features (e.g. `artisan serve`, anything that shells out) in production — they won't work here. The app runs through Apache/PHP-FPM normally, which doesn't need any of this.

## Safety notes

- `edu-app/` is outside `public_html`, so `.env` (including the PostgreSQL credentials) and `storage/logs` are never web-reachable.
- `APP_DEBUG=false` in production `.env` — never flip this on live, it would leak stack traces (including DB credentials in error contexts) to any visitor.
- The `edutest.agtci.com` subdomain was newly created for this project and had no prior content beyond Hostinger's default placeholder page (`public_html/default.php`) — no existing site was overwritten.

## Branches and promotion

| Branch | Environment |
|---|---|
| `staging` | A separate staging subdomain with its own PostgreSQL database, deployed from `staging` the same way (`git pull origin staging`) |
| `main` | `edutest.agtci.com` (current live site) |

Work happens on feature branches. Open a PR into `staging`; CI (`.github/workflows/ci.yml`, which runs the suite and a full migrate + seed against PostgreSQL 16) must pass before merging. Test on staging, then promote with a PR from `staging` into `main`. Don't commit directly to `staging` or `main`.
