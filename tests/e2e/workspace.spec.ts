import { expect, test } from '@playwright/test';
import fs from 'node:fs';
const fixture = () =>
    JSON.parse(fs.readFileSync(process.env.P01_E2E_FIXTURES!, 'utf8'));

test('manager publishes a dynamic workflow, drags tickets, schedules work and uses every view', async ({
    page,
}) => {
    test.setTimeout(90_000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.setViewportSize({ width: 1600, height: 1000 });
    await page.goto('/login');
    await page
        .getByLabel('Email', { exact: true })
        .fill('manager@example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture().password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL('/account');
    const base = `/projects/${fixture().projectId}`;
    await page.goto(`${base}/workflow`);
    await page.getByLabel('Stage 1 name', { exact: true }).fill('Backlog');
    await page.getByLabel('Stage 3 name', { exact: true }).fill('Done');
    await page.getByRole('button', { name: 'Add stage', exact: true }).click();
    await page.getByLabel('Stage 4 name', { exact: true }).fill('To Do');
    await page.getByRole('button', { name: 'Add stage', exact: true }).click();
    await page.getByLabel('Stage 5 name', { exact: true }).fill('Review');
    await page
        .getByRole('button', { name: 'Move To Do up', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Move To Do up', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Move Review up', exact: true })
        .click();
    await page.getByLabel('Backlog to In Progress', { exact: true }).uncheck();
    await page.getByLabel('In Progress to Done', { exact: true }).uncheck();
    await page.getByLabel('Backlog to To Do', { exact: true }).check();
    await page.getByLabel('To Do to In Progress', { exact: true }).check();
    await page.getByLabel('In Progress to Review', { exact: true }).check();
    await page.getByLabel('Review to Done', { exact: true }).check();
    await page
        .getByRole('button', { name: 'Publish workflow', exact: true })
        .click();
    await expect(
        page.getByText('Published version 2', { exact: false }),
    ).toBeVisible();
    await expect(page.locator('[data-activity-toast]')).toContainText([
        'Workflow published.',
    ]);
    await page.getByRole('link', { name: 'Board', exact: true }).click();
    await expect(page.getByLabel('Project board')).toBeVisible();
    await expect(page.getByLabel('Review column')).toBeVisible();
    await page.getByRole('button', { name: 'New ticket', exact: true }).click();
    await page
        .getByRole('dialog')
        .getByLabel('Summary', { exact: true })
        .fill('Workspace browser task');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Create ticket', exact: true })
        .click();
    await expect(page).toHaveURL(/\/board\?ticket=/);
    await expect(
        page.getByRole('heading', {
            name: 'Workspace browser task',
            exact: true,
        }),
    ).toBeVisible();
    await page.keyboard.press('Escape');
    const card = page
        .getByLabel('Backlog column')
        .locator('article')
        .filter({ hasText: 'Workspace browser task' });
    await card.dragTo(page.getByLabel('To Do column'));
    await expect(page.getByLabel('To Do column')).toContainText(
        'Workspace browser task',
    );
    await page.reload();
    await expect(page.getByLabel('To Do column')).toContainText(
        'Workspace browser task',
    );
    const select = page
        .getByLabel('To Do column')
        .locator('article')
        .filter({ hasText: 'Workspace browser task' })
        .getByRole('combobox');
    await select.selectOption({ label: 'In Progress' });
    await expect(page.getByLabel('In Progress column')).toContainText(
        'Workspace browser task',
    );
    await page
        .getByLabel('In Progress column')
        .locator('article')
        .filter({ hasText: 'Workspace browser task' })
        .getByRole('combobox')
        .selectOption({ label: 'Review' });
    await expect(page.getByLabel('Review column')).toContainText(
        'Workspace browser task',
    );
    await page
        .getByLabel('Review column')
        .locator('article')
        .filter({ hasText: 'Workspace browser task' })
        .getByRole('combobox')
        .selectOption({ label: 'Done' });
    await page
        .getByLabel('Resolution', { exact: true })
        .selectOption({ label: 'Completed' });
    await page
        .getByRole('button', { name: 'Change status', exact: true })
        .click();
    await expect(page.getByLabel('Done column')).toContainText(
        'Workspace browser task',
    );
    await page
        .getByLabel('Done column')
        .locator('article')
        .filter({ hasText: 'Workspace browser task' })
        .getByRole('combobox')
        .selectOption({ label: 'Backlog' });
    await page.getByLabel('Reopening reason').fill('Needs a regression test');
    await page
        .getByRole('button', { name: 'Change status', exact: true })
        .click();
    await expect(page.getByLabel('Backlog column')).toContainText(
        'Workspace browser task',
    );
    await page.screenshot({
        path: '.p01-work/workspace-board.png',
        fullPage: true,
    });
    await page.getByRole('link', { name: 'List', exact: true }).click();
    await expect(page.getByLabel('Grouped ticket list')).toContainText(
        'Workspace browser task',
    );
    await page.getByRole('link', { name: 'Table', exact: true }).click();
    await expect(
        page.getByRole('table', { name: 'Project tickets' }),
    ).toContainText('Workspace browser task');
    await page.getByRole('link', { name: 'Calendar', exact: true }).click();
    const unscheduled = page
        .locator('section')
        .filter({ has: page.getByRole('heading', { name: /Unscheduled/ }) });
    await unscheduled
        .getByRole('button', { name: /Set dates for/ })
        .last()
        .click();
    const dates = page.getByRole('dialog', {
        name: 'Ticket dates',
        exact: true,
    });
    await dates.getByRole('button', { name: 'Due date', exact: true }).click();
    await page
        .getByRole('gridcell')
        .filter({ hasText: /^28$/ })
        .first()
        .click();
    await page.keyboard.press('Escape');
    await dates
        .getByRole('button', { name: 'Save dates', exact: true })
        .click();
    await expect(dates).not.toBeVisible();
    await page.getByRole('link', { name: 'Timeline', exact: true }).click();
    await expect(page.getByLabel('Ticket timeline')).toContainText(
        'Workspace browser task',
    );
    const lane = page
        .locator('[data-timeline-ticket]')
        .filter({ hasText: 'Workspace browser task' });
    await lane
        .locator('[draggable="true"]')
        .dragTo(lane.locator('[data-timeline-date="2026-10-29"]'));
    await expect(page.locator('[data-activity-toast]').last()).toContainText(
        'Ticket dates saved.',
    );
    const firstUnscheduled = page
        .locator('section')
        .filter({ has: page.getByRole('heading', { name: /Unscheduled/ }) })
        .locator('[draggable="true"]')
        .filter({ hasText: 'Review project scope' });
    const firstLane = page
        .locator('[data-timeline-ticket]')
        .filter({ hasText: 'Review project scope' });
    await firstUnscheduled.dragTo(
        firstLane.locator('[data-timeline-date="2026-10-26"]'),
    );
    await expect(firstLane.locator('[draggable="true"]')).toBeVisible();
    await page.reload();
    await expect(firstLane.locator('[draggable="true"]')).toHaveAttribute(
        'title',
        /2026-10-26/,
    );
    await page.getByRole('link', { name: 'Overview', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'Progress', exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: '.p01-work/workspace-overview.png',
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${base}/board`);
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: '.p01-work/workspace-board-mobile.png',
        fullPage: true,
    });
    expect(errors).toEqual([]);
});
