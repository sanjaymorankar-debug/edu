# Database

MySQL in production (Hostinger), SQLite for local dev. 51 tables (see `database/migrations/`).

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

**Fees & true annual cost (spec §9):** `fees` — one row per school/year/class/charge, with category, amount, frequency, mandatory and refundable flags, and a `state_cap_status` (fee regulation is a state subject, so there is no single national rule to check against). History is inherent: a year's fees are that year's rows and are never overwritten. `fee_revisions` covers edits *within* a year — both the before and after amount, a full JSON snapshot of the prior state, and a required reason. `AnnualCostCalculator` turns these into first-year and continuing-year totals, keeping one-time charges and optional charges separate rather than merging everything into one number.

**Facilities, claims & experience (spec §8, §11, §12):** `facility_claims` — what a school says it offers, versioned by academic year, keyed against the canonical taxonomy in `App\Support\FacilityTaxonomy` (a code constant, deliberately not a table: claims, ratings and history must share one list or claimed-vs-experienced cannot be computed). `verification_status` is about evidence, not truth, and is not mass-assignable — `markVerified()` is the only way to set it, so a school cannot self-verify. `facility_ratings` — the experience side, storing `anonymous_ref` and never a `user_id` (same identity separation as complaints, spec §26), with structured per-dimension scores rather than one star rating, and one rating per person per facility per year. `ClaimedVsExperiencedService` compares the two into Consistent / Partially consistent / Significant discrepancy reported, showing nothing at all below 3 reports.

**Consent (DPDP Act 2023 Section 9):** `consent_records` — one row per child per purpose (`capability_growth`, `career_pathway`, `life_skills`, `physical_health`, `mental_wellbeing`, `alumni_outcomes`), storing the notice text actually shown, who granted it, and how they were verified as a guardian. Withdrawal sets `status`/`withdrawn_at` rather than deleting, so "was consent in force when this was collected?" stays answerable. Every read and write in the growth/career/life-skills modules goes through `ConsentService`.

**Student growth (spec §15–16):** `capability_observations` — dated, single-observer notes across five NEP 2020 domains, tagged `strength` or `growth_area`, from a teacher/parent/self/peer. **There is deliberately no score, rating or level column and there must never be one** — see [`STUDENT_GROWTH_FRAMEWORK.md`](STUDENT_GROWTH_FRAMEWORK.md). Peer rows land as `moderation_status = 'pending'` and stay invisible until a teacher clears them. `growth_plans` (one per child/school/term, with `shared_with_parent_at` gating guardian visibility) and `growth_goals` (max 3 per plan; `support_at_school` and `support_at_home` are NOT NULL, so no goal exists without a next step).

**Career & life skills (spec §17):** `career_interest_profiles` — a time series of child-stated interests, appended never updated, since interests are meant to change; no assigned-pathway column exists, suggestions are computed at read time by `CareerPathwayService`. `life_skills_tracking` — participation in structured activities (`participated`/`engaged`/`led`), explicitly not a score.

**Notifications:** `notifications` — Laravel's standard database-notification table (uuid id, polymorphic `notifiable`, json `data`, `read_at`). Written to by `App\Notifications\*` classes on relationship-approval, complaint-status-change, invitation-acceptance, and appeal-decision events.

## Indexes

Search-relevant columns on `schools` (name, pincode, board, state/district), `complaints` (status, school_id+status, district_id+status, anonymous_ref), and `school_quality_scores` (school_id+calculated_at) are indexed. Nothing here is tuned for the 100k+-school scale the full spec envisions — see `ROADMAP.md` for aggregation-table/caching work that's deferred.

## Migrating on the live server

```bash
php artisan migrate --force
```

`--force` is required in production since `APP_ENV=production` blocks interactive migration prompts. **Never run `migrate:fresh` against the live database** — it drops every table.

### Applying schema changes by hand instead

If you prefer running SQL in phpMyAdmin over `artisan migrate`, `database/sql/` holds ready-to-run
scripts. They were generated from the migrations using Laravel's own MySQL schema grammar, so they
match what `artisan migrate` would produce, and each one also records itself in the `migrations`
table so a later `artisan migrate` doesn't try to recreate the same tables. Read the header comment
in the script before running it — back up first.
