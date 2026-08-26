# Roadmap — what's deferred, and why

The original spec for this platform is a multi-month, national-scale system (50+ tables, national gov analytics, AI moderation, teacher value-add scoring, anti-manipulation ML, a full 12-document security/privacy test regime). Phase 1 was a **fully working, fully tested core vertical slice**. Phase 2 (this update) adds the remaining dashboards, an admin panel, retaliation reporting, the Teacher Effectiveness Index, and a first pass at AI-assisted features. This file lists what's still deliberately left out, so it's never mistaken for "built but broken."

## Deferred entirely (not built, not stubbed)

- **Anti-manipulation detection at the account level** — suspicious-account flagging, coordinated-account detection across users. A basic *feedback-timing-spike* heuristic exists (`AIAssistService::detectFeedbackSpike`) but isn't wired into any UI yet — see "Partially built" below.
- **National/state analytics rollups as real infrastructure** — State/National/Researcher dashboards compute their numbers with live queries at request time (see below), not pre-aggregated tables, cached summaries, or scheduled recalculation jobs. Fine at current data volume (20 schools); would need real infrastructure at spec's target scale (100k+ schools).
- **Formal appeals workflow** — beyond the resolution confirmation's escalate-on-"no" path.
- **2FA** for officer/admin accounts.
- **Government identity-access "reason required" hard gate** — the reason field exists and is logged (`identity_access_logs`), but the UI doesn't yet force it before proceeding.
- **Real AI provider integration** — `AIAssistService` (category suggestion, duplicate detection, summarization, feedback-spike detection) is entirely rule-based/heuristic right now; no AI API key is configured for this environment. The service is the intended integration seam — swapping in a real model call means editing that one class, not call sites. See `SETUP_REQUIRED.md`-style note in `DEPLOYMENT.md`.
- **Translation / sentiment-theme analysis** — part of spec's AI-assisted features list, not built even as a heuristic.
- **Value-add / classroom-observation / professional-development components of the Teacher Effectiveness Index** — spec sections AC/AD describe measuring student learning improvement and observed teaching practice; this build's TEI is feedback-only (parent/student ratings across 12 dimensions), documented as such in the service's own doc comment.

## Partially built

- **Student/Teacher/State Officer/National Admin/Researcher/System Admin dashboards** — now real and working (not placeholders), but intentionally lean:
  - Student dashboard reuses the Parent dashboard's visual language rather than a dedicated age-appropriate design pass (simplified language, safety-resource links) — still on the list if this becomes a priority.
  - State/National/Researcher dashboards are live-query rollups (see above) — correct today, not how it would scale to spec's target school count.
- **Admin panel** — covers rating weights (school + teacher) and complaint categories, plus an audit-log/identity-access-log viewer. Does **not** yet cover role/permission management, notification templates, or moderation-rule configuration (all still DB/seeder-only).
- **Parent-child linkage** — a parent's self-service onboarding (`/onboarding`) links them to a school but doesn't yet capture *which* specific student/child they're linked to (`parent_school_relationships.student_user_id` stays null on this path — it's only populated by the seeder). A parent can currently only have one pending/verified link per school, not one per child.
- **School registration additional-staff invites** — creates the account and shows the temporary password on-screen (since `MAIL_MAILER=log`, no real email goes out in this environment). A production deployment would need real mail delivery here instead of an on-screen credential dump.
- **AI-assisted suggestions** — category suggestion and possible-duplicate detection are wired into the complaint form as advisory hints (never auto-applied, per spec section AF). Not wired into: school registration, retaliation reports, or teacher feedback forms yet.

## Full doc set

Spec asked for 12 docs; this build ships 8 (`README`, `SETUP`, `DEPLOYMENT`, `DATABASE`, `SECURITY_PRIVACY`, `TESTING`, `TEST_ACCOUNTS`, `ROADMAP`). `API.md`, `ADMIN_GUIDE.md`, `USER_GUIDE.md`, `TROUBLESHOOTING.md`, `ENVIRONMENT.md` still aren't written — there's no public API, and the admin panel is now real but small enough that a dedicated guide would mostly restate this file.

## Suggested next phase order

1. Parent-child linkage (multiple children per parent per school) — closes a real usability gap in onboarding.
2. Hard identity-access reason gate — closes the biggest remaining privacy/safety gap.
3. Real aggregation infrastructure for State/National/Researcher dashboards (replace live queries with scheduled rollups) — needed before this scales past a few hundred schools.
4. Value-add component of TEI (requires academic performance data this build doesn't have).
5. Account-level anti-manipulation detection (suspicious/coordinated accounts) — should follow, not precede, having enough real usage data to design against.
6. Real AI provider integration behind `AIAssistService`, once an API key is available.
