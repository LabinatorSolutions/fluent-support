// The admin ticket list (AllTickets.vue): search, status tabs, the views
// sidebar, opening a ticket, bulk actions. Every case narrows the list to this
// run's tickets by searching for the run marker.
import { test, expect } from '@playwright/test';
import { api, fixtures, openTicketList, rowTitles, rows, searchMarker, watchErrors } from '../lib/app.js';
import { conversations, mailSent, php, reseed, ticket } from '../lib/lab.js';

const titles = (fx, keys) => keys.map(key => `${key.charAt(0).toUpperCase() + key.slice(1).replace('_', ' ')} ticket ${fx.marker}`).sort();

test.describe('ticket list', () => {
    let fx;
    let checkErrors;

    test.beforeAll(() => reseed());

    test.beforeEach(async ({ page }) => {
        fx = fixtures();
        checkErrors = watchErrors(page);
        await openTicketList(page);
    });

    test.afterEach(() => checkErrors());

    test('search shows the open tickets that match, with the count in the title', async ({ page }) => {
        await searchMarker(page);

        expect((await rowTitles(page)).sort()).toEqual(titles(fx, ['view', 'new', 'new2', 'critical', 'other_box']));
        await expect(page.locator('.fs_page_title')).toContainText('(5)');

        const viewRow = rows(page).filter({ hasText: `View ticket ${fx.marker}` });
        await expect(viewRow.locator('.fs_ticket_product')).toContainText(`E2E Product ${fx.marker}`);
        await expect(viewRow.locator('.fs_ticket_author')).toHaveText('by Carla');
        await expect(viewRow.locator('.fs_ticket_description')).toContainText(`Internal note ${fx.marker}`);
        await expect(viewRow).toContainText(`Billing ${fx.marker}`);
        await expect(viewRow.locator('.fs_ticket_agent_badge')).toContainText('Lab Agent');
    });

    test('status tabs filter the list', async ({ page }) => {
        await searchMarker(page);
        const tab = async (label, expected) => {
            const loaded = api(page, 'GET', 'tickets');
            await page.locator('.fs_segment_button', { hasText: new RegExp(`^\\s*${label}\\s*$`) }).click();
            await loaded;
            expect((await rowTitles(page)).sort(), label).toEqual(titles(fx, expected));
        };

        await tab('Closed', ['closed']);
        await tab('New', ['new', 'new2']);
        await tab('All', ['view', 'new', 'new2', 'closed', 'critical', 'other_box']);
        await tab('Open', ['view', 'new', 'new2', 'critical', 'other_box']);
    });

    test('waiting for reply toggles a filter', async ({ page }) => {
        await searchMarker(page);
        const loaded = api(page, 'GET', 'tickets');
        await page.locator('.fs_waiting_reply_btn').click();
        const response = await loaded;
        expect(response.url()).toContain('waiting_for_reply');
        await expect(page.locator('.fs_waiting_reply_btn')).toHaveClass(/fs_waiting_reply_btn_active/);
    });

    test('a sidebar view filters the list and names it in the title', async ({ page }) => {
        await searchMarker(page);
        // A view replaces the search: it lists every critical ticket.
        const loaded = api(page, 'GET', 'tickets');
        await page.getByRole('link', { name: /^Critical \d+$/ }).click();
        const response = await loaded;
        expect(decodeURIComponent(response.url())).toContain('critical');
        await expect(page.locator('.fs_page_title')).toContainText('Critical');
        const listed = await rowTitles(page);
        expect(listed).toContain(titles(fx, ['critical'])[0]);
        expect(listed).not.toContain(titles(fx, ['view'])[0]);
        expect(listed).not.toContain(titles(fx, ['new'])[0]);
    });

    test('the grid layout lists the same tickets', async ({ page }) => {
        await searchMarker(page);
        // The last toolbar popover is Display Settings (its button has only an icon).
        await page.locator('.fs_filter_right_section span > .el-button').last().click();
        await page.locator('.fs_display_settings_popover').getByText('Grid View').click();
        const grid = page.locator('.fs_tickets_grid_view .fs_grid_row');
        await expect(grid).toHaveCount(5);
        const gridTitles = (await page.locator('.fs_grid_row .fs_grid_title_text').allInnerTexts()).sort();
        expect(gridTitles).toEqual(titles(fx, ['view', 'new', 'new2', 'critical', 'other_box']));
        await expect(grid.filter({ hasText: `View ticket ${fx.marker}` }).locator('.fs_grid_td_product')).toContainText(`E2E Product ${fx.marker}`);
    });

    test('Add Ticket opens the new ticket form', async ({ page }) => {
        await page.locator('.fs_add_ticket_btn').click();
        const dialog = page.locator('.fs_tk_dialog');
        await expect(dialog).toBeVisible();
        await expect(dialog.getByPlaceholder(/Search or provide email address|Full email address/)).toBeVisible();
    });

    test('a call logged with "Save as an internal note" opens as a note and emails the customer nothing', async ({ page }) => {
        const email = `carla-${fx.marker}@example.test`;
        const title = `Logged call ${fx.marker}`;
        const mailBefore = mailSent().length;

        await page.locator('.fs_add_ticket_btn').click();
        const dialog = page.locator('.fs_tk_dialog');
        const search = dialog.getByPlaceholder(/Search or provide email address|Full email address/);
        await search.fill(email);
        await search.press('Enter');
        // Picking the contact moves straight to the ticket step.
        await dialog.locator('.fs_contact_results li').filter({ hasText: email }).click();

        await dialog.getByPlaceholder('What\'s this support ticket about?').fill(title);
        const body = dialog.frameLocator('iframe').locator('body');
        await body.click();
        await page.keyboard.type(`Customer called about the license ${fx.marker}`);

        // The two options exclude each other, and the editor says the text stays internal.
        const note = dialog.locator('.el-checkbox').filter({ hasText: 'Save as an internal note' });
        const outreach = dialog.locator('.el-checkbox').filter({ hasText: 'Initiated by agent' });
        await note.click();
        await expect(dialog).toContainText('Internal note (the customer will not see this)');
        await outreach.click();
        await expect(note).not.toHaveClass(/is-checked/);
        await note.click();
        await expect(outreach).not.toHaveClass(/is-checked/);

        const created = api(page, 'POST', 'tickets');
        await dialog.getByRole('button', { name: 'Create Ticket' }).click();
        const ticketId = (await (await created).json()).ticket.id;

        try {
            await expect(page.locator('.fs_view_ticket .fs_ticket_title')).toHaveText(title);
            await expect(page.locator('.fs_thread_started_by_agent')).toContainText('logged this ticket');
            await expect(page.locator('.fs_threads_container article.fs_thread_starter')).toHaveCount(0);
            await expect(page.locator('.fs_threads_container')).toContainText(`Customer called about the license ${fx.marker}`);

            expect(ticket(ticketId).source).toBe('agent_logged');
            expect(conversations(ticketId).map(row => row.conversation_type)).toEqual(['note']);
            expect(mailSent().slice(mailBefore).map(mail => mail.to).flat().join(',')).not.toContain(email);

            // The list shows the bundled source icon, not the letter fallback for a missing image.
            await openTicketList(page);
            await searchMarker(page);
            const row = rows(page).filter({ hasText: title });
            await expect(row.locator('.fs_ticket_source_wrap svg')).toHaveCount(1);
            await expect(row.locator('.fs_source_initial_badge')).toHaveCount(0);
        } finally {
            // Other cases count this run's tickets.
            php(`\\FluentSupport\\App\\Models\\Ticket::find(${Number(ticketId)})->delete(); echo '[]';`);
        }
    });

    test('clicking a row opens the ticket', async ({ page }) => {
        await searchMarker(page);
        const loaded = api(page, 'GET', `tickets/${fx.ticket_view}`);
        await rows(page).filter({ hasText: `View ticket ${fx.marker}` }).locator('.fs_ticket_description').click();
        await loaded;
        await expect(page).toHaveURL(new RegExp(`#/tickets/${fx.ticket_view}/view`));
        await expect(page.locator('.fs_view_ticket .fs_ticket_title')).toHaveText(`View ticket ${fx.marker}`);
    });

    test('bulk change priority on the selected tickets', async ({ page }) => {
        await searchMarker(page);
        for (const key of ['new', 'new2']) {
            await rows(page).filter({ hasText: titles(fx, [key])[0] }).locator('.el-checkbox').click();
        }
        await expect(page.locator('.fs_select_all_section')).toContainText('2 out of 5 tickets');

        const bar = page.locator('.fs_bb_bar');
        await expect(bar).toBeVisible();
        await bar.locator('.fs_bb_btn', { hasText: 'More' }).click();
        await page.locator('.fs_bb_panel .fs_bb_menu_item', { hasText: /priority/i }).first().click();
        await page.locator('.fs_bb_panel').getByRole('radio', { name: 'Critical' }).click();

        const done = api(page, 'POST', 'tickets/bulk-actions');
        await page.locator('.fs_bb_panel').getByRole('button', { name: 'Set 2 tickets to Critical priority' }).click();
        await done;

        await expect.poll(() => [ticket(fx.ticket_new).priority, ticket(fx.ticket_new2).priority]).toEqual(['critical', 'critical']);
        expect(ticket(fx.ticket_critical).priority).toBe('critical');
        expect(ticket(fx.ticket_view).priority).toBe('normal');
    });
});
