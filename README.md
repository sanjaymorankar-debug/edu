> ## ⚠️ New work has moved to `bkesari-platform/edu`
>
> This repository still serves the existing site at **`edutest.agtci.com`** and is
> fine to keep running. But it is **behind**, and new development happens in the
> `edu/` folder of
> **[`sanjaymorankar-debug/bkesari-platform`](https://github.com/sanjaymorankar-debug/bkesari-platform)**,
> which is what `devedu`/`testedu`/`edu.bkesari.com` deploy from (`DEPLOY.md` part 2).
>
> Measured at the time this notice was added:
>
> | | this repo | `bkesari-platform/edu` |
> |---|---|---|
> | Files (excl. `vendor/`) | 265 | 391 |
> | Spec roles with dashboards | 10 | 12 |
> | Files unique to this repo | **1** — a stale compiled `public/build` CSS asset | — |
> | Shared files that differ | 25 — the platform copy is larger in every one sampled | — |
>
> The platform copy adds fee transparency, the claimed-vs-experienced facilities
> model, `STUDENT_GROWTH_FRAMEWORK.md` and around 127 files of models and views
> that do not exist here. So: **fix things here only if they affect
> `edutest.agtci.com`; put everything else in `bkesari-platform`**, or the two
> will keep drifting.

# Education Accountability Platform

A national school-quality, complaint, and accountability platform: parents and students can search schools, submit **faceless (anonymized) complaints**, rate schools, and confirm whether issues were actually resolved — while schools and government officers work the case without ever seeing the submitter's real identity.

**Live (test):** https://edutest.agtci.com
**Stack:** Laravel 13, Livewire/Volt, Tailwind, PostgreSQL 16 (all environments; SQLite only for the zero-setup test run)

This is a large spec built incrementally — see [`ROADMAP.md`](ROADMAP.md) for exactly what's built vs. deliberately deferred, and don't take this repo as feature-complete against the original brief.

## What's actually here

- Auth + RBAC for all 10 spec roles, each with a real working dashboard (Parent, Student, Teacher, School Admin, District/State Officer, National Admin, Researcher, System Admin)
- Public school registration (`/schools/register`) — the registrant becomes School Admin immediately; the school itself stays `pending` until a District/State Officer verifies it
- Self-service parent/student/teacher onboarding (`/onboarding`) — link your account to a school; the link stays `pending` until the School Admin approves it
- School Admin dashboard includes a "Pending Verifications" queue; District Officer dashboard includes a "Pending School Registrations" queue
- Public school search, school profile pages, per-teacher rating
- Faceless complaint system with real identity anonymization (see [`SECURITY_PRIVACY.md`](SECURITY_PRIVACY.md))
- Resolution-confirmation workflow ("was your issue *actually* resolved?")
- Retaliation reporting — a separate, prioritized-review workflow for parents/students who face retaliation after a complaint
- School Quality Index and Teacher Effectiveness Index, both with admin-configurable weights (Teacher scores are private to the teacher — never public, never shown to the school); TEI blends in an approximate value-add component from admin-entered `student_academic_records`
- Formal appeals workflow — one appeal per resolved/escalated complaint, reviewed by a State Officer one level above whoever handled the original complaint
- Two-factor authentication (TOTP) for School Admin and officer/admin roles, with a real login challenge and single-use recovery codes
- Real notifications system — relationship approvals, complaint status changes, invitation acceptance, and appeal decisions all notify the relevant user, with an unread-count bell in the nav
- Admin panel: rating weights, complaint categories, audit log + identity-access log viewer, fraud-flag review queue, role/permission management, moderation-threshold configuration
- Anti-manipulation flagging — a coordinated-feedback-burst heuristic wired into a real review queue, admin-configurable thresholds
- Analytics-snapshot infrastructure for the National/Researcher dashboards and the State Officer dashboard's summary numbers — scheduled recalculation instead of a live query on every page load
- Advisory AI-assist (rule-based, no model configured yet): category suggestion and possible-duplicate detection on the complaint form, possible-duplicate detection on the retaliation and school-registration forms — never auto-applied, always overridable
- A hard reason-required gate before any officer can reverse an anonymized identity, with mandatory audit logging
- Full audit logging + identity-access logging

## Quick start (local dev)

See [`SETUP.md`](SETUP.md).

## Tests

```bash
php artisan test        # 130 tests on in-memory SQLite, no setup (3 PostgreSQL-only ones skip)
```

That needs no database server, so it works on a fresh clone. It is not the
engine this runs on: production is PostgreSQL (on Neon), and CI runs the same
suite against a PostgreSQL 16 service, where all 130 run. To do that locally,
export the `DB_*` variables for a throwaway database:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=edu_test \
DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test
```

Export them; don't put them in `.env.testing`. `phpunit.xml` sets
`DB_CONNECTION` and `DB_DATABASE` before Laravel reads any file, so only real
environment variables override them. The suite drops every table in that
database, and refuses to start against anything but a local server.
[`TESTING.md`](TESTING.md) has the details and
[`TEST_SPEC.md`](TEST_SPEC.md) the full test specification.

## Deploying

See [`DEPLOYMENT.md`](DEPLOYMENT.md).

## Docs index

- [`SETUP.md`](SETUP.md) — local development setup
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — deployment steps (PHP app on Hostinger, database on Neon)
- [`DATABASE.md`](DATABASE.md) — schema overview and PostgreSQL notes
- [`SECURITY_PRIVACY.md`](SECURITY_PRIVACY.md) — identity-separation design, what's hardened vs. not yet
- [`TESTING.md`](TESTING.md) — how to run the test suite, what's covered
- [`TEST_SPEC.md`](TEST_SPEC.md) — the test specification the testing agent follows
- [`TEST_ACCOUNTS.md`](TEST_ACCOUNTS.md) — demo login credentials (synthetic data only)
- [`ROADMAP.md`](ROADMAP.md) — what's deferred to later phases, and why
