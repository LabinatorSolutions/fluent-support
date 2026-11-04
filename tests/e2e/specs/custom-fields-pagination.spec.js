// Settings > Custom Fields (CustomFields.vue): the API returns every field, so
// the pager slices the list on screen. Edit, delete and drag on a later page
// must act on that row's place in the full list, which is saved as a whole.
import { test, expect } from '@playwright/test';
import { adminPage, api, watchErrors } from '../lib/app.js';
import { php } from '../lib/lab.js';

const CF = '\\FluentSupportPro\\App\\Modules\\CustomFields\\CustomFields';

function setFields(fields) {
    php(`${CF}::save(json_decode('${JSON.stringify(fields)}', true)); echo '[]';`);
}

function savedSlugs() {
    return php(`echo wp_json_encode(array_column(${CF}::all(), 'slug'));`);
}

const twelve = Array.from({ length: 12 }, (_, i) => ({ label: `Field ${i + 1}`, type: 'text', slug: `cf_field_${i + 1}` }));
const slugs = numbers => numbers.map(n => `cf_field_${n}`);

test.describe('custom fields pagination', () => {
    let original;
    let checkErrors;

    test.beforeAll(() => {
        original = php(`echo wp_json_encode(${CF}::all());`);
    });

    test.afterAll(() => setFields(original));

    test.beforeEach(async ({ page }) => {
        checkErrors = watchErrors(page);
        setFields(twelve);
        const loaded = api(page, 'GET', 'ticket-custom-fields');
        await page.goto(`${adminPage}#/settings/custom-fields`);
        await loaded;
    });

    test.afterEach(() => checkErrors());

    const rows = page => page.locator('.fs_custom_field_row');
    const pageText = page => page.locator('.fs_pagination_left p');

    async function goToPage(page, number) {
        await page.locator('.fs_pagination_right .el-pager li', { hasText: String(number) }).click();
    }

    async function deleteRow(page, index) {
        const saved = api(page, 'POST', 'ticket-custom-fields');
        await rows(page).nth(index).locator('.fs_more_action').click();
        await page.locator('.fs_table_more_actions_dropdown_menu:visible .fs_delete_action').click();
        await page.locator('.el-dialog:visible .fs_popconfirm_actions .fs_filled_btn').click();
        await saved;
    }

    test('shows 10 fields per page with the right page count', async ({ page }) => {
        await expect(pageText(page)).toHaveText('Page 1 of 2');
        await expect(rows(page)).toHaveCount(10);
        await expect(rows(page).first()).toContainText('Field 1');
        await expect(rows(page).last()).toContainText('Field 10');

        await goToPage(page, 2);
        await expect(pageText(page)).toHaveText('Page 2 of 2');
        await expect(rows(page)).toHaveCount(2);
        await expect(rows(page).nth(0)).toContainText('Field 11');
        await expect(rows(page).nth(1)).toContainText('Field 12');
    });

    test('edit on page 2 opens that field', async ({ page }) => {
        await goToPage(page, 2);
        await rows(page).nth(1).locator('.fs_action_button:not(.fs_more_action)').click();

        const dialog = page.locator('.el-dialog:visible');
        await expect.poll(() => dialog.locator('input').evaluateAll(els => els.map(el => el.value))).toContain('Field 12');
        await expect.poll(() => dialog.locator('input').evaluateAll(els => els.map(el => el.value))).toContain('cf_field_12');
    });

    test('delete on page 2 removes that field and steps back when the page empties', async ({ page }) => {
        await goToPage(page, 2);

        await deleteRow(page, 0);
        expect(savedSlugs()).toEqual(slugs([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12]));
        await expect(rows(page)).toHaveCount(1);
        await expect(rows(page).first()).toContainText('Field 12');

        await deleteRow(page, 0);
        expect(savedSlugs()).toEqual(slugs([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]));
        await expect(pageText(page)).toHaveText('Page 1 of 1');
        await expect(rows(page)).toHaveCount(10);
    });

    test('adding a field from page 1 shows the page it landed on', async ({ page }) => {
        await page.getByRole('button', { name: 'Add New Field' }).click();
        const dialog = page.locator('.el-dialog:visible');
        await dialog.locator('.fs_select_field').click();
        await page.locator('.el-select-dropdown__item:visible', { hasText: 'Single Line Text' }).click();
        await dialog.getByPlaceholder('Custom Field Public Label').fill('Field 13');

        const saved = api(page, 'POST', 'ticket-custom-fields');
        await dialog.getByRole('button', { name: 'Add', exact: true }).click();
        await saved;

        expect(savedSlugs()).toHaveLength(13);
        await expect(pageText(page)).toHaveText('Page 2 of 2');
        await expect(rows(page).last()).toContainText('Field 13');
    });

    test('dragging on page 2 reorders only those fields in the saved list', async ({ page }) => {
        await goToPage(page, 2);

        const from = await rows(page).nth(1).locator('.fs_custom_drag_svg').boundingBox();
        const to = await rows(page).nth(0).boundingBox();
        const saved = api(page, 'POST', 'ticket-custom-fields');
        await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
        await page.mouse.down();
        await page.mouse.move(from.x + from.width / 2, to.y + 4, { steps: 12 });
        await page.mouse.move(from.x + from.width / 2, to.y + 2, { steps: 4 });
        await page.mouse.up();
        await saved;

        expect(savedSlugs()).toEqual(slugs([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 11]));
        await expect(rows(page).nth(0)).toContainText('Field 12');
        await expect(rows(page).nth(1)).toContainText('Field 11');
    });
});
