# Database

PostgreSQL (14+, tested on 16) in every real environment — production, staging and local dev. MySQL/MariaDB are not supported. The test suite can also run on in-memory SQLite (see `SETUP.md`). 40 tables (see `database/migrations/`).

## Core groups

**Location/school:** `states`, `districts`, `schools`, `school_profiles`

**Identity & RBAC:** `users` (real identity), `roles`/`permissions`/pivot tables (spatie/laravel-permission), `parent_profiles`, `student_profiles`, `teacher_profiles`, `school_staff`, `officer_jurisdictions`

**Anonymization bridge:** `anonymous_identities` — maps `user_id` → a stable `anonymous_ref` per school/context. This is the *only* table that can join a real user to their anonymized activity. See [`SECURITY_PRIVACY.md`](SECURITY_PRIVACY.md).

**Relationships:** `parent_school_relationships` (includes `student_user_id` — links a parent to their child's own account), `student_school_relationships`, `teacher_school_relationships` — verification status (`pending`/`verified`/`rejected`) gates who may submit complaints/feedback for a school.

**Invitations:** `invitations` — School Admin-initiated invite (email + role + optional student name) with a unique token; acceptance creates the relationship as `verified` directly, skipping the normal pending-approval step.

**Complaints:** `complaint_categories`, `complaints` (stores `anonymous_ref`, never `user_id`), `complaint_evidence`, `complaint_responses`, `complaint_status_history`, `complaint_resolutions`

**Ratings:** `school_feedback` (stores `anonymous_ref`), `school_rating_components` (admin-editable weights), `school_quality_scores` (historical snapshots, one row per recalculation)

**Teacher effectiveness:** `teacher_feedback` (stores `anonymous_ref`, never `user_id`), `teacher_rating_components` (admin-editable weights), `teacher_effectiveness_scores` (historical snapshots — privacy-restricted, see `SECURITY_PRIVACY.md`)

**Retaliation:** `retaliation_reports` (stores `anonymous_ref`, optional `complaint_id` link, same anonymization rule as complaints)

**Governance:** `audit_logs` (general action log), `identity_access_logs` (every reversal of an `anonymous_ref` back to a real user, now requiring a non-empty reason — see `IdentityResolutionService`)

**Appeals:** `appeals` — one per `complaint_id` (unique), reviewed by a State Officer/National Admin/System Admin one level above whoever handled the original complaint; `reason`/`status`/`decision_note`/`resolved_at`.

**Academic records & TEI value-add:** `student_academic_records` — School Admin-entered `subject`/`term`/`score`/`max_score` per student; feeds `TeacherEffectivenessIndexService`'s value-add component (school+subject proxy, not a real roster link — see `TeacherEffectivenessIndexService`'s doc comment).

**Fraud/moderation:** `fraud_flags` (`flag_type`, `subject_type`+`subject_id` resolving to a School or teacher User, `status` open/reviewing/dismissed/confirmed), `settings` (generic key/value store — currently holds `fraud.window_minutes`/`fraud.threshold`, admin-editable at `/admin/moderation`)

**2FA:** `two_factor_authentications` — deliberately a separate table from `users` (not a column) so an encrypted secret/recovery-code set is never accidentally exposed via a broad `User::all()` or `select *` query. `secret` is `encrypted`, `recovery_codes` is `encrypted:array`.

**Analytics:** `analytics_snapshots` — `scope` (national/state), `scope_id`, `metrics` (json), `calculated_at`. Populated by `php artisan analytics:recalculate` (see `app/Console/Commands/RecalculateAnalyticsSnapshots.php`), scheduled hourly. Read by the National/Researcher dashboards and the State Officer dashboard's summary numbers — never by the State dashboard's live complaint/retaliation queues.

**Notifications:** `notifications` — Laravel's standard database-notification table (uuid id, polymorphic `notifiable`, json `data`, `read_at`). Written to by `App\Notifications\*` classes on relationship-approval, complaint-status-change, invitation-acceptance, and appeal-decision events.

## Indexes

Search-relevant columns on `schools` (name, pincode, board, state/district), `complaints` (status, school_id+status, district_id+status, anonymous_ref), and `school_quality_scores` (school_id+calculated_at) are indexed. Nothing here is tuned for the 100k+-school scale the full spec envisions — see `ROADMAP.md` for aggregation-table/caching work that's deferred.

## PostgreSQL specifics

- **`enum` columns** are created by Laravel as `varchar` plus a `CHECK (col IN (...))` constraint (e.g. `complaints_status_check`). Adding a value to an enum later needs a migration that drops and re-creates that check constraint — there's no `ALTER TYPE`.
- **`json` columns** are native `json`. Compare/filter in PHP after casting, or use Laravel's `->` JSON path syntax; don't compare a json column with `=`.
- **Text comparisons are case-sensitive.** User-facing search uses `whereLike()` (→ `ILIKE`). Emails are stored lowercased by the `User`/`Invitation` models and lowercased again before every lookup — keep it that way, or two accounts can differ only by case.
- **Booleans** are real `boolean` columns — compare with `true`/`false`, never `1`/`0`.
- **Sequences:** ids come from per-table sequences. If you ever insert rows with explicit ids (e.g. a bulk import), reset the sequence afterwards: `SELECT setval(pg_get_serial_sequence('users','id'), (SELECT max(id) FROM users));`

## Creating the database

```sql
CREATE ROLE edu WITH LOGIN PASSWORD '<strong password>';
CREATE DATABASE edu_platform OWNER edu ENCODING 'UTF8';
```

The app role only needs to own its database; it doesn't need superuser. On a managed PostgreSQL service, create the role/database in the provider's console instead and copy the host/port/credentials into `.env` (set `DB_SSLMODE=require` if the provider enforces TLS).

## Migrating on the live server

```bash
php artisan migrate --force
```

`--force` is required in production since `APP_ENV=production` blocks interactive migration prompts. **Never run `migrate:fresh` against the live database** — it drops every table.

## Backups and restore

Back up before every migration/deploy:

```bash
pg_dump -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -Fc -f backup-$(date +%Y%m%d-%H%M%S).dump <DB_DATABASE>
```

`-Fc` is PostgreSQL's compressed custom format. Restore into an empty database with:

```bash
pg_restore -h <DB_HOST> -p <DB_PORT> -U <DB_USERNAME> -d <DB_DATABASE> --no-owner --clean --if-exists backup-YYYYMMDD-HHMMSS.dump
```

`pg_dump`'s major version must be ≥ the server's. Keep backups off the web root.

## Moving off the old MySQL database (one-time)

Earlier deployments ran on MySQL. All data in the test environment is synthetic, so the simplest path is a fresh PostgreSQL database with `php artisan migrate --force` + `php artisan db:seed --force`. If existing rows ever need to be carried over instead:

1. Create the schema on PostgreSQL with `php artisan migrate --force` (don't let a conversion tool create tables — the migrations define the constraints the app relies on).
2. Copy the data table by table (e.g. `pgloader` with `data only`, or a CSV export/`\copy` import), skipping the `migrations` table.
3. Lowercase emails, after checking nothing collides once case is ignored (MySQL treated these as equal; PostgreSQL won't):
   ```sql
   SELECT lower(email), count(*) FROM users GROUP BY 1 HAVING count(*) > 1;   -- must return no rows
   UPDATE users SET email = lower(email);
   UPDATE invitations SET email = lower(email);
   ```
4. Reset every table's id sequence (see "Sequences" above).
