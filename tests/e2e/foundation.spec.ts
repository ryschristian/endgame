import { expect, test } from '@playwright/test';

test('loads the Vue application and follows the health link', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto('/');
    await expect(
        page.getByRole('heading', { name: 'Endgame', level: 1 }),
    ).toBeVisible();

    const responsePromise = page.waitForResponse('**/up');
    await page.getByRole('link', { name: 'Check application health' }).click();

    expect((await responsePromise).status()).toBe(200);
    await expect(page).toHaveURL('/up');
    expect(errors).toEqual([]);
});
