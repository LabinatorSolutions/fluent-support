// Settings > Tags saves exactly what the agent typed, over the real admin
// request (jQuery form post; PUT sent as POST with X-HTTP-Method-Override).
// Backslashes and quotes survive create and repeated edits, markup is removed,
// and an edit cannot change the row's tag type. Needs Pro.
import { test, expect } from '@playwright/test';
import { adminPage, api, watchErrors } from '../lib/app.js';
import { php } from '../lib/lab.js';

const marker = 'e2etag' + Date.now().toString(36);

function storedTag() {
    return php(`global $wpdb; echo wp_json_encode($wpdb->get_row($wpdb->prepare("SELECT title, description, tag_type FROM {$wpdb->prefix}fs_taggables WHERE title LIKE %s", '%${marker}%'), ARRAY_A));`);
}

test.describe('Settings > Tags save', () => {
    let checkErrors;

    test.beforeEach(({ page }) => {
        checkErrors = watchErrors(page);
    });

    test.afterEach(() => checkErrors());

    test.afterAll(() => {
        php(`global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}fs_taggables WHERE title LIKE %s", '%${marker}%')); echo '[]';`);
    });

    test('backslashes and quotes survive create and two edits, markup is removed', async ({ page }) => {
        test.skip(!php(`echo wp_json_encode([defined('FLUENTSUPPORTPRO')]);`)[0], 'Fluent Support Pro is not active');

        const title = `Tag ${marker} can't "log in" C:\\Users\\x \\\\share`;
        const description = `Line one: C:\\Users\\x\nLine two: it's "quoted" \\\\share`;
        const expected = { title, description, tag_type: 'ticket_tag' };

        await page.goto(`${adminPage}#/settings/ticket-tags`);
        await page.locator('.fs_box_actions .fs_filled_btn').click();
        let dialog = page.locator('.el-dialog:visible');
        await dialog.locator('input').first().fill(title);
        await dialog.locator('textarea').fill(description + '<img src=x onerror="window.fsE2eTagDesc=1">');
        const created = api(page, 'POST', 'ticket-tags');
        await dialog.locator('.fs_dialog_footer .fs_filled_btn').click();
        expect((await created).status(), 'create').toBe(200);

        expect(storedTag(), 'after create').toEqual(expected);
        const tagId = php(`global $wpdb; echo wp_json_encode([(int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fs_taggables WHERE title LIKE %s", '%${marker}%'))]);`)[0];

        const search = page.locator('.fs_table_search_input input');
        await search.fill(marker);
        const listed = api(page, 'GET', 'ticket-tags');
        await search.press('Enter');
        await listed;

        for (const label of ['first edit', 'second edit']) {
            await page.locator('.fs_table_row', { hasText: marker }).locator('.fs_action_button:not(.fs_more_action)').click();
            dialog = page.locator('.el-dialog:visible');
            await expect(dialog.locator('input').first(), `${label}: dialog title`).toHaveValue(title);
            await expect(dialog.locator('textarea'), `${label}: dialog description`).toHaveValue(description);

            // The dialog sends the whole row back, tag_type and created_by included.
            const updated = api(page, 'PUT', `ticket-tags/${tagId}`);
            await dialog.locator('.fs_dialog_footer .fs_filled_btn').click();
            expect((await updated).status(), label).toBe(200);
            await expect(dialog).toBeHidden();

            expect(storedTag(), `after ${label}`).toEqual(expected);
        }
    });

    // Every way a client can send the tag: the admin app's form posts, JSON, the
    // method override and a real PUT. The stored row must equal what WordPress's
    // own sanitizers make of the input, computed in PHP, with nothing unslashed.
    test('hard strings store the same over every request shape', async ({ page }) => {
        test.skip(!php(`echo wp_json_encode([defined('FLUENTSUPPORTPRO')]);`)[0], 'Fluent Support Pro is not active');
        test.setTimeout(10 * 60_000);

        const mx = marker + 'slash'; // afterAll removes anything left behind by a failure
        const hard = [
            '\\', '\\\\', '\\\\\\\\', 'trailing\\', '\\\'', '\\"', '\\\\\'', 'O\'Reilly', 'say "hi"',
            'C:\\new\\table\\x', '\\\\server\\share', '/\\d+\\.\\d+/', 'literal \\n and \\t', '\\0 \\x41 \\u00e9',
            '{"a":"b\\"c"}', 'a+b=c&d%20e%5C', 'it\\\'s already escaped', 'বাংলা\\টেক্সট 🚀', '&amp; &lt;tag&gt; a < b',
            '<b>bold</b> \\<i>x</i>',
        ];
        const inputs = hard.map((s, i) => ({ title: `${mx}${String(i).padStart(2, '0')} ${s}`, description: `${s}\r\nsecond line ${s}\nthird\\` }));

        const b64 = v => Buffer.from(JSON.stringify(v)).toString('base64');
        const expected = php(`echo wp_json_encode(array_map(function ($r) { return ['title' => sanitize_text_field($r['title']), 'description' => sanitize_textarea_field($r['description'])]; }, json_decode(base64_decode('${b64(inputs)}'), true)));`);

        await page.goto(`${adminPage}#/settings/ticket-tags`);
        const rest = await page.evaluate(() => window.fluentSupportAdmin.rest);
        const send = async (shape, route, body) => {
            const headers = { 'X-WP-Nonce': rest.nonce };
            const url = `${rest.url}/${route}`;
            let response;
            if (shape === 'form POST') {
                response = await page.request.post(url, { headers, form: body });
            } else if (shape === 'json POST') {
                response = await page.request.post(url, { headers, data: body });
            } else if (shape === 'override PUT form') {
                response = await page.request.post(url, { headers: { ...headers, 'X-HTTP-Method-Override': 'PUT' }, form: body });
            } else if (shape === 'override PUT json') {
                response = await page.request.post(url, { headers: { ...headers, 'X-HTTP-Method-Override': 'PUT' }, data: body });
            } else {
                response = await page.request.put(url, { headers, form: body });
            }
            expect(response.status(), `${shape} ${route}`).toBe(200);
            return response.json();
        };
        // The row as the database has it, read back over REST (GET returns the raw model).
        const stored = async id => {
            const response = await page.request.get(`${rest.url}/ticket-tags/${id}`, { headers: { 'X-WP-Nonce': rest.nonce } });
            const { title, description, tag_type } = (await response.json()).tags;
            return { title, description, tag_type };
        };

        const failures = [];
        const check = async (id, i, step) => {
            const row = await stored(id);
            const want = { ...expected[i], tag_type: 'ticket_tag' };
            if (JSON.stringify(row) !== JSON.stringify(want)) {
                failures.push({ step, input: hard[i], want, got: row });
            }
        };

        for (let i = 0; i < inputs.length; i++) {
            for (const createShape of ['form POST', 'json POST']) {
                const created = await send(createShape, 'ticket-tags', inputs[i]);
                const id = created.tag.id;
                await check(id, i, `create (${createShape})`);
                for (const shape of ['override PUT form', 'override PUT json', 'real PUT form']) {
                    await send(shape, `ticket-tags/${id}`, inputs[i]);
                    await check(id, i, `${createShape} then ${shape}`);
                }
                // Re-save what is stored, twice, as the edit dialog does.
                for (const n of [1, 2]) {
                    const row = await stored(id);
                    await send('override PUT form', `ticket-tags/${id}`, { ...row, tag_type: 'contact_tag' });
                    await check(id, i, `${createShape} then re-save ${n}`);
                }
                await page.request.post(`${rest.url}/ticket-tags/${id}`, { headers: { 'X-WP-Nonce': rest.nonce, 'X-HTTP-Method-Override': 'DELETE' } });
            }
        }

        expect(failures, JSON.stringify(failures.slice(0, 5), null, 1)).toEqual([]);
    });
});
