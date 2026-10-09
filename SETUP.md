# Local Development Setup

## Requirements

- PHP 8.3+ with `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `gd`, `intl` (plus `pdo_sqlite` if you want the fast in-memory test run)
- PostgreSQL 14+ (CI and development are tested on PostgreSQL 16)
- Composer 2.x
- Node 18+ / npm (only needed for editing frontend assets — the server does not need Node)

PostgreSQL is the only supported database. MySQL/MariaDB are not supported.

## Create the database

As a PostgreSQL superuser (e.g. `sudo -u postgres psql`, or `psql -h 127.0.0.1 -U postgres`):

```sql
CREATE ROLE edu WITH LOGIN PASSWORD 'choose-a-local-password';
CREATE DATABASE edu_platform OWNER edu ENCODING 'UTF8';
```

## First-time setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Then edit the database block in `.env` to match the role/database you created:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=edu_platform
DB_USERNAME=edu
DB_PASSWORD=choose-a-local-password
DB_SSLMODE=prefer
```

For local work also set `APP_ENV=local`, `APP_DEBUG=true` and `APP_URL=http://127.0.0.1:8000` (`.env.example` ships production-safe values).

## Migrate + seed synthetic data

```bash
php artisan migrate:fresh --seed
```

This creates ~3 states, 5 districts, 20 schools, ~275 synthetic users (parents/students/teachers/officers/admins), ~100 complaints, and school feedback. **All data is fabricated** — see [`TEST_ACCOUNTS.md`](TEST_ACCOUNTS.md) for login credentials.

## Run locally

```bash
php artisan serve
```

Visit `http://127.0.0.1:8000`.

## Run tests

```bash
php artisan test
```

`phpunit.xml` points this at in-memory SQLite so it runs with no database setup. Because production is PostgreSQL, run it against PostgreSQL before pushing anything that touches queries or migrations (CI does both). Use a throwaway database — the suite wipes it:

```bash
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE edu_test OWNER edu"
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=edu_test \
  DB_USERNAME=edu DB_PASSWORD=choose-a-local-password php artisan test
```

Real environment variables override `phpunit.xml`'s `<env>` defaults, which is what makes this work.

See [`TESTING.md`](TESTING.md) for what's covered.

## Writing portable queries

PostgreSQL is stricter than MySQL was. In particular:

- Text comparisons are **case-sensitive**. Use `whereLike()` / `orWhereLike()` (compiles to `ILIKE` on PostgreSQL) for user-facing search, not `where(..., 'like', ...)`. Emails are stored lowercased (`User::email()` mutator) — lowercase user input before looking one up.
- Boolean columns must be compared with `true`/`false`, not `1`/`0`.
- Every non-aggregated column in a `select` must appear in `GROUP BY`.
- No MySQL-only functions (`IFNULL`, `GROUP_CONCAT`, `DATE_FORMAT`, `FIND_IN_SET`, …) or backtick-quoted identifiers in raw SQL.
