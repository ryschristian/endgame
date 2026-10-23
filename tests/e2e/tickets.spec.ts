import { expect, test } from '@playwright/test';
import fs from 'node:fs';
const fixture = () =>
    JSON.parse(fs.readFileSync(process.env.P01_E2E_FIXTURES!, 'utf8'));

test('ticket dialogs, details sheet, assignment, history, filters and mobile layout', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page
        .getByLabel('Email', { exact: true })
        .fill('manager@example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture().password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL('/account');
    await page.goto(`/projects/${fixture().projectId}/tickets`);
    await page
        .getByRole('button', { name: '+ New ticket', exact: true })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog
        .getByLabel('Summary', { exact: true })
        .fill('Create a thoughtful homepage');
    await dialog
        .getByLabel('Description', { exact: true })
        .fill('A clear introduction for the team.');
    await dialog.getByLabel('Estimate (hours)', { exact: true }).fill('2.5');
    await dialog
        .getByRole('button', { name: 'Create ticket', exact: true })
        .click();
    await expect(
        page.getByRole('heading', {
            name: 'Create a thoughtful homepage',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Open full ticket details' }),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Edit ticket', exact: true })
        .click();
    const edit = page.getByRole('dialog').last();
    await edit
        .getByLabel('Summary', { exact: true })
        .fill('Refine the homepage');
    await edit.getByLabel('Assignee', { exact: true }).click();
    await page
        .getByRole('option', { name: 'Project Manager', exact: true })
        .click();
    await edit
        .getByRole('button', { name: 'Save ticket', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Refine the homepage', exact: true }),
    ).toBeVisible();
    await expect(page.getByLabel('Ticket activity')).toContainText(
        'updated this ticket',
    );
    await page.screenshot({
        path: '.p01-work/p03-details-desktop.png',
        fullPage: true,
        animations: 'disabled',
    });
    await page.getByRole('link', { name: 'Open full ticket details' }).click();
    await expect(page).toHaveURL(/\/tickets\/\d+$/);
    await page.getByRole('link', { name: 'Back to tickets' }).click();
    await page.goto(
        `/projects/${fixture().projectId}/tickets?per_page=1&page=2&sort=number&direction=asc`,
    );
    await page
        .getByRole('link', { name: /Open TEAM-.*Refine the homepage/ })
        .click();
    await expect(page).toHaveURL(/page=2/);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/page=2/);
    await page.goto(`/projects/${fixture().projectId}/tickets`);
    await page.getByLabel('Search', { exact: true }).fill('Refine');
    await page.getByRole('button', { name: 'Apply', exact: true }).click();
    await expect(page).toHaveURL(/q=Refine/);
    await expect(
        page.getByRole('table', { name: 'Project tickets' }),
    ).toContainText('Refine the homepage');
    await page.getByRole('button', { name: 'Ticket options' }).click();
    await page.getByRole('menuitem', { name: 'Ticket settings' }).click();
    await page.getByLabel('Name', { exact: true }).fill('Research');
    await page.getByLabel('Order', { exact: true }).fill('4');
    await page.getByRole('button', { name: 'Save entry', exact: true }).click();
    await expect(page.getByRole('dialog')).toContainText('Research');
    await page.keyboard.press('Escape');
    await page.screenshot({
        path: '.p01-work/p03-table-desktop.png',
        fullPage: true,
        animations: 'disabled',
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page
        .getByRole('link', { name: /Open TEAM-.*Refine the homepage/ })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Refine the homepage', exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: '.p01-work/p03-details-mobile.png',
        fullPage: true,
        animations: 'disabled',
    });
    expect((await page.getByRole('dialog').boundingBox())?.width).toBe(390);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    expect(errors).toEqual([]);
});
