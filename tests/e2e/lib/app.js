// Helpers for the Fluent Support admin app in the browser.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';

const dir = path.dirname(fileURLToPath(import.meta.url));

// Ids and the run marker written by global-setup.js, read when a test runs.
export function fixtures() {
    return JSON.parse(fs.readFileSync(path.join(dir, '../.auth/fixtures.json'), 'utf8'));
}

export const adminPage = '/wp-admin/admin.php?page=fluent-support';

// Waits for a Fluent Support REST call to exactly this route. The app sends PUT
// and DELETE as POST with X-HTTP-Method-Override, so match the override too.
export function api(page, method, route) {
    const pattern = new RegExp('fluent-support/v2/' + route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?:[?&]|$)');
    return page.waitForResponse(response => {
        const request = response.request();
        const sent = (request.headers()['x-http-method-override'] || request.method()).toUpperCase();
        return sent === method && pattern.test(decodeURIComponent(response.url()));
    });
}

// Fails the test on a JavaScript error or a console error from the page.
export function watchErrors(page) {
    const errors = [];
    page.on('pageerror', error => errors.push('pageerror: ' + error.message));
    page.on('console', message => {
        if (message.type() === 'error' && !/favicon|Failed to load resource/.test(message.text())) {
            errors.push(message.text());
        }
    });
    return () => expect(errors, 'browser errors').toEqual([]);
}

export async function openTicketList(page) {
    const loaded = api(page, 'GET', 'tickets');
    await page.goto(adminPage + '#/tickets');
    await loaded;
    await expect(page.locator('.fs_tickets_list .fs_ticket_item').first()).toBeVisible();
}

// Searches the list for this run's marker, so only the run's tickets show.
export async function searchMarker(page) {
    await page.locator('.fs_search_button').click();
    const input = page.locator('.fs_table_search_input input');
    await input.fill(fixtures().marker);
    const loaded = api(page, 'GET', 'tickets');
    await input.press('Enter');
    await loaded;
}

export function rows(page) {
    return page.locator('.fs_tickets_list .fs_ticket_item');
}

export function rowTitles(page) {
    return page.locator('.fs_tickets_list .fs_ticket_item .fs_ticket_title').allInnerTexts();
}

export async function openTicket(page, ticketId) {
    const loaded = api(page, 'GET', `tickets/${ticketId}`);
    await page.goto(`${adminPage}#/tickets/${ticketId}/view`);
    await loaded;
    await expect(page.locator('.fs_view_ticket .fs_ticket_title')).toBeVisible();
}

// Types into the open composer's TinyMCE editor like an agent does.
export async function typeInComposer(page, text) {
    const body = page.frameLocator('.fs_create_response iframe').locator('body');
    await body.click();
    await page.keyboard.type(text);
}
