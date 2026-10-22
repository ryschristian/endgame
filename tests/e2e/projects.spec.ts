import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

function fixture(): {
    password: string;
    projectId: number;
    projectInvitationToken: string;
    hiddenProjectId: number;
} {
    return JSON.parse(fs.readFileSync(process.env.P01_E2E_FIXTURES!, 'utf8'));
}
async function signIn(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(fixture().password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL('/account');
}

test('an administrator creates a project with a selected manager', async ({
    page,
}) => {
    await signIn(page, 'admin@example.test');
    await page.getByRole('link', { name: 'Projects', exact: true }).click();
    await page
        .getByRole('link', { name: 'Create project', exact: true })
        .click();
    await page.getByLabel('Project key', { exact: true }).fill('BROWSER');
    await page
        .getByLabel('Project name', { exact: true })
        .fill('Browser-created project');
    await page
        .getByLabel('Initial manager email', { exact: true })
        .fill('manager@example.test');
    await page
        .getByRole('button', { name: 'Create project', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Browser-created project' }),
    ).toBeVisible();
    await expect(
        page.getByRole('listitem').filter({ hasText: 'Project Manager' }),
    ).toHaveCount(1);
});

test('a manager configures a role, adds a member, and archives and restores the project', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await signIn(page, 'manager@example.test');
    await page.goto(`/projects/${fixture().projectId}/roles`);
    await page.getByLabel('Role name', { exact: true }).fill('Browser Reader');
    await page
        .getByRole('button', { name: 'Create role', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Browser Reader', exact: true }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Members', exact: true }).click();
    await page
        .getByLabel('Account email', { exact: true })
        .fill('project-member@example.test');
    await page
        .locator('form')
        .getByLabel('Browser Reader', { exact: true })
        .check();
    await page.getByRole('button', { name: 'Add member', exact: true }).click();
    await expect(
        page.getByText('project-member@example.test', { exact: true }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Overview', exact: true }).click();
    await page
        .getByRole('button', { name: 'Archive project', exact: true })
        .click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Confirm', exact: true })
        .click();
    await expect(
        page.getByRole('button', { name: 'Save settings', exact: true }),
    ).toBeDisabled();
    await page
        .getByRole('button', { name: 'Restore project', exact: true })
        .click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Confirm', exact: true })
        .click();
    await expect(
        page.getByRole('button', { name: 'Save settings', exact: true }),
    ).toBeEnabled();
    expect(errors).toEqual([]);
});

test('the intended account accepts a project invitation and cannot read another project', async ({
    page,
}) => {
    await page.goto(`/project-invitations/${fixture().projectInvitationToken}`);
    await expect(page).toHaveURL('/login');
    await page
        .getByLabel('Email', { exact: true })
        .fill('project-invitee@example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture().password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(
        `/project-invitations/${fixture().projectInvitationToken}`,
    );
    await page
        .getByRole('button', { name: 'Accept project invitation', exact: true })
        .click();
    await expect(page).toHaveURL(`/projects/${fixture().projectId}`);
    await expect(
        page.getByRole('link', { name: 'Members', exact: true }),
    ).toHaveCount(0);
    const response = await page.goto(`/projects/${fixture().hiddenProjectId}`);
    expect(response?.status()).toBe(404);
});

test('workspace navigation, project views, and the compact creation dialog work on desktop and mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await signIn(page, 'admin@example.test');
    await page.goto('/projects');
    await page.getByRole('button', { name: 'List view', exact: true }).click();
    await expect(
        page.getByRole('button', { name: 'List view', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('button', { name: 'Grid view', exact: true }).click();
    await expect(
        page.getByRole('button', { name: 'Grid view', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('Control+k');
    await expect(page.getByLabel('Search all projects')).toBeFocused();
    expect(
        await page.evaluate(async () => {
            await document.fonts.ready;
            return document.fonts.check('13px "Geist Sans"');
        }),
    ).toBe(true);
    await page.screenshot({
        path: path.resolve('.p01-work/workspace-desktop.png'),
        fullPage: true,
    });
    await page
        .getByRole('button', { name: 'New project', exact: true })
        .click();
    const dialog = page.getByRole('dialog');
    await expect(
        dialog.getByRole('heading', { name: 'New project', exact: true }),
    ).toBeVisible();
    await expect(dialog.getByRole('combobox')).toHaveCount(0);
    await dialog.getByLabel('Project key', { exact: true }).fill('DESIGN');
    await dialog
        .getByLabel('Project name', { exact: true })
        .fill('Design system');
    await dialog
        .getByLabel('Initial manager email', { exact: true })
        .fill('manager@example.test');
    await page.screenshot({
        path: path.resolve('.p01-work/workspace-create-dialog.png'),
        fullPage: true,
    });
    await dialog
        .getByRole('button', { name: 'Create project', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Design system', exact: true }),
    ).toBeVisible();
    await page.goto('/projects');
    await page.setViewportSize({ width: 390, height: 844 });
    await page
        .getByRole('button', { name: 'Open navigation', exact: true })
        .click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await expect(page.locator('[role="dialog"]')).toHaveCount(0);
    await page.getByRole('button', { name: 'List view', exact: true }).click();
    await expect(
        page.getByRole('button', { name: 'List view', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('button', { name: 'Grid view', exact: true }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    await page
        .getByRole('button', { name: 'Open navigation', exact: true })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('link', { name: 'Projects', exact: true })
        .click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: path.resolve('.p01-work/workspace-mobile.png'),
        fullPage: true,
    });
});
