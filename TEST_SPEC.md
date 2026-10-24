# Education (standalone) — test specification

This is the standalone Education repository (Laravel 13 + Livewire/Volt). It is **superseded by `bkesari-platform/edu`**, which is about 150 files ahead and is where new work goes. This copy is still the code behind **edutest.agtci.com**, and it is kept only until that site is retired, so test it for what that site needs. The site is served at the domain root (no bkesari.com base path): PHP on Hostinger, database PostgreSQL on Neon (branch `test`, database `bk_edu_standalone`). This spec covers everything in this repository: the PHP test suite on in-memory SQLite and on PostgreSQL, the migrations, seeders and SQL import files, the committed frontend build, a smoke run of the app, security hygiene, and Neon parity checks run against a local stand-in for Neon (TLS plus a PgBouncer in transaction mode). It does not cover `bkesari-platform/edu`, the live server, or Neon itself: nothing here ever connects to them. [`TESTING.md`](TESTING.md) has the short version and what each test file covers. [`SETUP.md`](SETUP.md), [`DATABASE.md`](DATABASE.md) and [`DEPLOYMENT.md`](DEPLOYMENT.md) explain the app, the database and the Neon setup.

Testing agent: `.claude/agents/edu-standalone-tester.md`. It runs automatically after every code change (the test gate, `.claude/hooks/test_gate.py`); run it by hand by asking Claude to "run the edu-standalone-tester agent".

## 1. Scope

| Component | Path | Language / framework |
|---|---|---|
| App core: routes, controllers, policies, services, models, providers | `app/`, `routes/`, `bootstrap/`, `config/` | PHP 8.3, Laravel 13.26 (pinned by `composer.lock`) |
| Pages, where most of the business logic lives | `resources/views/livewire/**` | Blade + Livewire 3 / Volt |
| PostgreSQL connector (passes `channel_binding` to libpq) | `app/Database/PostgresConnector.php` | PHP |
| Schema and seed data | `database/migrations/`, `database/seeders/` | Laravel migrations (PostgreSQL 16) |
| SQL import files (an alternative to `migrate` + `db:seed`) | `database/sql/01-03*.sql`, `regenerate.sh` | PostgreSQL SQL |
| Frontend, built and **committed** (Hostinger has no Node) | `resources/css`, `resources/js`, `public/build/` | Vite 8, Tailwind 3 |
| Tests | `tests/Unit`, `tests/Feature`, `tests/smoke/browser-smoke.cjs` | PHPUnit 12 (no Pest), Playwright |
| CI | `.github/workflows/ci.yml` | GitHub Actions |

Out of scope:

- `bkesari-platform/edu`. It is a separate, newer copy with its own spec; fixes found here are reported for cross-porting, not made there.
- The live server, Hostinger and Neon. There are no credentials here, and tests must never reach a hosted database. Neon behaviour is checked against the local stand-in (S7).
- Mail delivery (`MAIL_MAILER=array` in tests, `log` locally), the `staging` branch's deployment, and the host cron that runs `analytics:recalculate`.

## 2. Environment

**Tools.** CI (`.github/workflows/ci.yml`) uses PHP 8.3 with `mbstring, pdo_pgsql, pgsql`, Composer 2, Node 22 / npm 10, and a `postgres:16` service. Locally you need the same, plus `pdo_sqlite` for the zero-setup run and `psql`. S5b needs Playwright (not a project dependency: `npm i -g playwright` and `npx playwright install chromium` once). S7 needs a PostgreSQL with `ssl = on` and a PgBouncer 1.21+ in transaction mode that requires TLS.

**Scratch database.** PostgreSQL 16 on 127.0.0.1:5432, with its own non-superuser role that owns it and belongs to a `neon_superuser` stand-in role (on Neon, every app role is a member of `neon_superuser`). **The suite drops and recreates every table in it**, and S3/S5/S7 wipe and re-seed it, so it must be a throwaway database. On the Mac, once:

```bash
psql -h 127.0.0.1 -U postgres -v ON_ERROR_STOP=1 <<'SQL'
DO $$BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='neon_superuser')
  THEN CREATE ROLE neon_superuser NOLOGIN CREATEDB CREATEROLE; END IF; END$$;
CREATE ROLE edu_test LOGIN PASSWORD 'edu_test';
GRANT neon_superuser TO edu_test;
CREATE DATABASE edu_test OWNER edu_test;
SQL
```

Names used below are `edu_test` (role, database and password). The testing agent's container uses role and database `t_sedu` instead; any name works if it is local and throwaway. `tests/TestCase.php` refuses to start when the database host is not local (loopback, a unix socket, or a one-word container name).

