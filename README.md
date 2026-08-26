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
- School Quality Index and Teacher Effectiveness Index, both with admin-configurable weights (Teacher scores are private to the teacher — never public, never shown to the school)
- Admin panel: rating weights, complaint categories, audit log + identity-access log viewer
- Advisory AI-assist (rule-based, no model configured yet) on the complaint form: category suggestion and possible-duplicate detection — never auto-applied, always overridable
- Full audit logging + identity-access logging

## Quick start (local dev)

See [`SETUP.md`](SETUP.md).

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
