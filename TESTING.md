# Testing

```bash
php artisan test
```

41 tests, all passing as of this build (Pest/PHPUnit via Laravel's test runner).

## What's covered

- **Auth** (`tests/Feature/Auth/*`, from Breeze): registration (including the added role picker), login, logout, password reset/confirm, email verification
- **Complaint lifecycle** (`tests/Feature/Platform/ComplaintFlowTest.php`): verified-parent submission → complaint ID generated → unverified users blocked → full submit → school response → resolution proposed → parent confirms "yes" → resolved; confirming "no" escalates
- **RBAC boundaries** (`tests/Feature/Platform/RbacBoundaryTest.php`): school admin can't view another school's complaint; district officer can't view complaints outside their jurisdiction (and can within it); an unrelated parent can't view someone else's complaint; a school_admin role with no actual school_staff row is blocked
- **Anonymization** (`tests/Feature/Platform/AnonymizationTest.php`): `complaints`/`school_feedback` tables have no user-identifying columns at the schema level; a complaint's HTML never renders the submitter's real name/email to a school admin; `IdentityResolutionService::resolve()` requires the `access-protected-identity` permission and always writes an `identity_access_logs` row; the same user gets a stable `anonymous_ref` across multiple submissions to the same school

## Manual browser verification (done for this build)

Beyond the automated suite, the following was walked through live in a browser against the seeded local dataset:
- School search/filter and public school profile pages
- Full complaint cycle as an actual logged-in parent → school admin → parent again, confirming the UI (not just the underlying HTTP calls) shows the right thing at each step
- Confirmed the school admin's complaint view shows only the `ANON-...` reference, never the parent's real name
- District Officer dashboard, scoped correctly to their jurisdiction, with severity-first ordering and child-safety flags visible

## What's not tested

- Load/performance testing at scale (spec envisions 100k+ schools — this build has 20)
- Security penetration testing (SQLi/XSS/CSRF/IDOR beyond what Laravel's defaults + the RBAC tests above cover)
- Cross-browser/mobile-viewport testing beyond default desktop Chrome
- Anything in `ROADMAP.md`'s deferred list, since it isn't built
