# Testing

```bash
php artisan test
```

130 tests, PHPUnit 12 via Laravel's test runner (there is no Pest in this project). With no extra configuration this runs against in-memory SQLite (`phpunit.xml`), so it needs no database server; 3 PostgreSQL-specific tests are skipped there. [`TEST_SPEC.md`](TEST_SPEC.md) is the full test specification: every check, in order, with what counts as passing.

### Against PostgreSQL (what CI runs)

PostgreSQL is the production database, so run the suite against it before merging anything that touches queries or migrations. Create an empty database once, then export the `DB_*` variables (they take precedence over `phpunit.xml`'s SQLite defaults):

```bash
createdb -h 127.0.0.1 -U postgres edu_test

DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
DB_DATABASE=edu_test DB_USERNAME=postgres DB_PASSWORD=postgres \
php artisan test
```

All 130 pass on PostgreSQL 16. Export the variables: a `.env.testing` cannot do this, because `phpunit.xml` sets `DB_CONNECTION` and `DB_DATABASE` before Laravel reads any file. Tests use `RefreshDatabase`, which drops and recreates every table in that database — never point it at a database you care about. `tests/TestCase.php` refuses to start when the database host is not local (loopback, a unix socket, or a one-word container name), and `phpunit.xml` blanks `DB_URL` / `DB_URL_UNPOOLED`, so the Neon URLs in a server's `.env` never reach the suite. CI (`.github/workflows/ci.yml`) runs exactly this against a `postgres:16` service container, then a `migrate:fresh --seed` smoke run of every migration and seeder using Neon-style `DB_URL` / `DB_URL_UNPOOLED`.

The checks that the app behaves on Neon as it does here (pooled endpoint, TLS, dropped idle connections, a non-superuser owner role) need a PgBouncer in transaction mode and are described in `TEST_SPEC.md` (level S7).

## What's covered

- **Auth** (`tests/Feature/Auth/*`, from Breeze): registration (including the added role picker), login, logout, password reset/confirm, email verification
- **Complaint lifecycle** (`tests/Feature/Platform/ComplaintFlowTest.php`): verified-parent submission → complaint ID generated → unverified users blocked → full submit → school response → resolution proposed → parent confirms "yes" → resolved; confirming "no" escalates
- **RBAC boundaries** (`tests/Feature/Platform/RbacBoundaryTest.php`): school admin can't view another school's complaint; district officer can't view complaints outside their jurisdiction (and can within it); an unrelated parent can't view someone else's complaint; a school_admin role with no actual school_staff row is blocked
- **Anonymization** (`tests/Feature/Platform/AnonymizationTest.php`): `complaints`/`school_feedback` tables have no user-identifying columns at the schema level; a complaint's HTML never renders the submitter's real name/email to a school admin; `IdentityResolutionService::resolve()` requires the `access-protected-identity` permission, a non-empty reason (throws `ValidationException` on a blank/whitespace-only one, and logs nothing when it does), and always writes an `identity_access_logs` row on success; the same user gets a stable `anonymous_ref` across multiple submissions to the same school
- **Appeals** (`tests/Feature/Platform/AppealsTest.php`): submitter can file one appeal per escalated complaint, a second attempt is blocked, an unrelated user can't file on someone else's complaint, a State Officer in jurisdiction can review and decide, a District Officer cannot review an appeal against their own district, an out-of-jurisdiction State Officer is blocked
- **Academic records** (`tests/Feature/Platform/AcademicRecordsTest.php`): School Admin can add a record for a verified student at their own school; blocked for a student not verified there; a different school's admin can't reach the page at all
- **Notifications** (`tests/Feature/Platform/NotificationsTest.php`): approving a parent relationship sends `RelationshipApproved` to that parent (via `Notification::fake()`); a real (non-faked) notification can be listed and marked read through `/notifications`
- **Two-factor auth** (`tests/Feature/Platform/TwoFactorAuthTest.php`): setup + confirm with a valid TOTP generates 8 recovery codes; confirming with a wrong code fails and leaves 2FA unconfirmed; login redirects to the challenge screen when 2FA is enabled without authenticating yet; the challenge completes login with a valid TOTP code, rejects an invalid one, locks the account's challenge for a minute after 5 wrong codes (even a right code or a recovery code is refused then), and a recovery code works exactly once
- **Complaint numbers** (`tests/Feature/Platform/ComplaintNumberTest.php`): numbering continues after a missing number instead of reusing a taken one, and past 999999, and a number taken by a concurrent submission is retried with the next one instead of failing
- **Database connection** (`tests/Feature/DatabaseConnectionTest.php`): `DB_URL_UNPOOLED` becomes the direct connection that migrations use; without it, migrations use `DB_URL`; both send unnamed statements and no session `SET`s (pooler-safe); `channel_binding` from the URL reaches libpq; the suite refuses a hosted database host
- **Admin panel additions** (`tests/Feature/Platform/AdminPanelTest.php`): System Admin can review (dismiss) a fraud flag with reviewer/timestamp recorded, update moderation thresholds (persisted via the `Setting` model), toggle a role's permission, and assign/remove a role from a user found by email
- **Teacher Effectiveness value-add** (`tests/Feature/Platform/TeacherEffectivenessTest.php`): a teacher with a subject specialization and two `student_academic_records` showing improvement gets a `value_add` component in `component_breakdown`; a teacher with no subject specialization gets none
- **PostgreSQL compatibility** (`tests/Feature/Platform/DatabaseCompatibilityTest.php`): school search on the public directory and the onboarding picker is case-insensitive; on PostgreSQL, login, the `unique:users,email` rule and the admin user-by-email lookup match emails regardless of case (the `citext` column — see `DATABASE.md`), as they did on MySQL
- **Fraud-flag auto-creation** (`tests/Feature/Platform/SchoolFeedbackTest.php`): a burst of 5 feedback submissions for the same school within the default window creates exactly one open `feedback_spike` flag

## Manual browser verification (done for this build)

Beyond the automated suite, the following was walked through live in a browser against the seeded local dataset:
- School search/filter and public school profile pages
- Full complaint cycle as an actual logged-in parent → school admin → parent again, confirming the UI (not just the underlying HTTP calls) shows the right thing at each step
- Confirmed the school admin's complaint view shows only the `ANON-...` reference, never the parent's real name
- District Officer dashboard, scoped correctly to their jurisdiction, with severity-first ordering and child-safety flags visible
- Full 2FA setup → confirm → recovery-codes-shown-once flow, end to end in the browser (secret key generated, 8 recovery codes rendered, `two_factor_authentications.confirmed_at` set)
- `/admin/fraud-flags`, `/admin/roles`, `/admin/moderation`, and `/notifications` all render correctly with real seeded data and empty-state messaging where applicable
- The System Admin dashboard's new Fraud Flags / Roles & Permissions / Moderation Settings tiles link correctly

## What's not tested

- Load/performance testing at scale (spec envisions 100k+ schools — this build has 20)
- Security penetration testing (SQLi/XSS/CSRF/IDOR beyond what Laravel's defaults + the RBAC tests above cover)
- Cross-browser/mobile-viewport testing beyond default desktop Chrome
- Actual mail deliverability in production (spam placement, bounce handling) — see `ROADMAP.md`
- Whether the `analytics:recalculate` hourly schedule is actually firing via a real host cron entry, vs. relying on the manual "Recalculate now" button / same-request fallback — see `DEPLOYMENT.md`
- Anything in `ROADMAP.md`'s deferred list, since it isn't built