**Shell prelude.** Every level below assumes this has been run in the repository root (bash or zsh):

```bash
export TEST_DB=edu_test TEST_DB_USER=edu_test TEST_DB_PASSWORD=edu_test
export PSQL_SUPER="psql -h 127.0.0.1 -U postgres"     # a superuser, for S7 only
# The suite and the app against the scratch database, with the Neon URLs blanked:
pgt()   { env DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE="$TEST_DB" \
              DB_USERNAME="$TEST_DB_USER" DB_PASSWORD="$TEST_DB_PASSWORD" DB_URL= DB_URL_UNPOOLED= "$@"; }
psqlt() { PGPASSWORD="$TEST_DB_PASSWORD" psql -h 127.0.0.1 -U "$TEST_DB_USER" -d "$TEST_DB" -v ON_ERROR_STOP=1 "$@"; }
# S7 only: the Neon-like endpoints (URL-encode the password if it has special characters).
export PG_DIRECT="postgresql://$TEST_DB_USER:$TEST_DB_PASSWORD@127.0.0.1:5432/$TEST_DB?sslmode=require&channel_binding=require"
export PG_POOLED="postgresql://$TEST_DB_USER:$TEST_DB_PASSWORD@127.0.0.1:6432/$TEST_DB?sslmode=require"
neon()  { env DB_CONNECTION=pgsql DB_URL="$PG_POOLED" DB_URL_UNPOOLED="$PG_DIRECT" "$@"; }
```

**Environment variables.**

| Variable | Used by | Value |
|---|---|---|
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | S3, S5 (`pgt`) | the scratch database. They must be **exported**: `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` before Laravel reads any file, so a `.env.testing` cannot override them |
| `DB_URL`, `DB_URL_UNPOOLED` | S7 (`neon`) only | the pooled and direct URLs. Blank everywhere else; `phpunit.xml` blanks them unless exported |
| `DB_SSLMODE` | S7 N2 | `require` |
| `BCRYPT_ROUNDS` | seeding | `4` (a seed at the default 12 takes over a minute) |
| `APP_ENV`, `APP_DEBUG` | smoke runs | `local`, `true` |
| `APP_KEY` | everything | written to `.env` by `php artisan key:generate` |
| `BASE_URL`, `SMOKE_SCHOOL_ID` | S5b | `http://127.0.0.1:8102`, and the query result given in S5b |
| Everything else in `.env` | — | as copied from `.env.example`; blank is fine |

**Ports.** The smoke run serves on **8102** (`php artisan serve --port=8102`). S7 uses 5432 (direct) and 6432 (pooled).

**One-time setup.**

```bash
composer install --no-interaction --prefer-dist --no-progress
cp .env.example .env && php artisan key:generate
npm ci
```

## 3. Standard suite

Run in order. Do not stop at the first failure.

