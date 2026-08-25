# Security & Privacy

## Identity separation (the core design constraint)

A school or officer must never see a submitter's real name/email/phone directly on a complaint or rating.

```
users (real identity)
   |
   | anonymous_identities: (user_id, school_id, context) -> anonymous_ref
   v
complaints / school_feedback  — store anonymous_ref only, NO user_id column at all
```

This isn't a view or a hidden column — `complaints.anonymous_ref` and `school_feedback.anonymous_ref` are the *only* identity-shaped columns on those tables (verified by `AnonymizationTest::test_complaints_table_has_no_user_identifying_column`). A school admin's query can never accidentally join to `users`.

**Reversal is a single, audited code path.** `App\Services\IdentityResolutionService::resolve()` is the only place `anonymous_ref` is ever turned back into a `User`. It:
1. Requires the `access-protected-identity` permission (district/state/national officers + system admin only)
2. Writes one row to `identity_access_logs` (officer, anonymous_ref, action, reason, timestamp) on every call, whether or not the lookup succeeds

What's **not yet built**: a hard UI gate forcing a reason before proceeding (currently the reason field is logged if supplied, but not required), and a dedicated admin screen for reviewing identity-access logs. See `ROADMAP.md`.

## RBAC

`spatie/laravel-permission`, 10 roles matching the spec (public/parent/student/teacher/school_admin/district_officer/state_officer/national_admin/researcher/system_admin). Authorization is enforced via Laravel Policies (`app/Policies/ComplaintPolicy.php`, `SchoolPolicy.php`), not just route middleware — e.g. a school_admin route-level check alone wouldn't stop them viewing another school's complaint; the policy checks their actual `school_staff` assignment.

Covered by `tests/Feature/Platform/RbacBoundaryTest.php`: cross-school access, cross-jurisdiction district-officer access, and complaints from users with no real staff/relationship record.

## Standard web security

- CSRF: Laravel default (all forms)
- Password hashing: bcrypt, `BCRYPT_ROUNDS=12` in production
- Rate limiting: Laravel's default auth throttling applies to login/register
- File uploads (complaint evidence): type-restricted (jpg/jpeg/png/pdf), 5MB cap, stored on the **private** `local` disk (`storage/app/private`, never `public/`), served only through `ComplaintEvidenceController` which re-checks the `view` policy on the parent complaint before streaming the file — no direct public URL exists for evidence files.
- `.env` is never committed; see `.env.example` for the required keys and `DEPLOYMENT.md` for how production secrets get onto the server.

## What's explicitly NOT hardened yet (Phase 1 test environment)

- No 2FA for officer/admin accounts
- No anti-manipulation/duplicate-review detection
- No AI-assisted moderation
- Serious child-safety allegations are flagged (`is_child_safety_flag`) and prioritized in the District Officer dashboard, but there's no dedicated escalation workflow beyond that flag yet

All of the above are listed in `ROADMAP.md`, not silently skipped. **Do not put real parent/student/complaint data into this test environment** until these are addressed — seed data is 100% synthetic and this should stay that way until a proper security review happens.
