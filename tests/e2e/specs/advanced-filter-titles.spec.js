// Tag and product titles are text, never markup. Settings > Tags and Settings >
// Products save a title with markup as plain text, and the advanced ticket
// filter (_OptionSelector.vue) shows a title that already holds markup as
// text without running it.
import { test, expect } from '@playwright/test';
import { adminPage, api, openTicketList, watchErrors } from '../lib/app.js';
import { php } from '../lib/lab.js';

const marker = 'e2exss' + Date.now().toString(36);
const markup = flag => `<img src=x onerror="window.${flag}=1">`;

function storedTitles(table, where = '') {
    return php(`global $wpdb; echo wp_json_encode($wpdb->get_col($wpdb->prepare("SELECT title FROM {$wpdb->prefix}fs_${table} WHERE title LIKE %s ${where} ORDER BY id", '%${marker}%')));`);
}

// Raw insert, as rows saved before titles were sanitized look in the database.
function insertRaw(table, row) {
    php(`global $wpdb; $row = json_decode('${JSON.stringify(row)}', true); $row['created_at'] = $row['updated_at'] = current_time('mysql'); $wpdb->insert($wpdb->prefix . 'fs_${table}', $row); echo wp_json_encode([$wpdb->insert_id]);`);
}

test.describe('tag and product titles', () => {
    let checkErrors;

    test.beforeEach(({ page }) => {
        checkErrors = watchErrors(page);
    });

    test.afterEach(() => checkErrors());

    test.afterAll(() => {
        php(`global $wpdb; $like = '%${marker}%'; foreach (['products', 'taggables'] as $t) { $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}fs_{$t} WHERE title LIKE %s", $like)); } echo '[]';`);
    });

    async function saveInDialog(page, route, openLabel, title) {
        await page.goto(`${adminPage}#/settings/${route}`);
        await page.locator('.fs_box_actions .fs_filled_btn').click();
        const dialog = page.locator('.el-dialog:visible');
        await dialog.locator('input').first().fill(title);
        const saved = api(page, 'POST', route);
        await dialog.locator('.fs_dialog_footer .fs_filled_btn').click();
        expect((await saved).status(), openLabel).toBe(200);
    }

    test('Settings > Tags saves a title with markup as plain text', async ({ page }) => {
        await saveInDialog(page, 'ticket-tags', 'create tag', `UI tag ${marker}${markup('fsE2eUiTag')}`);

        expect(storedTitles('taggables', "AND tag_type = 'ticket_tag'")).toEqual([`UI tag ${marker}`]);
    });

    test('Settings > Products saves a title with markup as plain text', async ({ page }) => {
        await saveInDialog(page, 'products', 'create product', `UI product ${marker}${markup('fsE2eUiProduct')}`);

        expect(storedTitles('products')).toEqual([`UI product ${marker}`]);
    });

    test('advanced filter shows stored markup in tag and product titles as text', async ({ page }) => {
        const tagTitle = `Legacy tag ${marker}${markup('fsE2eTag')}`;
        const productTitle = `Legacy product ${marker}${markup('fsE2eProduct')}`;
        insertRaw('taggables', { tag_type: 'ticket_tag', title: tagTitle, slug: 'legacy-tag-' + marker, created_by: 1 });
        insertRaw('products', { title: productTitle, source: 'local', created_by: 1 });

        await openTicketList(page);
        await page.locator('.fs_advanced_button .el-switch').click();

        for (const [label, title] of [['Tags', tagTitle], ['Products', productTitle]]) {
            await page.locator('.fs_add_action_property').first().click();
            await page.locator('.el-cascader-node', { hasText: /^Tickets$/ }).first().click();
            await page.locator('.el-cascader-node', { hasText: new RegExp(`^${label}$`) }).first().click();
            await page.locator('.fs_rich_filter_item .fs_options_selector .el-select').last().click();

            const dropdown = page.locator('.el-select-dropdown:visible');
            await expect(dropdown.locator('.el-select-dropdown__item', { hasText: `Legacy ${label === 'Tags' ? 'tag' : 'product'} ${marker}` }), `${label} option`).toHaveText(title);
            await expect(dropdown.locator('img'), `${label}: markup must not become elements`).toHaveCount(0);
            await page.keyboard.press('Escape');
        }

        expect(await page.evaluate(() => [window.fsE2eTag, window.fsE2eProduct])).toEqual([undefined, undefined]);
    });
});
