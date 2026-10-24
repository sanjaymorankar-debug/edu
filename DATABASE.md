# Database

PostgreSQL 16 in every environment (local dev, CI, staging, production). SQLite is used only for the zero-setup in-memory test run (`phpunit.xml`); CI runs the same suite against PostgreSQL. 41 migrations, ~50 tables (see `database/migrations/`).

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

## PostgreSQL notes

The schema was originally written for MySQL; these are the places where PostgreSQL behaves differently and what the app does about it:

- **Case-sensitive text comparison.** MySQL's `utf8mb4_unicode_ci` collation made `=` and `LIKE` case-insensitive; PostgreSQL does not. `users.email` is therefore a `citext` column on PostgreSQL (migration `2026_10_09_000000_make_user_email_case_insensitive_on_pgsql`, which runs `CREATE EXTENSION IF NOT EXISTS citext`), so login, password reset, `unique:users,email` and the admin email lookup still match regardless of case, and the unique index still rejects `Alice@x.com` next to `alice@x.com`. User-facing search uses `whereLike()`, which compiles to `ILIKE`. New code that searches free text should use `whereLike()` / `orWhereLike()`, not `where(..., 'like', ...)`. Machine-generated values (statuses, slugs, tokens, `anonymous_ref`, complaint numbers) are always written in one case, so their exact comparisons are unaffected.
- **`enum()` columns** become `varchar` + a `CHECK` constraint (Laravel's pgsql grammar), so invalid values are still rejected at the database level.
- **`unsigned*()` columns** have no unsigned equivalent: `unsignedInteger` is a signed `integer`, `unsignedSmallInteger` a `smallint`, `unsignedBigInteger` a `bigint`. Nothing stored here approaches those limits, but negative values are not rejected by the database — validate them in the app.
- **`json()` columns** are PostgreSQL `json`. `json` has no equality operator, so don't use `DISTINCT`, `GROUP BY`, or `=` on these columns (nothing currently does).
- **Booleans** are real `boolean`s; every boolean column has a `'boolean'` cast on its model, so PHP sees `true`/`false` on both drivers.
- **Grouping:** PostgreSQL requires every selected non-aggregate column to be in `GROUP BY` (the queries in `RecalculateAnalyticsSnapshots` already comply).
- **Transactions:** after any error inside a PostgreSQL transaction, every further statement in it fails until rollback — don't catch-and-continue on a `QueryException` inside `DB::transaction()`.

## Backups

```bash
pg_dump --format=custom --no-owner -h <DB_HOST> -U <DB_USERNAME> -d <DB_DATABASE> -f backup-$(date +%Y%m%d-%H%M%S).dump
# restore into an empty database:
pg_restore --no-owner -h <DB_HOST> -U <DB_USERNAME> -d <DB_DATABASE> backup-....dump
```

Use a `pg_dump` client whose major version is the same as or newer than the server's. Most managed PostgreSQL services also take automatic snapshots — check what your provider keeps and for how long.

## Migrating on the live server

```bash
php artisan migrate --force
```

`--force` is required in production since `APP_ENV=production` blocks interactive migration prompts. **Never run `migrate:fresh` against the live database** — it drops every table.
