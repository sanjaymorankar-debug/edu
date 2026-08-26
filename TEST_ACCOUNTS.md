# Test Accounts (synthetic data only)

Every account below uses the same demo password: **`Password123!`**

This is a test environment. All accounts and data are fabricated — do not reuse this password anywhere real, and do not put real people's information into these accounts.

| Role | Email | Notes |
|---|---|---|
| System Admin | `admin@test.agtci.com` | Full working dashboard + admin panel (rating weights, categories, audit log) |
| National Admin | `national.admin@test.agtci.com` | Full working dashboard (national rollups, audit log link) |
| Researcher | `researcher@test.agtci.com` | Full working dashboard (aggregate-only analytics, no complaint detail) |
| State Officer (Maharashtra) | `state.mh@test.agtci.com` | Full working dashboard |
| State Officer (Karnataka) | `state.ka@test.agtci.com` | Full working dashboard |
| State Officer (Delhi) | `state.dl@test.agtci.com` | Full working dashboard |
| District Officer (Pune) | `district.pun@test.agtci.com` | Full working dashboard |
| District Officer (Mumbai) | `district.mum@test.agtci.com` | Full working dashboard |
| District Officer (Bengaluru Urban) | `district.blr@test.agtci.com` | Full working dashboard |
| District Officer (Mysuru) | `district.mys@test.agtci.com` | Full working dashboard |
| District Officer (New Delhi) | `district.ndl@test.agtci.com` | Full working dashboard |
| School Admin (demo school) | `school.admin@test.agtci.com` | Full working dashboard, linked to the first seeded school |
| Parent (demo) | `parent@test.agtci.com` | Full working dashboard, verified at the same demo school, has an existing complaint |
| Student (demo) | `student@test.agtci.com` | Full working dashboard |
| Teacher (demo) | `teacher@test.agtci.com` | Full working dashboard with a seeded Teacher Effectiveness Index score |

Every role now has a real dashboard — nothing left on the placeholder screen. See `ROADMAP.md` for what's still simplified within each.

None of the seeded accounts have 2FA enabled by default — enable it yourself from Profile → "Two-Factor Authentication" → Manage (available to School Admin and the four officer/admin roles) to test that flow. Use `admin@test.agtci.com` for `/admin/fraud-flags`, `/admin/roles`, and `/admin/moderation`.

Plus ~275 additional synthetic parent/student/teacher/school-admin accounts from the seeder — all share the same demo password, all have randomly generated `@example.com`-style emails from Faker. Query the database directly if you need to find one for a specific school/district.

## Regenerating this data

```bash
php artisan migrate:fresh --seed
```

Never run this against the production database without a fresh backup first — see `DEPLOYMENT.md`.
