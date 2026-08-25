# Roadmap — what's deferred, and why

The original spec for this platform is a multi-month, national-scale system (50+ tables, national gov analytics, AI moderation, teacher value-add scoring, anti-manipulation ML, a full 12-document security/privacy test regime). Phase 1 — this build — is a **fully working, fully tested core vertical slice**, not a stub of everything. This file lists what was deliberately left out, so it's never mistaken for "built but broken."

## Deferred entirely (not built, not stubbed)

- **Teacher Effectiveness Index / value-add scoring** — teacher feedback dimensions, aggregation, privacy-restricted display to the teacher only
- **Retaliation reporting module** — separate workflow for parents/students reporting retaliation after a complaint
- **AI-assisted features** — categorization, translation, sentiment/theme analysis, duplicate/anomaly detection. The spec is explicit that AI must never make final decisions; none of it is built yet, so there's nothing to constrain
- **Anti-manipulation detection** — duplicate/coordinated-review detection, suspicious-account flagging
- **National/state analytics rollups** — aggregation tables, cached summaries, scheduled recalculation jobs
- **Formal appeals workflow** — beyond the resolution confirmation's escalate-on-"no" path
- **2FA** for officer/admin accounts
- **Government identity-access "reason required" hard gate** — the reason field exists and is logged (`identity_access_logs`), but the UI doesn't yet force it before proceeding

## Partially built

- **Dashboards** — Parent, School Admin, and District Officer are fully working. Student, Teacher, State Officer, National Admin, Researcher, and System Admin roles authenticate and route correctly (RBAC is fully modeled for all 10 roles) but land on a placeholder screen instead of a full dashboard.
- **School Quality Index** — basic weighted average with a configurable-weights table and a simple response-volume confidence heuristic. No time-decay weighting, no protection against a single review moving the score disproportionately (spec section X), no historical trend charting yet (the `school_quality_scores` table stores snapshots for this, just not surfaced in UI).
- **Admin panel** — none yet. Rating weights, complaint categories, and roles/permissions are seeded and DB-editable but have no UI; changing them today means a database update or a new seeder run.
- **Parent-child linkage** — a parent's self-service onboarding (`/onboarding`) links them to a school but doesn't yet capture *which* specific student/child they're linked to (`parent_school_relationships.student_user_id` stays null on this path — it's only populated by the seeder). A parent can currently only have one pending/verified link per school, not one per child. Fixing this means adding a "select or invite your child's account" step to onboarding.
- **School registration additional-staff invites** — creates the account and shows the temporary password on-screen (since `MAIL_MAILER=log`, no real email goes out in this environment). A production deployment would need real mail delivery here instead of an on-screen credential dump.

## Full doc set

Spec asked for 12 docs; this build ships 8 (`README`, `SETUP`, `DEPLOYMENT`, `DATABASE`, `SECURITY_PRIVACY` — combined per spec's SECURITY+PRIVACY split, `TESTING`, `TEST_ACCOUNTS`, `ROADMAP`). `API.md`, `ADMIN_GUIDE.md`, `USER_GUIDE.md`, `TROUBLESHOOTING.md`, `ENVIRONMENT.md` are not written — there's no public API and no admin UI yet to document, and the others would mostly duplicate `SETUP.md`/`DEPLOYMENT.md` until there's more surface area to cover.

## Suggested next phase order

1. Full dashboards for remaining roles (Student safety/reporting UX needs care — spec explicitly calls for age-appropriate design)
2. Admin panel (rating weights, categories, roles) — unlocks a lot of the "configurable, not hard-coded" requirements at once
3. Retaliation reporting + hard identity-access reason gate (these two are the biggest remaining privacy/safety gaps)
4. Teacher Effectiveness Index
5. Analytics rollups + national/state dashboards
6. Anti-manipulation + AI-assisted moderation (should follow, not precede, having enough real usage data to design against)
