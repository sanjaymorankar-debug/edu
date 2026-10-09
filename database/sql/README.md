# SQL files for a PostgreSQL import

For when you'd rather import the database than run `php artisan migrate` over
SSH. Both routes produce the same schema, so pick one. PostgreSQL 14+ (tested on 16).
MySQL/MariaDB are not supported.

| File | What it is |
|---|---|
| `01-schema.sql` | All 40 migrations' DDL (generated with `php artisan migrate --pretend`), plus the `migrations` table filled in so a later `php artisan migrate --force` is a no-op. |
| `02-reference-data.sql` | Roles + permissions, states, districts, complaint categories, school/teacher rating components. No users. `ON CONFLICT DO NOTHING`, so re-importing is harmless. |
| `03-demo-data.sql` | **Optional, test/staging only.** The synthetic accounts in `TEST_ACCOUNTS.md` (password `Password123!`) and their schools, complaints and feedback. Never on a real production database. |

Each file runs in a single transaction, so a failed import leaves nothing
half-applied. Each file ends by moving the id sequences past the imported
ids, so new rows don't collide with imported ones.

## Importing

Create an **empty** database first. There's no `CREATE DATABASE` in the files
(see `DATABASE.md` → "Creating the database", or use your provider's console).
Import as the role the app connects with, so it owns the tables.

**psql:**

```bash
psql -v ON_ERROR_STOP=1 -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -d <DB_DATABASE> -f database/sql/01-schema.sql
psql -v ON_ERROR_STOP=1 -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -d <DB_DATABASE> -f database/sql/02-reference-data.sql
# test/staging only:
psql -v ON_ERROR_STOP=1 -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -d <DB_DATABASE> -f database/sql/03-demo-data.sql
```

`-v ON_ERROR_STOP=1` matters. Without it psql carries on past an error.

**pgAdmin:** right-click the empty database → *Query Tool* → open `01-schema.sql`
→ *Execute script* (F5). Repeat for 02 and, optionally, 03. The files contain
only plain SQL (no psql `\` meta-commands), so the Query Tool can run them.

Without 03 there's no login yet. Create the first admin with
`php artisan tinker`, or import 03 on a test site.

Afterwards `php artisan migrate --force` must say "Nothing to migrate".

## Regenerating after a new migration or seeder change

Don't edit these files by hand. Regenerate them from the migrations and seeders against a
throwaway, empty database:

```bash
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE edu_sqlgen"
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=edu_sqlgen \
  DB_USERNAME=postgres DB_PASSWORD=... database/sql/generate.sh
psql -h 127.0.0.1 -U postgres -c "DROP DATABASE edu_sqlgen"
```

`generate.sh` writes 01 from `php artisan migrate --pretend`, then migrates,
runs the reference seeders and dumps their tables with
`pg_dump --data-only --column-inserts` into 02. Then it runs the demo seeders
and dumps theirs into 03. It strips pg_dump's psql-only `\restrict` lines and
session settings, and replaces pg_dump's fixed `setval()` calls with resets
computed from `max(id)`. It warns if a seeder writes to a table that's in
neither list. The demo data uses Faker, so it changes on every run.

Check the result by importing the files into an empty database with
`psql -v ON_ERROR_STOP=1` and then running `php artisan migrate --force`. It
must say "Nothing to migrate".
