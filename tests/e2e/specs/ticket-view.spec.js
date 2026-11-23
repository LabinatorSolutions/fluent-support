// The single ticket screen (ViewTicket.vue): the thread, replying and notes,
// closing and reopening, the title, agent, inbox and priority controls, editing
// and deleting a reply, stepping to the next ticket and deleting the ticket.
// Every change is checked in the database, not only on screen.
import { test, expect } from '@playwright/test';
import { adminPage, api, fixtures, openTicket, openTicketList, rows, searchMarker, typeInComposer, watchErrors } from '../lib/app.js';
import { conversations, mailSent, php, reseed, ticket } from '../lib/lab.js';

test.describe('ticket view', () => {
    let fx;
    let checkErrors;

    test.beforeAll(() => reseed());

    test.beforeEach(async ({ page }) => {
        fx = fixtures();
        checkErrors = watchErrors(page);
    });

    test.afterEach(() => checkErrors());

    const thread = page => page.locator('.fs_threads_container article.fs_conversation_message');

    test('shows the ticket, its thread and the customer', async ({ page }) => {
        await openTicket(page, fx.ticket_view);

        await expect(page.locator('.fs_view_ticket .fs_ticket_title')).toHaveText(`View ticket ${fx.marker}`);
        await expect(page.locator('.fs_info_container .fs_status_badge')).toHaveText('Active');

        // Newest first; the opening message is last.
        await expect(thread(page)).toHaveCount(4);
        await expect(thread(page).nth(0)).toContainText(`Internal note ${fx.marker}`);
        await expect(thread(page).nth(0).locator('.fs_label_internal_info')).toHaveText('Added Note');
        await expect(thread(page).nth(1)).toContainText(`Agent answer ${fx.marker}`);
        await expect(thread(page).nth(2)).toContainText(`Customer follow-up ${fx.marker}`);
        await expect(thread(page).nth(2).locator('.from_info')).toContainText(`carla-${fx.marker}@example.test`);
        await expect(thread(page).nth(3)).toHaveClass(/fs_thread_starter/);
        await expect(thread(page).nth(3)).toContainText('Opening message of the view ticket.');

        await expect(page.locator('.fs_header_right_group')).toContainText(`E2E Box ${fx.marker}`);
        await expect(page.locator('.fs_header_right_group')).toContainText(`E2E Product ${fx.marker}`);
        await expect(page.locator('.fs_ticket_sidebar')).toContainText(`carla-${fx.marker}@example.test`);
        await expect(page.locator('.fs_ticket_sidebar')).toContainText(`Billing ${fx.marker}`);
        await expect(page.locator('#fs_top_bar_actions_slot')).toContainText('Lab Agent');
    });

    test('a reply goes to the top of the thread, is saved and emails the customer', async ({ page }) => {
        await openTicket(page, fx.ticket_view);
        const mailBefore = mailSent().length;

        await page.locator('.fs_composer_prompt_reply').click();
        await typeInComposer(page, `Replay from the browser ${fx.marker}`);
        const sent = api(page, 'POST', `tickets/${fx.ticket_view}/responses`);
        await page.locator('.fs_create_response .fs_reply_btn:not(.fs_reply_split_toggle)').click();
        await sent;

        await expect(page.locator('.fs_create_response .fs_reply_btn:not(.fs_reply_split_toggle)')).toHaveCount(0);
        await expect(thread(page).first()).toContainText(`Replay from the browser ${fx.marker}`);
        const saved = conversations(fx.ticket_view).pop();
        expect([saved.conversation_type, Number(saved.person_id)]).toEqual(['response', fx.admin_agent]);
        expect(saved.content).toContain(`Replay from the browser ${fx.marker}`);
        await expect.poll(() => mailSent().slice(mailBefore).map(mail => mail.to).flat().join(',')).toContain(`carla-${fx.marker}@example.test`);
    });

    test('switching between Reply and Notes keeps the text, and it sends as the open mode', async ({ page }) => {
        await openTicket(page, fx.ticket_view);
        const header = page.locator('.fs_action_group');
        const editorBody = page.frameLocator('.fs_create_response iframe').locator('body');

        await page.locator('.fs_composer_prompt_reply').click();
        await typeInComposer(page, `Switch text ${fx.marker}`);

        await header.getByRole('button', { name: 'Notes', exact: true }).click();
        await expect(page.locator('.fs_note_warning')).toBeVisible();
        await expect(editorBody).toContainText(`Switch text ${fx.marker}`);

        await header.getByRole('button', { name: 'Reply', exact: true }).click();
        await expect(page.locator('.fs_note_warning')).toHaveCount(0);
        await expect(editorBody).toContainText(`Switch text ${fx.marker}`);

        await header.getByRole('button', { name: 'Notes', exact: true }).click();
        const sent = api(page, 'POST', `tickets/${fx.ticket_view}/responses`);
        await page.locator('.fs_create_response .fs_reply_btn:not(.fs_reply_split_toggle)').click();
        await sent;

        const saved = conversations(fx.ticket_view).pop();
        expect(saved.conversation_type).toBe('note');
        expect(saved.content).toContain(`Switch text ${fx.marker}`);
    });

    test('a saved draft opens in Notes too, so switching to Reply never overwrites it', async ({ page }) => {
        const ticketId = Number(fx.ticket_view);
        const key = `ticket_no_${ticketId}_agent_id_${Number(fx.admin_agent)}_response_draft`;
        php(`$s = \\FluentSupport\\App\\Services\\Helper::getOption('global_business_settings', []);
            update_option('fs_e2e_draft_mode_was', isset($s['enable_draft_mode']) ? $s['enable_draft_mode'] : 'no');
            $s['enable_draft_mode'] = 'yes';
            \\FluentSupport\\App\\Services\\Helper::updateOption('global_business_settings', $s);
            \\FluentSupport\\App\\Models\\Meta::where('key', '${key}')->delete();
            \\FluentSupport\\App\\Models\\Meta::insert(['object_type' => '_fs_auto_draft', 'object_id' => ${ticketId}, 'key' => '${key}',
                'value' => maybe_serialize(['content' => '<p>Saved draft ${fx.marker}</p>', 'conversation_type' => 'response'])]);
            echo '{}';`);

        try {
            await openTicket(page, fx.ticket_view);
            const editorBody = page.frameLocator('.fs_create_response iframe').locator('body');

            await page.locator('.fs_composer_prompt_note').click();
            await expect(editorBody).toContainText(`Saved draft ${fx.marker}`);

            await page.locator('.fs_action_group').getByRole('button', { name: 'Reply', exact: true }).click();
            await expect(editorBody).toContainText(`Saved draft ${fx.marker}`);
        } finally {
            php(`$s = \\FluentSupport\\App\\Services\\Helper::getOption('global_business_settings', []);
                $s['enable_draft_mode'] = get_option('fs_e2e_draft_mode_was', 'no');
                \\FluentSupport\\App\\Services\\Helper::updateOption('global_business_settings', $s);
                delete_option('fs_e2e_draft_mode_was');
                \\FluentSupport\\App\\Models\\Meta::where('key', '${key}')->delete();
                echo '{}';`);
        }
    });

    test('a note is saved as a note and emails nobody outside the team', async ({ page }) => {
        await openTicket(page, fx.ticket_view);
        const mailBefore = mailSent().length;

        await page.locator('.fs_composer_prompt_note').click();
        await typeInComposer(page, `Browser note ${fx.marker}`);
        const sent = api(page, 'POST', `tickets/${fx.ticket_view}/responses`);
        await page.locator('.fs_create_response .fs_reply_btn:not(.fs_reply_split_toggle)').click();
        await sent;

        await expect(thread(page).first()).toContainText(`Browser note ${fx.marker}`);
        await expect(thread(page).first().locator('.fs_label_internal_info')).toHaveText('Added Note');
        expect(conversations(fx.ticket_view).pop().conversation_type).toBe('note');
        expect(mailSent().slice(mailBefore).map(mail => mail.to).flat().join(',')).not.toContain(`carla-${fx.marker}`);
    });

    test('Reply & Close sends the reply and closes; Reopen opens it again', async ({ page }) => {
        await openTicket(page, fx.ticket_new2);

        await page.locator('.fs_composer_prompt_reply').click();
        await typeInComposer(page, `Closing reply ${fx.marker}`);
        const sent = api(page, 'POST', `tickets/${fx.ticket_new2}/responses`);
        await page.locator('.fs_create_response .fs_reply_close_btn').click();
        await sent;

        await expect(page.locator('.fs_create_response_closed')).toContainText('Closed Ticket');
        await expect(page.locator('.fs_info_container .fs_status_badge')).toHaveText('Closed');
        expect(ticket(fx.ticket_new2).status).toBe('closed');

        const reopened = api(page, 'POST', `tickets/${fx.ticket_new2}/re-open`);
        await page.locator('.fs_create_response_closed').getByRole('button', { name: 'Reopen This ticket' }).click();
        await reopened;
        await expect(page.locator('.fs_create_response_closed')).toHaveCount(0);
        expect(ticket(fx.ticket_new2).status).toBe('active');
    });

    test('the top bar closes and reopens the ticket', async ({ page }) => {
        await openTicket(page, fx.ticket_critical);
        const button = page.locator('.fs_close_ticket_btn');

        const closed = api(page, 'POST', `tickets/${fx.ticket_critical}/close`);
        await button.click();
        await closed;
        await expect(button).toContainText('Re-open');
        expect(ticket(fx.ticket_critical).status).toBe('closed');

        const reopened = api(page, 'POST', `tickets/${fx.ticket_critical}/re-open`);
        await button.click();
        await reopened;
        await expect(button).toContainText('Close Ticket');
        expect(ticket(fx.ticket_critical).status).toBe('active');
    });

    test('the title is edited in place', async ({ page }) => {
        await openTicket(page, fx.ticket_new);
        await page.locator('.fs_view_ticket .fs_ticket_title span').first().click();
        const input = page.locator('.el-popover .fs_text_input input').first();
        await input.fill(`Renamed in the browser ${fx.marker}`);
        const saved = api(page, 'PUT', `tickets/${fx.ticket_new}/property`);
        await input.press('Enter');
        await saved;
        expect(ticket(fx.ticket_new).title).toBe(`Renamed in the browser ${fx.marker}`);
        await expect(page.locator('.fs_view_ticket .fs_ticket_title')).toHaveText(`Renamed in the browser ${fx.marker}`);
    });

    test('an agent is assigned from the top bar', async ({ page }) => {
        await openTicket(page, fx.ticket_new);
        await page.locator('.fs_agent_selector_tooltip .el-button').click();
        const saved = api(page, 'PUT', `tickets/${fx.ticket_new}/property`);
        await page.locator('.fs_agent_group_popper .el-select-dropdown__item', { hasText: 'Ava Agent' }).click();
        await saved;
        expect(Number(ticket(fx.ticket_new).agent_id)).toBe(fx.agent);
        await expect(page.locator('#fs_top_bar_actions_slot')).toContainText('Ava Agent');
    });

    test('the inbox is changed from the header', async ({ page }) => {
        await openTicket(page, fx.ticket_new);
        await page.locator('.fs_header_right_group .el-button', { hasText: `E2E Box ${fx.marker}` }).click();
        const moved = api(page, 'PUT', `mailboxes/${fx.mailbox}/move_tickets`);
        await page.locator('.el-dropdown-menu__item', { hasText: `E2E Second Box ${fx.marker}` }).click();
        await moved;
        expect(Number(ticket(fx.ticket_new).mailbox_id)).toBe(fx.mailbox2);
        await expect(page.locator('.fs_header_right_group')).toContainText(`E2E Second Box ${fx.marker}`);
    });

    test('the agent priority is changed in the sidebar', async ({ page }) => {
        await openTicket(page, fx.ticket_new);
        const field = page.locator('.fs_ticket_sidebar .fs_detail_label', { hasText: 'Agent Priority' }).locator('..');
        await field.locator('.el-select').click();
        const saved = api(page, 'PUT', `tickets/${fx.ticket_new}/property`);
        await page.locator('.el-select-dropdown__item:visible', { hasText: 'Critical' }).click();
        await saved;
        expect(ticket(fx.ticket_new).priority).toBe('critical');
    });

    test('a reply is edited from its menu', async ({ page }) => {
        await openTicket(page, fx.ticket_view);
        await thread(page).filter({ hasText: `Agent answer ${fx.marker}` }).locator('.fs_more_btn').click();
        await page.locator('.el-dropdown-menu__item:visible', { hasText: 'Edit' }).click();

        const dialog = page.getByRole('dialog', { name: 'Edit Response' });
        await expect(dialog).toBeVisible();
        const body = dialog.frameLocator('iframe').locator('body');
        await body.click();
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.type(`Edited answer ${fx.marker}`);
        const saved = api(page, 'PUT', `tickets/${fx.ticket_view}/responses/${fx.reply_agent}`);
        await dialog.getByRole('button', { name: 'Update Response' }).click();
        await saved;

        await expect(dialog).toBeHidden();
        await expect(thread(page).filter({ hasText: `Edited answer ${fx.marker}` })).toHaveCount(1);
        expect(conversations(fx.ticket_view).find(row => Number(row.id) === fx.reply_agent).content).toContain(`Edited answer ${fx.marker}`);
    });

    test('a reply is deleted from its menu after confirming', async ({ page }) => {
        await openTicket(page, fx.ticket_view);
        await thread(page).filter({ hasText: `Customer follow-up ${fx.marker}` }).locator('.fs_more_btn').click();
        await page.locator('.el-dropdown-menu__item:visible', { hasText: 'Delete' }).click();
        const deleted = api(page, 'DELETE', `tickets/${fx.ticket_view}/responses/${fx.reply_customer}`);
        await page.getByRole('button', { name: 'Delete Response' }).click();
        await deleted;

        await expect(thread(page).filter({ hasText: `Customer follow-up ${fx.marker}` })).toHaveCount(0);
        expect(conversations(fx.ticket_view).some(row => Number(row.id) === fx.reply_customer)).toBe(false);
    });

    test('opened from the list, Next steps to the next ticket in it', async ({ page }) => {
        await openTicketList(page);
        await searchMarker(page);
        const listed = await rows(page).locator('.fs_ticket_title').evaluateAll(links => links.map(link => link.textContent.trim()));
        const ids = await rows(page).locator('.fs_tk_number').evaluateAll(spans => spans.map(span => span.textContent.trim().replace('#', '')));

        await rows(page).first().locator('.fs_ticket_description').click();
        await expect(page).toHaveURL(new RegExp(`#/tickets/${ids[0]}/view`));
        await expect(page.locator('.fs_ticket_position_text')).toHaveText(`1 of ${listed.length}`);

        const next = api(page, 'GET', `tickets/${ids[1]}`);
        await page.getByRole('button', { name: 'Next ticket' }).click();
        await next;
        await expect(page).toHaveURL(new RegExp(`#/tickets/${ids[1]}/view`));
        await expect(page.locator('.fs_view_ticket .fs_ticket_title')).toHaveText(listed[1]);
        await expect(page.locator('.fs_ticket_position_text')).toHaveText(`2 of ${listed.length}`);
    });

    test('the ticket is deleted from More Actions after confirming', async ({ page }) => {
        await openTicket(page, fx.ticket_other_box);
        await page.locator('#fs_top_bar_actions_slot .fs_more_action .el-button').click();
        await page.locator('.el-dropdown-menu__item:visible', { hasText: 'Delete Ticket' }).click();
        const deleted = api(page, 'DELETE', `tickets/${fx.ticket_other_box}/delete`);
        await page.locator('.el-message-box').getByRole('button', { name: 'Delete Ticket' }).click();
        await deleted;

        await expect(page).toHaveURL(new RegExp(`${adminPage.replace(/[?]/g, '\\?')}#/tickets$`));
        expect(php(`echo wp_json_encode([\\FluentSupport\\App\\Models\\Ticket::find(${fx.ticket_other_box}) ? 'found' : 'gone']);`)).toEqual(['gone']);
    });
});
