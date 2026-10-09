# SQL files for importing the PostgreSQL database

For when you'd rather import the database than run `php artisan migrate` over
SSH (e.g. into a managed PostgreSQL service from your own machine or the
provider's console). Both routes produce the same schema; pick one.

| File | What it is |
|---|---|
| `01-schema.sql` | The exact SQL `php artisan migrate` runs on PostgreSQL for all 41 migrations (what `migrate --pretend` prints), plus the `migrations` table filled in so a later `php artisan migrate --force` is a no-op. Runs `CREATE EXTENSION IF NOT EXISTS citext`. |
| `02-reference-data.sql` | Roles + permissions, states, districts, complaint categories, school/teacher rating components. No users. `ON CONFLICT DO NOTHING`, so re-importing is harmless. |
| `03-demo-data.sql` | **Optional, test/staging only.** The synthetic accounts in `TEST_ACCOUNTS.md` (password `Password123!`) and their schools, complaints and feedback. Never on a real production database. |

The data files use explicit ids and finish each table with `setval(...)` on
its id sequence, so rows the app creates afterwards don't collide with the
imported ones.

## Importing

Create an **empty** database first (there's no `CREATE DATABASE` in these
files), owned by — or at least writable by — the role the app will connect
as. Import as that role, so the tables are owned by it. Then import 01, 02
and optionally 03, in that order. Don't import 01 into a database that
already has these tables.

**psql** (any machine that can reach the database):

```bash
export PGHOST=<host> PGPORT=5432 PGUSER=<user> PGDATABASE=<database> PGSSLMODE=require
psql -v ON_ERROR_STOP=1 -1 -f database/sql/01-schema.sql
psql -v ON_ERROR_STOP=1 -1 -f database/sql/02-reference-data.sql
psql -v ON_ERROR_STOP=1 -1 -f database/sql/03-demo-data.sql   # test/staging only
```

`-1` runs each file in one transaction, so a failed import leaves nothing
half-done. `psql` prints one `NOTICE: identifier ... will be truncated` for a
long index name; `php artisan migrate` produces the same notice and the same
index name, so it's harmless.

**pgAdmin:** connect to the server, select the new database, open
*Query Tool*, then *Open File* → `01-schema.sql` → *Execute*. Repeat for 02
and (optionally) 03.

**A provider's web SQL console/editor:** paste or upload each file's contents
in the same order. Consoles differ in size limits and in whether they run a
whole file as one transaction; `03-demo-data.sql` is about 700 KB, so if
the console rejects it, use psql or pgAdmin instead.

If `CREATE EXTENSION citext` fails, the import role isn't allowed to create
extensions: enable `citext` through the provider's extension settings (or ask
the database owner), then re-run 01 on a fresh empty database. Both this
file and the migration it mirrors look for `citext` in the `public` schema
(the app's `search_path`); if the provider pre-installs extensions in another
schema, install `citext` in `public` instead.

Without 03 there is no login yet: create the first admin with
`php artisan tinker`, or import 03 on a test site.

## Regenerating after a new migration

```bash
createdb edu_sqlgen        # a scratch database; it gets migrate:fresh --seed
PGHOST=127.0.0.1 PGPORT=5432 PGUSER=postgres PGPASSWORD=... \
  database/sql/regenerate.sh edu_sqlgen
```

`regenerate.sh` rebuilds the scratch database with
`php artisan migrate:fresh --seed`, writes 01 from the migrations' own SQL,
and writes 02/03 with `pg_dump --data-only --column-inserts` (FK-ordered, with
sequence `setval`s). It strips pg_dump's `\restrict` lines so the files also
work outside psql. The demo data is re-randomised by the seeders each time,
apart from the fixed accounts in `TEST_ACCOUNTS.md`.

Check by importing the three files into another empty database and comparing:
`pg_dump --schema-only` of the two databases must be identical, the row counts
must match, and `php artisan migrate --force` against the imported database
must say "Nothing to migrate".
