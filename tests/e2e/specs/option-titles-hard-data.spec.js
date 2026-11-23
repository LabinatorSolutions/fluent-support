// The advanced filter value dropdown (_OptionSelector.vue) shows tag, product,
// agent and mailbox names as text. Rows are written raw, as old data or another
// plugin could have stored them: markup must not become elements or run, Vue
// template syntax must stay literal, and every option must read exactly as the
// page received it (entities, quotes, backslashes, RTL, emoji, long names).
import { test, expect } from '@playwright/test';
import { openTicketList, watchErrors } from '../lib/app.js';
import { php } from '../lib/lab.js';

const marker = 'e2ehard' + Date.now().toString(36);

const hard = [
    '<img src=x onerror="window.__fsHard=1">',
    '<svg onload="window.__fsHard=2"></svg>svg',
    '"><script>window.__fsHard=3</script>',
    '{{ constructor.constructor(\'window.__fsHard=4\')() }}',
    '${window.__fsHard=5}',
    '<a href="javascript:window.__fsHard=6">link</a>',
    '<style>body{display:none}</style>styled',
    '&lt;b&gt;encoded&lt;/b&gt; &amp; &quot; &#039; &#x3C;',
    '<b>bold</b><i>italic</i>',
    'a < b > c & d <= e',
    'C:\\Users\\x \\\\share \\\' \\" trailing\\',
    'O\'Reilly "quoted" `tick`',
    'مرحبا עברית \u202Ereversed',
    '👩‍👩‍👧 zero\u200Bwidth বাংলা',
    'x'.repeat(150),
];

const name = i => `${marker}${String(i).padStart(2, '0')} ${hard[i]}`;

function seed() {
    const rows = Buffer.from(JSON.stringify(hard.map((_, i) => name(i)))).toString('base64');
    php(`global $wpdb; $p = $wpdb->prefix; $now = current_time('mysql');
        foreach (json_decode(base64_decode('${rows}'), true) as $i => $n) {
            $wpdb->insert($p . 'fs_taggables', ['tag_type' => 'ticket_tag', 'title' => $n, 'slug' => '${marker}-t' . $i, 'created_at' => $now, 'updated_at' => $now]);
            $wpdb->insert($p . 'fs_products', ['title' => $n, 'source' => 'local', 'created_at' => $now, 'updated_at' => $now]);
            $wpdb->insert($p . 'fs_mail_boxes', ['name' => $n, 'slug' => '${marker}-m' . $i, 'box_type' => 'web', 'email' => '${marker}-' . $i . '@example.test', 'created_at' => $now, 'updated_at' => $now]);
            $wpdb->insert($p . 'fs_persons', ['person_type' => 'agent', 'first_name' => $n, 'last_name' => '', 'email' => '${marker}-' . $i . '@agent.example.test', 'hash' => md5('${marker}' . $i), 'created_at' => $now, 'updated_at' => $now]);
        } echo '[]';`);
}

function cleanup() {
    php(`global $wpdb; $p = $wpdb->prefix; $like = '${marker}%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$p}fs_taggables WHERE title LIKE %s", $like));
        $wpdb->query($wpdb->prepare("DELETE FROM {$p}fs_products WHERE title LIKE %s", $like));
        $wpdb->query($wpdb->prepare("DELETE FROM {$p}fs_mail_boxes WHERE name LIKE %s", $like));
        $wpdb->query($wpdb->prepare("DELETE FROM {$p}fs_persons WHERE person_type = 'agent' AND first_name LIKE %s", $like)); echo '[]';`);
}

const properties = [
    ['Tags', 'ticket_tags', o => o.title],
    ['Products', 'support_products', o => o.title],
    ['Assigned Agent', 'support_agents', o => o.full_name],
    ['Ticket Mailbox', 'mailboxes', o => o.name],
];

test.describe('advanced filter options with hard data', () => {
    test.beforeAll(seed);
    test.afterAll(cleanup);

    test('every option reads as text, nothing runs or renders', async ({ page }) => {
        const checkErrors = watchErrors(page);
        await openTicketList(page);
        await page.locator('.fs_advanced_button .el-switch').click();

        for (const [label, key, text] of properties) {
            const received = await page.evaluate(([k, m]) => Object.values(window.fluentSupportAdmin[k] || {})
                .filter(o => JSON.stringify(o).includes(m)), [key, marker]);
            const expected = received.map(text).sort();
            expect(expected.length, `${label}: seeded rows reach the page`).toBe(hard.length);

            await page.locator('.fs_add_action_property').first().click();
            await page.locator('.el-cascader-node', { hasText: /^Tickets$/ }).first().click();
            await page.locator('.el-cascader-node', { hasText: new RegExp(`^${label}$`) }).first().click();
            const select = page.locator('.fs_rich_filter_item .fs_options_selector .el-select').last();
            // Options mount with the select, so injected markup would act before the dropdown opens.
            expect(await page.evaluate(() => window.__fsHard), `${label}: no payload ran`).toBeUndefined();
            expect(await page.evaluate(() => getComputedStyle(document.body).display), `${label}: page not hidden by injected CSS`).not.toBe('none');
            await select.click();

            const dropdown = page.locator('.el-select-dropdown:visible');
            const items = dropdown.locator('.el-select-dropdown__item', { hasText: marker });
            await expect(items, `${label}: options`).toHaveCount(hard.length);
            const shown = (await items.evaluateAll(els => els.map(el => el.textContent))).sort();
            expect(shown, `${label}: option text equals the data`).toEqual(expected);
            await expect(dropdown.locator('.el-select-dropdown__item *'), `${label}: no elements inside options`).toHaveCount(0);

            // The selected value is text too. Element Plus icons are <i class="el-icon"><svg>, so skip those.
            const first = shown[0];
            await items.filter({ hasText: first.slice(0, marker.length + 2) }).first().click();
            await page.keyboard.press('Escape');
            const filterItem = page.locator('.fs_rich_filter_item').last();
            await expect(filterItem.locator('.fs_options_selector .el-select'), `${label}: selected value text`).toContainText(first.slice(0, 40));
            await expect(filterItem.locator('.fs_options_selector').locator('img, script, style, a, b, [onerror], [onload], svg:not(.el-icon svg)'), `${label}: selected value has no injected elements`).toHaveCount(0);
        }

        expect(await page.evaluate(() => window.__fsHard), 'no payload ran').toBeUndefined();
        await expect(page.locator('body')).toBeVisible();
        checkErrors();
    });
});
