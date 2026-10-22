import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import fs from 'node:fs';

function fixtures(): {
    password: string;
    invitationToken: string;
    resetToken: string;
} {
    return JSON.parse(fs.readFileSync(process.env.P01_E2E_FIXTURES!, 'utf8'));
}

async function signIn(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL('/account');
}

test('accepts an invitation, signs in, updates a profile, and signs out', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`/invitations/${fixtures().invitationToken}`);
    await expect(
        page.getByRole('heading', { name: 'Accept your invitation' }),
    ).toBeVisible();
    await page.getByLabel('Name', { exact: true }).fill('Invited Browser User');
    await page
        .getByLabel('Password', { exact: true })
        .fill(fixtures().password);
    await page
        .getByLabel('Confirm password', { exact: true })
        .fill(fixtures().password);
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page).toHaveURL('/login');
    await signIn(page, 'invited@example.test', fixtures().password);
    await expect(
        page.getByRole('link', { name: 'Accounts', exact: true }),
    ).toHaveCount(0);
    await page.getByLabel('Name', { exact: true }).fill('Updated Browser User');
    await page.getByRole('button', { name: 'Save profile' }).click();
    await expect(page.getByRole('status')).toContainText(
        'Your profile has been updated',
    );
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page).toHaveURL('/login');
    expect(errors).toEqual([]);
});

test('resets a password using a single-use link and signs in with the new password', async ({
    page,
}) => {
    const newPassword = 'ChangedBrowserPassword123';
    await page.goto(
        `/reset-password/${fixtures().resetToken}?email=reset%40example.test`,
    );
    await page.getByLabel('New password', { exact: true }).fill(newPassword);
    await page
        .getByLabel('Confirm new password', { exact: true })
        .fill(newPassword);
    await page
        .getByRole('button', { name: 'Reset password', exact: true })
        .click();
    await expect(page).toHaveURL('/login');
    await signIn(page, 'reset@example.test', newPassword);
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page).toHaveURL('/login');
});

test('suspension signs an existing member session out and blocks sign-in', async ({
    page,
    browser,
}) => {
    const memberContext = await browser.newContext();
    const memberPage = await memberContext.newPage();
    try {
        await signIn(memberPage, 'member@example.test', fixtures().password);
        await signIn(page, 'admin@example.test', fixtures().password);
        await page.getByRole('link', { name: 'Accounts', exact: true }).click();
        const row = page.getByRole('row').filter({
            has: page.getByText('member@example.test', { exact: true }),
        });
        await row.getByRole('button', { name: 'Suspend', exact: true }).click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await dialog.getByLabel('Current password').fill(fixtures().password);
        await dialog.getByRole('button', { name: 'Confirm change' }).click();
        await expect(dialog).not.toBeVisible();
        await expect(row).toContainText('Suspended');
        await memberPage.reload();
        await expect(memberPage).toHaveURL('/login');
        await memberPage
            .getByLabel('Email', { exact: true })
            .fill('member@example.test');
        await memberPage
            .getByLabel('Password', { exact: true })
            .fill(fixtures().password);
        await memberPage
            .getByRole('button', { name: 'Sign in', exact: true })
            .click();
        await expect(memberPage.getByRole('alert')).toContainText(
            'These credentials do not match our records',
        );
    } finally {
        await memberContext.close();
    }
});

test('rejects state changes without a CSRF token in the HTTP server', async ({
    request,
}) => {
    const response = await request.post('/login', {
        form: { email: 'admin@example.test', password: fixtures().password },
    });
    expect(response.status()).toBe(419);
});
