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
**Stack:** Laravel 13, Livewire/Volt, Tailwind, MySQL (Hostinger) / SQLite (local dev)

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
php artisan test                          # 111 tests on in-memory SQLite, no setup
```

That is what `phpunit.xml` pins and what CI's `test` job runs. It needs no
database server, so it works on a fresh clone.

It is also not the engine this runs on. Production is **MariaDB 10.11**, and
SQLite agrees with it on nothing it is not forced to — these migrations declare
28 `enum` and 10 `json` columns, and on SQLite an `enum` is a `varchar` that
accepts any string, so a test asserting the database rejects bad data passes
there while proving nothing. To run the same 111 tests against real MySQL:

```bash
cp .env.testing.example .env.testing      # defaults match the local devstack
php artisan key:generate --env=testing
php artisan test -c phpunit.mysql.xml
```

Two things about that which are easy to get wrong, both found by being caught
by them:

- **The `-c` is not optional.** PHPUnit applies its `<env>` entries before
  Laravel boots, and Laravel's dotenv will not overwrite a variable that
  already exists, so `phpunit.xml`'s `DB_CONNECTION=sqlite` beats anything in
  `.env.testing`. Without `-c phpunit.mysql.xml` the suite stays on SQLite —
  green, fast, and testing the wrong thing. `phpunit.mysql.xml` is
  `phpunit.xml` with those keys left out so `.env.testing` can decide.
- **`.env.testing` replaces `.env`, it does not layer on top.** Laravel loads
  one or the other. A `.env.testing` holding only the `DB_*` lines unsets
  everything else: 88 of the 111 tests then fail on a missing `APP_KEY`. Hence
  the full copy and the `key:generate --env=testing`.

CI runs both engines, and the `db-mysql` job fails if `edu_test` comes back
empty — that being the only way it could go green while running on SQLite.

> `.env.testing` points at a **throwaway** database; the suite migrates it from
> scratch on every run.

## Deploying

See [`DEPLOYMENT.md`](DEPLOYMENT.md).

## Docs index

- [`SETUP.md`](SETUP.md) — local development setup
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — Hostinger deployment steps
- [`DATABASE.md`](DATABASE.md) — schema overview
- [`SECURITY_PRIVACY.md`](SECURITY_PRIVACY.md) — identity-separation design, what's hardened vs. not yet
- [`TESTING.md`](TESTING.md) — how to run the test suite, what's covered
- [`TEST_ACCOUNTS.md`](TEST_ACCOUNTS.md) — demo login credentials (synthetic data only)
- [`ROADMAP.md`](ROADMAP.md) — what's deferred to later phases, and why
