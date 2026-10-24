// Browser smoke run (TEST_SPEC.md, level S5): sign in as a seeded demo
// account, open a list page, submit one complaint, see it listed, sign out.
// Run against an app started with `php artisan serve` on a freshly seeded
// throwaway database. Needs Playwright (not a dependency of this project):
//
//   BASE_URL=http://127.0.0.1:8102 SMOKE_SCHOOL_ID=<id> node tests/smoke/browser-smoke.cjs
//
// SMOKE_SCHOOL_ID is a school the account is verified at (TEST_SPEC.md gives
// the query). Prints one JSON line; exits non-zero on any failure.
const { chromium } = require('playwright');

const base = (process.env.BASE_URL || 'http://127.0.0.1:8102').replace(/\/$/, '');
const email = process.env.SMOKE_EMAIL || 'parent@test.agtci.com';
const password = process.env.SMOKE_PASSWORD || 'Password123!';
const schoolId = process.env.SMOKE_SCHOOL_ID;
const subject = `Smoke test ${new Date().toISOString()}`;

(async () => {
    if (!schoolId) throw new Error('SMOKE_SCHOOL_ID is required');

    const browser = await chromium.launch();
    const page = await browser.newPage();
    const steps = [];

    try {
        await page.goto(`${base}/login`);
        await page.fill('#email', email);
        await page.fill('#password', password);
        await page.click('button[type=submit]');
        await page.waitForURL(/\/dashboard\/parent$/, { timeout: 20000 });
        steps.push('signed in');

        // List page: the school directory.
        await page.goto(`${base}/schools`);
        const schools = await page.locator('a[href*="/schools/"]').count();
        if (schools < 1) throw new Error('the school list is empty');
        steps.push(`school list shows ${schools} link(s)`);

        // One write: submit a complaint.
        await page.goto(`${base}/complaints/create`);
        await page.selectOption('#schoolId', String(schoolId));
        const category = await page.locator('#complaintCategoryId option:not([value=""])').first().getAttribute('value');
        await page.selectOption('#complaintCategoryId', category);
        await page.fill('#subject', subject);
        await page.fill('#description', 'Smoke run: the ground-floor drinking water tap has been broken for a week.');
        await page.press('#description', 'Tab');
        await page.click('form button[type=submit]');
        await page.waitForURL(/\/complaints\/\d+$/, { timeout: 20000 });
        const heading = await page.locator('h2').first().innerText();
        const number = (heading.match(/EDU-[A-Z0-9-]+/) || [])[0];
        if (!number) throw new Error(`no complaint number on the page: ${heading}`);
        steps.push(`submitted ${number}`);

        // Read it back: the parent's dashboard lists the new complaint.
        await page.goto(`${base}/dashboard/parent`);
        await page.getByText(subject).first().waitFor({ timeout: 10000 });
        steps.push('dashboard lists it');

        // Sign out through the user menu.
        await page.locator('nav button:has(div[x-text="name"])').first().click();
        await page.getByText('Log Out').first().click();
        await page.waitForURL((url) => !url.pathname.startsWith('/dashboard'), { timeout: 20000 });
        await page.goto(`${base}/dashboard`);
        if (!page.url().endsWith('/login')) throw new Error(`still signed in after logout: ${page.url()}`);
        steps.push('signed out');

        console.log(JSON.stringify({ result: 'passed', subject, number, steps }));
    } catch (error) {
        console.log(JSON.stringify({ result: 'failed', subject, steps, error: String(error && error.message || error) }));
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})().catch((error) => {
    console.log(JSON.stringify({ result: 'failed', error: String(error && error.message || error) }));
    process.exitCode = 1;
});
