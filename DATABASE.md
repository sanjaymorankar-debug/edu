# Database

MySQL in production (Hostinger), SQLite for local dev. 40 tables (see `database/migrations/`).

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

## Migrating on the live server

```bash
php artisan migrate --force
```

`--force` is required in production since `APP_ENV=production` blocks interactive migration prompts. **Never run `migrate:fresh` against the live database** — it drops every table.