| # | Level | Command | Passes when |
|---|---|---|---|
| S1a | static | `composer validate --no-check-publish` | prints `./composer.json is valid` (the lock file is current) |
| S1b | static | `find app bootstrap config database routes tests -name '*.php' -print0 \| xargs -0 -n1 -P2 php -l > /dev/null` | exit 0, nothing printed |
| S1c | static | `php artisan view:cache && php artisan view:clear` | exit 0: every Blade and Volt view compiles |
| S1d | static | `php artisan config:cache && php artisan route:cache && php artisan route:list --json \| python3 -c 'import json,sys; print(len(json.load(sys.stdin)))'; php artisan config:clear; php artisan route:clear` | the caches build (the deploy runs them) and 52 routes are listed |
| S1e | lint (report-only) | `vendor/bin/pint --test` | report-only. It lists 12 files as of the baseline (§5), all of them older than this spec. A file not in that list is a failure; fix it with `vendor/bin/pint <file>` |
| S1f | config | `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"` | parses |
| S2 | unit, zero-setup | `php artisan test` | 130 tests: 127 passed, 3 skipped, 0 failed. The 3 skips are the PostgreSQL-only email tests in `DatabaseCompatibilityTest` |
| S3a | integration (PostgreSQL) | `pgt php artisan db:wipe --force && pgt php artisan test --log-junit /tmp/edu-pg.xml` | 130 tests, 130 passed, **0 skipped** |
| S3b | integration | `psqlt -Atc "select count(*) from information_schema.tables where table_schema='public'; select udt_name from information_schema.columns where table_name='users' and column_name='email'"` | `50` and `citext`. S3a started from a wiped database, so this proves the suite really ran on PostgreSQL |
| S3c | integration | `pgt env BCRYPT_ROUNDS=4 php artisan migrate:fresh --seed --force && pgt php artisan analytics:recalculate && psqlt -Atc "select (select count(*) from states),(select count(*) from schools),(select count(*) from users),(select count(*) from complaints),(select count(*) from analytics_snapshots)"` | exit 0; `3\|20\|284\|100\|4` (every migration and seeder, plus the analytics queries' GROUP BY, on PostgreSQL as a non-superuser owner; 4 snapshots = national + 3 states) |
| S3d | integration | `pgt php artisan db:wipe --force && for f in 01-schema 02-reference-data 03-demo-data; do psqlt -q -1 -f database/sql/$f.sql > /dev/null \|\| echo "FAIL $f"; done; pgt php artisan migrate --force` | no `FAIL`, and migrate prints `Nothing to migrate`: the import files match the migrations |
| S4a | build | `npm ci && npx vite build --outDir /tmp/edu-build --emptyOutDir && diff -r /tmp/edu-build public/build` | the build succeeds and `diff` prints nothing: `public/build` is what the source produces. Hostinger serves it as committed, so a difference is a defect (S3): run `npm run build` and leave the new `public/build` in the working tree |
| S4b | build (report) | `git status --porcelain public/build` | report-only: anything listed is a rebuilt `public/build` that still has to be committed with the change; say so in the report |
| S5 | smoke | see S5a–S5c below | every probe prints `ok` and S5b prints `"result":"passed"` |
| S6 | security & hygiene | see S6a–S6e below | as stated per check |
| S7 | Neon parity | see N1–N9 below | as stated per check |

### S5 — smoke run

S5a re-seeds the scratch database, then starts the app on it:

```bash
# S5a: start the app on the scratch database and probe it
pgt env BCRYPT_ROUNDS=4 php artisan migrate:fresh --seed --force > /dev/null
pgt env APP_ENV=local APP_DEBUG=true php artisan serve --host=127.0.0.1 --port=8102 --no-reload > /tmp/edu-serve.log 2>&1 &
sleep 1; SERVE_PID=$(pgrep -f '^php artisan serve --host=127.0.0.1 --port=8102')   # not $!, which is the function's subshell
for i in $(seq 1 60); do curl -fs -o /dev/null http://127.0.0.1:8102/up && break; sleep 0.5; done

probe() {  # path, expected status, optional body text
  local out code; out=$(mktemp)
  code=$(curl -s -o "$out" -w '%{http_code}' "http://127.0.0.1:8102$1")
  if [ "$code" = "$2" ] && { [ -z "$3" ] || grep -q "$3" "$out"; }; then echo "ok   $1 $code"
  else echo "FAIL $1: got $code, want $2${3:+ with '$3'}"; fi
  rm -f "$out"
}
probe /up 200 'Application up'
probe / 200 'Search'
probe /schools 200 'Pune'
probe /schools/1 200 'About'
probe /schools/register 200 'Register'
probe /login 200 'Log in'
probe /build/manifest.json 200 'app.css'
probe /dashboard 302
probe /dashboard/admin 302
probe /admin/roles 302
probe /complaints/1 302
probe /complaints/1/evidence/1 302
probe /notifications 302
probe /two-factor-challenge 403
probe /.env 404
probe /composer.json 404
probe /storage/logs/laravel.log 403
```

The `302`s must all redirect to `/login` (`curl -s -o /dev/null -w '%{redirect_url}' http://127.0.0.1:8102/admin/roles`).

```bash
# S5b: sign in as the demo parent, open the school list, submit one complaint,
# see it on the dashboard, sign out; then confirm the row is in the database
SMOKE_SCHOOL_ID=$(psqlt -Atc "select r.school_id from parent_school_relationships r join users u on u.id = r.user_id where u.email = 'parent@test.agtci.com' and r.status = 'verified' order by r.school_id limit 1")
BASE_URL=http://127.0.0.1:8102 SMOKE_SCHOOL_ID=$SMOKE_SCHOOL_ID node tests/smoke/browser-smoke.cjs | tee /tmp/edu-smoke.json
psqlt -Atc "select complaint_number, status from complaints where subject = '$(python3 -c 'import json; print(json.load(open("/tmp/edu-smoke.json"))["subject"])')'"

# S5c: stop the server and check the port is free
kill $SERVE_PID; sleep 2; curl -s -o /dev/null --max-time 2 http://127.0.0.1:8102/up && echo "FAIL still running" || echo "ok   stopped"
```

S5b passes when the script prints `"result":"passed"` with the steps `signed in`, `school list shows …`, `submitted EDU-…`, `dashboard lists it`, `signed out`, and the query prints that complaint number with status `submitted`. Without Playwright, S5b is skipped with that reason; S5a still runs.

### S6 — security and hygiene

| # | Command | Passes when |
|---|---|---|
| S6a | `git ls-files \| grep -E '(^\|/)\.env($\|\.)' \| grep -v '\.example$'` | prints nothing: only `*.example` env files are tracked |
| S6b | `git grep -nIE 'npg_[A-Za-z0-9]{8,}\|AKIA[0-9A-Z]{16}\|-----BEGIN [A-Z ]*PRIVATE KEY' -- . ':!vendor' ':!node_modules'` | prints nothing (`npg_` is how Neon passwords start) |
| S6c | `grep -E '^(APP_ENV\|APP_DEBUG\|DB_URL\|DB_URL_UNPOOLED)=' .env.example` | `APP_ENV=production`, `APP_DEBUG=false`, and both URLs blank |
| S6d | the S5a probes | protected routes redirect anonymous users to `/login`; `/two-factor-challenge` is 403 without a password step; `.env`, `composer.json` and `storage/` are not served |
| S6e | `composer audit --no-dev; npm audit --omit=dev` | report-only: list advisories in the report; fix only when trivial and in scope |

### S7 — Neon parity

These prove the app behaves as it will on Neon: the pooled endpoint at runtime, the direct endpoint for migrations, TLS, a role that is not a superuser, and connections the server drops while idle. They need the S7 tools from §2. Run them after S5, with nothing else using the scratch database. **Skip rule:** if no TLS PgBouncer is available, mark S7 `SKIPPED` with that reason. That is acceptable for a `PASS` only when no changed file is in the S7 rows of the change-area map (§4 R3); otherwise the run is `BLOCKED`.

| # | Check | Command | Passes when |
|---|---|---|---|
| N1 | the role is an owner, not a superuser | `psql "$PG_DIRECT" -Atc "select rolsuper, pg_has_role(current_user, 'neon_superuser', 'member'), (select pg_get_userbyid(datdba) = current_user from pg_database where datname = current_database()) from pg_roles where rolname = current_user"` | `f\|t\|t` |
| N2 | the whole suite through the pooler, migrations over the direct URL | `env DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=6432 DB_SSLMODE=require DB_DATABASE="$TEST_DB" DB_USERNAME="$TEST_DB_USER" DB_PASSWORD="$TEST_DB_PASSWORD" DB_URL= DB_URL_UNPOOLED="$PG_DIRECT" php artisan test` | same counts as S3a |
| N3 | migrations never touch the pooled URL | `env DB_CONNECTION=pgsql DB_URL="${PG_POOLED/:$TEST_DB_PASSWORD@/:wrong@}" DB_URL_UNPOOLED="$PG_DIRECT" php artisan migrate:fresh --force` then the control `env DB_CONNECTION=pgsql DB_URL="${PG_POOLED/:$TEST_DB_PASSWORD@/:wrong@}" DB_URL_UNPOOLED= php artisan migrate:status`; then `psql "$PG_DIRECT" -Atc "select pg_get_userbyid(extowner) from pg_extension where extname = 'citext'"` | the first exits 0 although the pooled URL has a wrong password; the control fails with `authentication failed`; `citext` is owned by the app role |
| N4 | seeding (one long transaction) over the pooler | `neon env BCRYPT_ROUNDS=4 php artisan db:seed --force` | exit 0, and the S3c counts |
| N5 | TLS and `channel_binding` reach libpq | `env DB_CONNECTION=pgsql DB_URL="$PG_DIRECT" php artisan tinker --execute 'echo DB::selectOne("select ssl from pg_stat_ssl where pid = pg_backend_pid()")->ssl ? "tls\n" : "plain\n";'` then `env DB_CONNECTION=pgsql DB_URL="$PG_POOLED&channel_binding=require" php artisan tinker --execute 'DB::select("select 1"); echo "connected\n";'` | the first prints `tls`. The second prints `connected` on a pooler that offers SCRAM-SHA-256-PLUS (Neon does), or fails with `channel binding is required, but server did not offer` on PgBouncer (which does not). Either way the parameter reached libpq. Connecting to PgBouncer is a **failure**: it means `channel_binding` was dropped |
| N6 | smoke on the pooled endpoint | S5a and S5b again, with the server started as `env DB_CONNECTION=pgsql DB_URL="$PG_POOLED&application_name=edu_smoke_pooled" DB_URL_UNPOOLED="$PG_DIRECT" APP_ENV=local APP_DEBUG=true php artisan serve --host=127.0.0.1 --port=8102 --no-reload > /tmp/edu-serve.log 2>&1 &` and `SERVE_PID` taken with `pgrep` as in S5a; straight after S5b: `$PSQL_SUPER -Atc "select count(*) from pg_stat_activity where usename = '$TEST_DB_USER' and application_name = 'edu_smoke_pooled'"` | every probe `ok`, S5b `passed`, and the count ≥ 1 (the app really went through the pooler) |
| N7 | dropped idle connections | with the N6 server still running: `sleep 5; $PSQL_SUPER -Atc "SELECT count(pg_terminate_backend(pid)) FROM pg_stat_activity WHERE usename = '$TEST_DB_USER' AND pid <> pg_backend_pid()"`; then `kill -0 $SERVE_PID && echo alive`, `probe /schools 200 Pune`, and S5b again | ≥ 1 terminated, `alive`, `ok`, S5b `passed` |
| N8 | complaint numbers under real concurrency, through the pooler | the block below | `6` lines `ok=10`, then `60\|60`; no exception in the logs |
| N9 | control: the stand-in really is a transaction pooler | `neon php artisan tinker --execute 'config(["database.connections.pgsql.options" => [PDO::ATTR_EMULATE_PREPARES => false]]); DB::purge("pgsql"); DB::transaction(function () { foreach (range(1, 3) as $i) { DB::select("select ?::int", [$i]); } }); echo "no error\n";'` (PHP's default named prepared statements), then the same without the `config(...)`/`purge` part | the first fails with `25P02` (named prepared statements break on it); the second prints `no error` |

Stop the N6 server (S5c) after N9.

```bash
# N8
PROBE='$s = App\Models\School::with("state", "district")->find(1); $n = 0;
foreach (range(1, 10) as $i) { App\Models\Complaint::createNumbered($s->state, $s->district, [
  "school_id" => $s->id, "complaint_category_id" => 1, "district_id" => $s->district_id, "state_id" => $s->state_id,
  "anonymous_ref" => "ANON-CONCURRENCY", "submitted_role" => "parent", "subject" => "Concurrency probe",
  "description" => "Concurrency probe for complaint numbers.", "status" => "submitted"]); $n++; }
echo "ok=$n\n";'
for i in 1 2 3 4 5 6; do neon php artisan tinker --execute "$PROBE" > /tmp/edu-conc-$i.log 2>&1 & done; wait
grep -h 'ok=\|Exception' /tmp/edu-conc-*.log
psqlt -Atc "select count(*), count(distinct complaint_number) from complaints where subject = 'Concurrency probe'"
psqlt -qc "delete from complaints where subject = 'Concurrency probe'"
```

## 4. Regression suite

**R1 — the full suite.** S2 and S3a are the complete PHPUnit suite on both engines, and N2 runs it a third time through the pooler. The test count is the same in all three; only S2 may skip, and only the 3 PostgreSQL-only tests.

**R2 — critical regressions.** None of these may ever break.

| ID | Behaviour | Checked by |
|---|---|---|
| EDU-CR-01 | `complaints`, `school_feedback`, `teacher_feedback` and `retaliation_reports` have no user-identifying column | `AnonymizationTest::test_complaints_table_has_no_user_identifying_column`, `::test_school_feedback_table_has_no_user_identifying_column`, `TeacherEffectivenessTest::test_teacher_feedback_has_no_user_identifying_column`, `RetaliationReportTest::test_retaliation_report_has_no_user_identifying_column` |
| EDU-CR-02 | A school admin never sees the submitter's real name or email | `AnonymizationTest::test_complaint_detail_view_never_renders_submitters_real_name_or_email_to_school_admin` |
| EDU-CR-03 | Revealing an identity needs the permission and a reason, and is always logged | `AnonymizationTest::test_identity_resolution_requires_permission`, `::test_identity_resolution_requires_a_non_empty_reason`, `::test_identity_resolution_by_authorized_officer_logs_access` |
| EDU-CR-04 | No access across schools or jurisdictions | all of `RbacBoundaryTest`; `RetaliationReportTest::test_district_officer_outside_jurisdiction_cannot_view_report`; `NewDashboardsTest::test_state_officer_only_sees_complaints_in_their_state`, `::test_researcher_dashboard_shows_no_individual_complaint_links` |
| EDU-CR-05 | Complaint lifecycle: submit, respond, resolve; answering "no" escalates | all of `ComplaintFlowTest` |
| EDU-CR-06 | Evidence files are served only through the policy check | all of `ComplaintEvidenceTest`; S5a `/complaints/1/evidence/1` → 302 |
| EDU-CR-07 | A password alone never completes a 2FA login; 5 wrong codes lock the challenge; recovery codes work once | all of `TwoFactorAuthTest` |
| EDU-CR-08 | One appeal per complaint, reviewed only one level above | all of `AppealsTest` |
| EDU-CR-09 | Admin pages are for `system_admin` only | `AdminPanelTest::test_non_admin_cannot_reach_rating_weights_page`; S5a `/admin/roles`, `/dashboard/admin` → 302 |
| EDU-CR-10 | School search and email matching ignore case on PostgreSQL | all of `DatabaseCompatibilityTest` in S3a (0 skipped there) |
| EDU-CR-11 | Complaint numbers are unique and never fail a submission | all of `ComplaintNumberTest`; S7 N8 |
| EDU-CR-12 | Every migration and seeder runs on PostgreSQL as a non-superuser owner; the SQL import files match the migrations | S3c, S3d, S7 N1, N3 |
| EDU-CR-13 | Neon: runtime on `DB_URL`, migrations on `DB_URL_UNPOOLED`, pooler-safe statements, `channel_binding` honoured | all of `DatabaseConnectionTest`; S7 N2–N6, N9 |
| EDU-CR-14 | Tests never reach a hosted database | `DatabaseConnectionTest::test_tests_refuse_a_hosted_database`, `::test_tests_refuse_a_hosted_direct_endpoint`; `phpunit.xml` blanks `DB_URL` and `DB_URL_UNPOOLED` |
| EDU-CR-15 | The committed `public/build` is current | S4a (and CI's `git status` check after its build) |
| EDU-CR-16 | A feedback burst raises exactly one fraud flag | `SchoolFeedbackTest::test_a_burst_of_feedback_submissions_raises_a_fraud_flag` |
| EDU-CR-17 | Teacher effectiveness scores are visible only to the teacher | `TeacherEffectivenessTest::test_effectiveness_score_is_never_exposed_on_public_school_page`, `::test_teacher_can_see_their_own_score_but_no_one_elses_dashboard_shows_it` |
| EDU-CR-18 | The app keeps working after the server drops its idle connections (scale to zero) | S7 N7 |
| EDU-CR-19 | Sign in, a list page, one write, sign out, end to end | S5b, and again in S7 N6 and N7 |

**R3 — change-area map.** When files under a path change, look hardest at these checks; R1 still runs in full every time.

| Path | Emphasise |
|---|---|
| `config/database.php`, `app/Database/**`, `app/Providers/**`, `bootstrap/**`, `.env.example`, `phpunit.xml`, `tests/TestCase.php` | `DatabaseConnectionTest`, **all of S7**, CR-13, CR-14, S1d |
| `database/migrations/**`, `database/seeders/**` | S3a–S3d, **S7** N1, N3, N4, CR-12. A new migration also needs `database/sql/` regenerated (`database/sql/regenerate.sh`) or S3d fails |
| `database/sql/**` | S3d |
| `app/Models/Complaint.php`, `resources/views/livewire/complaints/**` | `ComplaintFlowTest`, `ComplaintNumberTest`, `ComplaintEvidenceTest`, `AnonymizationTest`, S5b, **S7** N8, CR-01–06, CR-11 |
| `app/Policies/**`, `app/Services/IdentityResolutionService.php`, `resources/views/livewire/{appeals,retaliation,dashboards}/**` | `RbacBoundaryTest`, `AnonymizationTest`, `AppealsTest`, `RetaliationReportTest`, `NewDashboardsTest`, CR-03, CR-04, CR-08 |
| `resources/views/livewire/pages/auth/**`, `app/Livewire/**`, `routes/auth.php` | `tests/Feature/Auth/*`, `TwoFactorAuthTest`, `DatabaseCompatibilityTest`, S5b, CR-07 |
| `routes/web.php`, `bootstrap/app.php`, `resources/views/livewire/admin/**` | `AdminPanelTest`, the S5a probes, S1d, CR-09 |
| `resources/views/livewire/{schools,onboarding,invitations}/**` | `DatabaseCompatibilityTest`, `RegistrationFlowTest`, `ParentChildAndInvitationTest`, CR-10 |
| `app/Http/Controllers/**`, `config/filesystems.php` | `ComplaintEvidenceTest`, S5a, CR-06 |
| `app/Services/AIAssistService.php`, `resources/views/livewire/{feedback,teacher-feedback}/**` | `AIAssistServiceTest`, `SchoolFeedbackTest`, CR-16 |
| `app/Services/TeacherEffectivenessIndexService.php`, `app/Services/SchoolQualityIndexService.php`, `app/Console/**` | `TeacherEffectivenessTest`, `NewDashboardsTest`, S3c (`analytics:recalculate`), CR-17 |
| any `resources/views/**`, `resources/css/**`, `resources/js/**`, `tailwind.config.js`, `vite.config.js`, `package*.json` | **S4a**: Tailwind only ships classes it finds in the views, so a view change can make the committed build stale. CR-15 |
| `composer.json`, `composer.lock` | S1a, all of R1, S6e |
| `.github/workflows/**` | S1f, and read the step order: the PostgreSQL check must come before anything else that touches `edu_test` |
| `tests/smoke/**` | S5b, S7 N6 |

**R4 — baseline comparison.** Compare each level's counts with §5. A total that goes down, a new skip, or a skip outside the 3 expected S2 skips is a failure even if nothing is red. New tests raise the baseline; say so in the report.

## 5. Baseline

Measured on 2026-10-24 on branch `claude/fervent-bell-wcmmdu` (base `cc17e7b` plus this run's uncommitted changes), Linux, PHP 8.3.6, Composer 2.8.12, Node 22.22.0 / npm 10.9.4, PostgreSQL 16.15, PgBouncer 1.22.0, Laravel 13.26.1, PHPUnit 12.5.33.

Final run (the counts to compare against):

| Level | Tests / checks | Passed | Failed | Skipped | Duration |
|---|---|---|---|---|---|
| S1a–S1d, S1f | 5 checks | 5 | 0 | 0 | 7 s together |
| S1e Pint (report-only) | 12 files listed, all older than this spec: `bootstrap/app.php`, `bootstrap/providers.php`, `database/seeders/TeacherFeedbackSeeder.php`, and in `tests/Feature/Platform/`: `AIAssistServiceTest`, `AcademicRecordsTest`, `AdminPanelTest`, `AnonymizationTest`, `ComplaintFlowTest`, `RegistrationFlowTest`, `RetaliationReportTest`, `TeacherEffectivenessTest`, `TwoFactorAuthTest` | — | — | — | 1 s |
| S2 `php artisan test` (SQLite) | 130 tests, 331 assertions | 127 | 0 | 3 (expected: the PostgreSQL-only email tests) | 10 s |
| S3a suite on PostgreSQL | 130 tests, 337 assertions | 130 | 0 | 0 | 18 s |
| S3b–S3d | 3 checks (`50`/`citext`; `3\|20\|284\|100\|4`; `Nothing to migrate`) | 3 | 0 | 0 | 9 s |
| S4a / S4b | build current / rebuilt build listed for commit | 1 | 0 | 0 | 10 s |
| S5a probes / S5b browser | 17 probes / 5 steps | 17 / 5 | 0 | 0 | 12 s / 6 s |
| S6a–S6d | 4 checks | 4 | 0 | 0 | 1 s |
| S6e audits (report-only) | Composer: 6 advisories (3 `laravel/framework` < 13.30, 2 `league/commonmark`, 1 `symfony/http-foundation`); npm `--omit=dev`: 0 (7 in build-only dev dependencies) | — | — | — | 10 s |
| S7 N1–N9 | 9 checks; N2 = 130 tests through the pooler, 130 passed | 9 | 0 | 0 | 50 s |

First run, before this run's fixes (commit `cc17e7b`, nothing uncommitted): S2 116 tests, 113 passed, 3 skipped (11 s); S3a 116 tests, 116 passed (18 s). The 14 new tests come from this run: 1 in `TwoFactorAuthTest`, and the new `ComplaintNumberTest` (3), `ComplaintEvidenceTest` (3) and `DatabaseConnectionTest` (7). Older counts in the docs (111 in README, 116 in PR #5) were right at the time; neither is the suite as it stands.

## 6. Pass criteria

- **PASS:** every level ran, or S5b/S7 were skipped under their stated rules. Every level is green, the counts are at or above the baseline, and no defect above S4 is open. A report-only level (S1e, S4b, S6e) may list findings, but S1e may not list a file outside the baseline 12.
- **FAIL:** a check is red after the fix loop, a count dropped, a new skip appeared, or a defect of S1–S3 is still open.
- **BLOCKED:** the suite could not run. The scratch PostgreSQL is missing, a tool is missing, a database value names a non-local host or a real (non-scratch) database, or S7 could not run although the change touched an S7 path (§4 R3).

## 7. Defect handling

| Severity | Meaning here | Example from this project |
|---|---|---|
| S1 | data loss, a submitter's identity or private data exposed, an auth bypass, tests reaching a hosted database, or the app does not start | `complaints.anonymous_ref` resolvable by a school admin; a test run pointed at Neon |
| S2 | a feature broken, or a security control weakened without direct exposure | every evidence download returned 500 (`ComplaintEvidenceController` called a removed `authorize()`); the 2FA challenge allowed unlimited code guesses |
| S3 | degraded behaviour with a workaround | complaint numbers colliding under concurrent submissions; a stale committed CSS build |
| S4 | cosmetic, or docs only | `TESTING.md` claiming Pest or a wrong test count |

The agent may fix on its own: product code, tests that are provably wrong (with the evidence), the committed `public/build` (by rebuilding), docs, `.env.example`, `phpunit.xml` and CI. Each product fix needs the smallest root-cause change, plus a test that fails without it when the suite did not already catch it. It must report instead of fixing: anything needing credentials, a change to the live server or Neon, a schema change that would need data migration on the live database, and fixes that belong in `bkesari-platform/edu` (report them for cross-porting).

Never: skip, disable, delete or loosen a test, or change an expected value to match wrong output; point anything at a non-local database or a Neon URL; run `migrate:fresh` or `db:wipe` anywhere but the scratch database; edit `.env` files beyond creating the local one from `.env.example`; commit, push, deploy or touch a server; edit `.claude/settings.json`, `.claude/hooks/`, `.claude/test-gate.json`, `.gitignore`, `CLAUDE.md` or `AGENTS.md`.

## 8. Report format

Write to `.claude/test-runs/<UTC yyyymmdd-hhmmss>-edu-standalone-tester.md` and copy to `.claude/test-runs/latest-edu-standalone-tester.md`:

```markdown
# edu-standalone-tester report

- Date (UTC): <yyyy-mm-dd hh:mm>
- Commit: <short sha> on <branch>, plus uncommitted changes: <yes/no>
- Changed files (vs origin/staging, plus uncommitted): <list, each mapped to its R3 row>
- Environment: <PHP, PostgreSQL, PgBouncer, Node versions; scratch database name>

## Results

| Level | Command | Result | Tests / passed / failed / skipped | Duration | vs baseline |
|---|---|---|---|---|---|
| S1a … S7 N9 | <exact command> | PASS / FAIL / SKIPPED (<reason>) | <counts or —> | <s> | <same / +n / -n> |

## Critical regressions

| ID | Result | Evidence |
|---|---|---|
| EDU-CR-01 … EDU-CR-19 | PASS / FAIL | <test names or probe output> |

## Defects

| ID | Severity | Area | Symptom | Root cause | Fix | Files | Test added | Status |
|---|---|---|---|---|---|---|---|---|

## Re-test

<which failing tests were re-run first, then the full standard + regression results after the last fix>

## Open items

<anything still failing, skipped, report-only findings (S1e, S4b, S6e), and items for bkesari-platform/edu>

TESTER: edu-standalone-tester
VERDICT: PASS | FAIL | BLOCKED
```

## 9. Known gaps

- **Neon itself is never contacted.** S7 uses a local stand-in. Neon's pooler offers SCRAM channel binding and PgBouncer does not, so `channel_binding=require` on the pooled endpoint is only proven to reach libpq (N5), not to connect. Libpq on Hostinger (version 14+ is needed for Neon's SNI routing), outbound port 5432 and `pdo_pgsql` on the web PHP are deploy-time checks (`DEPLOYMENT.md`), not tested here.
- **Scale to zero** is approximated by terminating the server connections (N7). The real wake-up delay is not exercised. pdo_pgsql's 30 s connect timeout is relied on, not measured.
- **Browser coverage** is one flow (S5b: the parent) in headless Chromium. There is no cross-browser or mobile-viewport testing, and no JavaScript unit tests (`resources/js` is a stub).
- **No load or performance tests**: 20 seeded schools, not the 100k+ the spec envisions. N8 is a small contention check, not a load test.
- **Mail** is never delivered (`array`/`log` mailers). The `analytics:recalculate` cron is not tested on a host.
- **Pint is not enforced.** S1e is report-only because 12 older files fail it.
- **`database/sql/regenerate.sh`** is not run here (it needs a matching `pg_dump`). S3d checks the committed files, not their regeneration.
- Security beyond the RBAC/anonymization tests and the S5/S6 probes (penetration testing, CSRF/XSS fuzzing) is out of scope.
