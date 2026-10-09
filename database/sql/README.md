# SQL files for a phpMyAdmin deploy

For when you'd rather import the database than run `php artisan migrate` over
SSH. Both routes produce the same schema; pick one.

| File | What it is |
|---|---|
| `01-schema.sql` | All 40 migrations' DDL (generated with `php artisan migrate --pretend`), plus the `migrations` table filled in so a later `php artisan migrate --force` is a no-op. |
| `02-reference-data.sql` | Roles + permissions, states, districts, complaint categories, school/teacher rating components. No users. |
| `03-demo-data.sql` | **Optional, test/staging only.** The synthetic accounts in `TEST_ACCOUNTS.md` (password `Password123!`) and their schools, complaints and feedback. Never on a real production database. |

phpMyAdmin: select the **empty** database first (there's no `CREATE DATABASE`/`USE`,
so Hostinger's prefixed names work), then import 01, 02 and optionally 03.

Without 03 there is no login yet: create the first admin with
`php artisan tinker` or import 03 on a test site.

## Regenerating after a new migration

```bash
mysql -e "CREATE DATABASE edu_sqlgen"
DB_DATABASE=edu_sqlgen php artisan migrate --pretend --force --no-ansi   # -> 01 (lines starting ⇂)
DB_DATABASE=edu_sqlgen php artisan migrate --force
DB_DATABASE=edu_sqlgen php artisan db:seed --class=RolesAndPermissionsSeeder --force   # ...and the other reference seeders -> 02
```

Check by importing the files into an empty database and running
`php artisan migrate --force`: it must say "Nothing to migrate".
