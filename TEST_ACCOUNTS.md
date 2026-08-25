# Test Accounts (synthetic data only)

Every account below uses the same demo password: **`Password123!`**

This is a test environment. All accounts and data are fabricated — do not reuse this password anywhere real, and do not put real people's information into these accounts.

| Role | Email | Notes |
|---|---|---|
| System Admin | `admin@test.agtci.com` | Full permissions |
| National Admin | `national.admin@test.agtci.com` | Placeholder dashboard only (see ROADMAP.md) |
| Researcher | `researcher@test.agtci.com` | Placeholder dashboard only |
| State Officer (Maharashtra) | `state.mh@test.agtci.com` | Placeholder dashboard only |
| State Officer (Karnataka) | `state.ka@test.agtci.com` | Placeholder dashboard only |
| State Officer (Delhi) | `state.dl@test.agtci.com` | Placeholder dashboard only |
| District Officer (Pune) | `district.pun@test.agtci.com` | **Full working dashboard** |
| District Officer (Mumbai) | `district.mum@test.agtci.com` | Full working dashboard |
| District Officer (Bengaluru Urban) | `district.blr@test.agtci.com` | Full working dashboard |
| District Officer (Mysuru) | `district.mys@test.agtci.com` | Full working dashboard |
| District Officer (New Delhi) | `district.ndl@test.agtci.com` | Full working dashboard |
| School Admin (demo school) | `school.admin@test.agtci.com` | **Full working dashboard**, linked to the first seeded school |
| Parent (demo) | `parent@test.agtci.com` | **Full working dashboard**, verified at the same demo school, has an existing complaint |
| Student (demo) | `student@test.agtci.com` | Placeholder dashboard (student dashboard is deferred, see ROADMAP.md) |
| Teacher (demo) | `teacher@test.agtci.com` | Placeholder dashboard |

Plus ~275 additional synthetic parent/student/teacher/school-admin accounts from the seeder — all share the same demo password, all have randomly generated `@example.com`-style emails from Faker. Query the database directly if you need to find one for a specific school/district.

## Regenerating this data

```bash
php artisan migrate:fresh --seed
```

Never run this against the production database without a fresh backup first — see `DEPLOYMENT.md`.
