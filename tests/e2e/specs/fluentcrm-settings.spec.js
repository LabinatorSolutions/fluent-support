// Settings > FluentCRM Integration (FluentCRMIntegration.vue): the list and tag
// selects, saving them, and a new customer landing in that list and tag.
// Every save is checked in the database, not only on screen.
import { test, expect } from '@playwright/test';
import { adminPage, api, fixtures, watchErrors } from '../lib/app.js';
import { php } from '../lib/lab.js';

const KEY = '_fluentcrm_intergration_settings';

function setSettings(value) {
    const code = value === null
        ? `\\FluentSupport\\App\\Models\\Meta::where('object_type', 'option')->where('key', '${KEY}')->delete();`
        : `\\FluentSupport\\App\\Services\\Helper::updateOption('${KEY}', json_decode('${JSON.stringify(value)}', true));`;
    php(`${code} echo '[]';`);
}

function savedSettings() {
    return php(`echo wp_json_encode(\\FluentSupport\\App\\Services\\Helper::getOption('${KEY}', []));`);
}

test.describe('FluentCRM integration settings', () => {
    let fx;
    let crm;
    let original;
    let checkErrors;

    test.beforeAll(() => {
        const { active } = php(`echo wp_json_encode(['active' => class_exists('\\\\FluentCrm\\\\App\\\\Models\\\\Lists')]);`);
        if (!active) {
            throw new Error('FluentCRM is not active in the lab. Run: bash tests/bin/lab setup');
        }
        fx = fixtures();
        original = savedSettings();
        crm = php(`
            $list = \\FluentCrm\\App\\Models\\Lists::create(['title' => 'E2E List ${fx.marker}', 'slug' => 'e2e-list-${fx.marker}']);
            $tag = \\FluentCrm\\App\\Models\\Tag::create(['title' => 'E2E Tag ${fx.marker}', 'slug' => 'e2e-tag-${fx.marker}']);
            echo wp_json_encode(['list' => (int) $list->id, 'tag' => (int) $tag->id]);
        `);
    });

    test.afterAll(() => {
        if (!crm) {
            return;
        }
        php(`
            $subscriber = \\FluentCrm\\App\\Models\\Subscriber::where('email', 'crm-${fx.marker}@example.test')->first();
            if ($subscriber) { $subscriber->lists()->detach(); $subscriber->tags()->detach(); $subscriber->delete(); }
            \\FluentSupport\\App\\Models\\Customer::where('email', 'crm-${fx.marker}@example.test')->delete();
            \\FluentCrm\\App\\Models\\Lists::where('id', ${crm.list})->delete();
            \\FluentCrm\\App\\Models\\Tag::where('id', ${crm.tag})->delete();
            echo '[]';
        `);
        setSettings(Array.isArray(original) && !original.length ? null : original);
    });

    test.beforeEach(({ page }) => {
        checkErrors = watchErrors(page);
    });

    test.afterEach(() => checkErrors());

    const selects = page => page.locator('.fs_fluentcrm_settings .fs_input-options_wrapper .el-select');
    const listShown = page => selects(page).nth(0).locator('.el-select__placeholder');

    async function openSettings(page, enable = false) {
        const loaded = api(page, 'GET', 'settings/fluentcrm-settings');
        const url = `${adminPage}#/settings/fluentcrm-integration`;
        // A goto that only changes the hash keeps the page, so reload to fetch again.
        await (page.url().endsWith(url) ? page.reload() : page.goto(url));
        await loaded;
        if (enable) {
            await page.locator('.fs_fluentcrm_settings').getByText('Enable FluentCRM Integration').click();
        }
        await expect(selects(page)).toHaveCount(2);
    }

    async function pick(page, index, title) {
        await selects(page).nth(index).click();
        await page.locator('.el-select-dropdown__item:visible', { hasText: title }).click();
        await page.keyboard.press('Escape');
    }

    async function save(page) {
        const saved = api(page, 'POST', 'settings');
        await page.locator('.fs_fluentcrm_save_btn').click();
        expect((await saved).status()).toBe(200);
    }

    test('with nothing saved the list select shows its placeholder', async ({ page }) => {
        setSettings(null);
        await openSettings(page, true);

        await expect(listShown(page)).toHaveClass(/is-transparent/);
        await expect(listShown(page)).not.toHaveText(/NaN|^0$/);
    });

    test('a saved "no list" (0) shows the placeholder, not 0', async ({ page }) => {
        setSettings({ enabled: 'yes', default_status: 'subscribed', assigned_list: 0, assigned_tags: [crm.tag] });
        await openSettings(page);

        await expect(listShown(page)).toHaveClass(/is-transparent/);
        await expect(listShown(page)).not.toHaveText(/NaN|^0$/);
    });

    test('picking a list and a tag saves their ids and shows them after reload', async ({ page }) => {
        setSettings({ enabled: 'yes', default_status: 'subscribed', assigned_list: 0, assigned_tags: [] });
        await openSettings(page);

        await pick(page, 0, `E2E List ${fx.marker}`);
        await pick(page, 1, `E2E Tag ${fx.marker}`);
        await save(page);

        const saved = savedSettings();
        expect([saved.assigned_list, saved.assigned_tags]).toEqual([crm.list, [crm.tag]]);

        await openSettings(page);
        await expect(listShown(page)).toHaveText(`E2E List ${fx.marker}`);
        await expect(selects(page).nth(1)).toContainText(`E2E Tag ${fx.marker}`);
    });

    test('clearing the list saves 0 and shows the placeholder after reload', async ({ page }) => {
        setSettings({ enabled: 'yes', default_status: 'subscribed', assigned_list: crm.list, assigned_tags: [crm.tag] });
        await openSettings(page);
        await expect(listShown(page)).toHaveText(`E2E List ${fx.marker}`);

        await selects(page).nth(0).hover();
        await selects(page).nth(0).locator('.el-select__clear').click();
        await save(page);

        expect(savedSettings().assigned_list).toBe(0);

        await openSettings(page);
        await expect(listShown(page)).toHaveClass(/is-transparent/);
        await expect(listShown(page)).not.toHaveText(/NaN|^0$/);
    });

    test('a new customer is added to the saved list and tag', async () => {
        setSettings({ enabled: 'yes', default_status: 'subscribed', assigned_list: crm.list, assigned_tags: [crm.tag] });

        const contact = php(`
            \\FluentSupport\\App\\Modules\\Customers\\CustomerService::findOrCreate(['email' => 'crm-${fx.marker}@example.test', 'first_name' => 'Crm', 'last_name' => 'E2E']);
            $subscriber = \\FluentCrm\\App\\Models\\Subscriber::where('email', 'crm-${fx.marker}@example.test')->first();
            echo wp_json_encode($subscriber ? [
                'status' => $subscriber->status,
                'lists'  => array_map('intval', $subscriber->lists()->pluck('fc_lists.id')->all()),
                'tags'   => array_map('intval', $subscriber->tags()->pluck('fc_tags.id')->all()),
            ] : ['missing' => true]);
        `);

        expect(contact).toEqual({ status: 'subscribed', lists: [crm.list], tags: [crm.tag] });
    });
});
