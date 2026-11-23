// @vitest-environment jsdom
// Patchstack 40379: a route param from the page URL must not turn the page's own REST call
// into a request to another REST route (or another method). Ported from FluentCommunity
// tests/unit/bits/rest-route-guard.spec.js (PR #1440).

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import { isSafeRestUrl, phpQueryKey } from '@/common/restUrlGuard.js';

vi.mock('@/customer_portal/components/Dashboard', () => ({ default: { render: () => null } }));
vi.mock('@/customer_portal/components/Ticket', () => ({ default: { render: () => null } }));
vi.mock('@/customer_portal/components/CreateTicket', () => ({ default: { render: () => null } }));

const pretty = 'https://site.test/wp-json/fluent-support/v2';
const plain = 'https://site.test/index.php?rest_route=/fluent-support/v2';

// The param the Patchstack link puts into #/customers/:customer_id, decoded as vue-router decodes it
const PATCHSTACK_PARAM = '1?rest_route=/wp/v2/users&rest_route=/wp/v2/users&_method=POST&username=x&roles=administrator&x=';
const PATCHSTACK_PATH = '/customers/1%3Frest_route%3D%2Fwp%2Fv2%2Fusers%26rest_route%3D%2Fwp%2Fv2%2Fusers%26_method%3DPOST%26username%3Dx%26email%3Dx%2540x.test%26password%3Dx%26roles%3Dadministrator%26x%3D';

describe.each([
    ['pretty permalinks', pretty, [
        '../../wp/v2/users',
        '../x',
        '%2e%2e/%2e%2e/wp/v2/users',
        // (a) "../" escape ending in "&" instead of "#"
        'tickets/5/../../../wp/v2/users?_method=POST&roles=administrator&',
        'tickets/5/../../../wp/v2/users?x=1&',
        // (c) no "../": WordPress lets $_GET rest_route win over the permalink
        'customers/1?rest_route=/wp/v2/types/post',
        'customers/1?x=1&rest_route=/wp/v2/users',
        // PHP reads a key whose "[" has no "]" as rest_route (Opus and Codex review)
        'customers/1?rest[route=/wp/v2/users',
        'customers/1?x=1&rest%5Broute=/wp/v2/users&x=',
        'customers/1?x=1&%20rest[route=/wp/v2/users'
    ]],
    ['plain permalinks', plain, [
        'x&rest_route=/fluent-support/v2/tickets/5',
        'x&rest.route=/fluent-support/v2/tickets/5',
        'customers/1&x=1&rest[route=/wp/v2/users',
        'customers/1&x=1&rest%5Broute=/wp/v2/users&x=',
        'customers/1&x=1&%20rest[route=/wp/v2/users',
        '../../wp/v2/users',
        // (b) a second rest_route: PHP keeps the last duplicate key
        '1?x=1&rest_route=/wp/v2/users&_method=POST&roles=administrator&x=',
        '1?x=1&rest_route=/wp/v2/users&x=',
        `customers/${PATCHSTACK_PARAM}`
    ]]
])('isSafeRestUrl with %s', (label, base, unsafeForBase) => {
    const sep = base === plain ? '&' : '?';

    it.each([
        'tickets',
        'tickets/5',
        'customers/12',
        `customers/${encodeURIComponent('a/b?c&d#e')}`,
        `tickets${sep}page=2&filters%5Bstatus%5D=open&query_timestamp=1`
    ])('allows %s', (route) => {
        expect(isSafeRestUrl(`${base}/${route}`, base)).toBe(true);
    });

    it.each([
        ...unsafeForBase,
        `5${sep}_method=DELETE`,
        `5${sep}%5Fmethod=DELETE`,
        `5${sep}_method[]=DELETE`,
        `5${sep}.method=DELETE`,
        `5${sep}%20_method=DELETE`,
        `5${sep}_method%00=DELETE`,
        `5${sep}_jsonp=x`,
        `5${sep}_envelope=1`,
        '5#x',
        '5\\x'
    ])('rejects %s', (route) => {
        expect(isSafeRestUrl(`${base}/${route}`, base)).toBe(false);
    });

    it('rejects another origin', () => {
        expect(isSafeRestUrl('https://evil.test/wp-json/fluent-support/v2/tickets', base)).toBe(false);
    });
});

// Each pair is [query key as sent, the $_GET name PHP 8.2 and 8.4 parse_str give it]. The full check of 4,000
// generated keys against PHP found no difference; these are samples from it.
describe('phpQueryKey reads keys the way PHP does', () => {
    it.each([
        ['rest_route', 'rest_route'],
        ['rest.route', 'rest_route'],
        ['rest route', 'rest_route'],
        [' _method', '_method'],
        ['_method\0x', '_method'],
        ['_method[]', '_method'],
        ['rest.route[a]', 'rest_route'],
        ['rest[route', 'rest_route'],
        [' rest[route', 'rest_route'],
        ['rest[ro.ute', 'rest_ro_ute'],
        ['a[b[c', 'a_b_c'],
        ['x[ [y', 'x___y'],
        ['a]b[c', 'a]b_c'],
        ['x[a]b[', 'x'],
        ['_method[', '_method_'],
        ['rest_route[', 'rest_route_']
    ])('%j is %j', (key, php) => {
        expect(phpQueryKey(key)).toBe(php);
    });
});

// The Client plugin keeps a copy of the guard; it must not drift
describe('fluent-support-client copy', async () => {
    const { existsSync, readFileSync } = await import('node:fs');
    const { resolve } = await import('node:path');
    const ours = resolve(__dirname, '../../../resources/common/restUrlGuard.js');
    const client = resolve(__dirname, '../../../../fluent-support-client/src/common/restUrlGuard.js');
    it.skipIf(!existsSync(client))('is identical to resources/common/restUrlGuard.js', () => {
        expect(readFileSync(client, 'utf8')).toBe(readFileSync(ours, 'utf8'));
    });
});

// A stand-in XMLHttpRequest: records what would be sent, and the test answers it by hand
class FakeXhr {
    constructor() {
        FakeXhr.sent.push(this);
        this.headers = {};
        this.responseHeaders = {};
    }
    open(method, url) { this.method = method; this.url = url; }
    setRequestHeader(key, value) { this.headers[key] = value; }
    getResponseHeader(key) { return this.responseHeaders[key] || null; }
    send(body) { this.body = body === undefined ? null : body; }
    abort() { this.onabort && this.onabort(); }
    respond(status, text, headers = {}) {
        this.status = status;
        this.statusText = '';
        this.responseText = text;
        this.responseHeaders = headers;
        this.onload();
    }
}
FakeXhr.sent = [];

// The ticket view request as the agent app sends it on mount, with a crafted ticket_id
describe('agent helper (resources/admin/Bits/Rest.js)', () => {
    let Rest, realXhr;

    beforeEach(async () => {
        vi.resetModules();
        FakeXhr.sent = [];
        realXhr = window.XMLHttpRequest;
        window.XMLHttpRequest = FakeXhr;
        Rest = (await import('@/admin/Bits/Rest.js')).default;
    });
    afterEach(() => {
        window.XMLHttpRequest = realXhr;
        delete window.fluentSupportAdmin;
    });

    it('does not use jQuery', async () => {
        const { readFileSync } = await import('node:fs');
        const { resolve } = await import('node:path');
        const source = readFileSync(resolve(__dirname, '../../../resources/admin/Bits/Rest.js'), 'utf8');
        expect(source).not.toMatch(/window\.jQuery|jQuery\.(ajax|post|get|Deferred)|\$\.(ajax|post|get)\(|jQuery\(/);
    });

    describe.each([['pretty', pretty], ['plain', plain]])('%s permalinks', (label, base) => {
        beforeEach(() => {
            window.fluentSupportAdmin = { rest: { url: base, nonce: 'n' }, ajaxurl: '/wp-admin/admin-ajax.php' };
        });

        it.each([
            ['the Patchstack customer link', `customers/${PATCHSTACK_PARAM}`],
            ['a ticket delete link', 'tickets/5/delete?_method=DELETE#/view'],
            ['a "../" escape', 'tickets/../../../wp/v2/users?_method=POST&roles=administrator&'],
            ['a second rest_route', 'tickets/1?x=1&rest_route=/wp/v2/users&_method=POST&x='],
            ['a second rest_route without _method', 'tickets/1?x=1&rest_route=/wp/v2/users&x='],
            ['" _method"', 'tickets/1?x=1&%20_method=DELETE&x='],
            ['"_method%00"', 'tickets/1?x=1&_method%00=DELETE&x=']
        ])('refuses %s without sending it', async (name, route) => {
            const req = Rest.get(route);
            let always = null;
            req.always((e) => { always = e; });
            req.abort(); // _TicketSidebar aborts in-flight requests
            const error = await req.then(() => null, e => e);
            expect(FakeXhr.sent).toHaveLength(0);
            expect(error).toMatchObject({ message: 'Invalid REST route.', code: 'fst_invalid_rest_route' });
            expect(error.responseJSON).toBe(error); // $handleError and Errors.record read it
            expect(always).toBe(error);
        });

        it('refuses a rest_route param with no "../" on pretty; on plain it stays inside the one route value', async () => {
            // Plain: PHP keeps "1?rest_route=..." inside the first rest_route value, and WordPress answers rest_no_route
            Rest.get('tickets/1?rest_route=/wp/v2/users&x=1').catch(() => {});
            expect(FakeXhr.sent).toHaveLength(base === plain ? 1 : 0);
        });

        it('refuses an override param in the query data', async () => {
            await expect(Rest.get('tickets', { _method: 'DELETE' })).rejects.toMatchObject({ code: 'fst_invalid_rest_route' });
            expect(FakeXhr.sent).toHaveLength(0);
        });

        it('sends a GET with the data in the URL, encoded as jQuery.param did', async () => {
            const req = Rest.get('tickets', { page: 2, filters: { status: 'open' }, tags: [1, 2], flag: false, empty: null, none: [] });
            const xhr = FakeXhr.sent[0];
            const sep = base === plain ? '&' : '?';
            expect(xhr.method).toBe('GET');
            expect(xhr.url).toBe(`${base}/tickets${sep}page=2&filters%5Bstatus%5D=open&tags%5B%5D=1&tags%5B%5D=2&flag=false&empty=`);
            expect(xhr.body).toBeNull();
            expect(xhr.headers['X-WP-Nonce']).toBe('n');
            xhr.respond(200, '{"ok":1}', { 'X-WP-Nonce': 'fresh' });
            await expect(req).resolves.toEqual({ ok: 1 });
            expect(window.fluentSupportAdmin.rest.nonce).toBe('fresh');
        });

        it('sends DELETE as POST with the override header and a form-encoded body', async () => {
            Rest.delete('tickets/5/delete', { a: 1, rows: [{ id: 3 }] }).catch(() => {});
            const xhr = FakeXhr.sent[0];
            expect(xhr.url).toBe(`${base}/tickets/5/delete`);
            expect(xhr.method).toBe('POST');
            expect(xhr.headers['X-HTTP-Method-Override']).toBe('DELETE');
            expect(xhr.headers['Content-Type']).toBe('application/x-www-form-urlencoded; charset=UTF-8');
            expect(xhr.body).toBe('a=1&rows%5B0%5D%5Bid%5D=3');
        });
    });

    describe('responses', () => {
        beforeEach(() => {
            window.fluentSupportAdmin = { rest: { url: pretty, nonce: 'n' }, ajaxurl: '/wp-admin/admin-ajax.php' };
        });

        it('never runs a script response; a non-JSON 200 rejects with a message', async () => {
            window.pwned = vi.fn();
            const req = Rest.get('tickets');
            FakeXhr.sent[0].respond(200, '/**/pwned({"a":1})', { 'Content-Type': 'application/javascript' });
            await expect(req).rejects.toMatchObject({ message: 'The server returned an unexpected response.' });
            expect(window.pwned).not.toHaveBeenCalled();
            delete window.pwned;
        });

        it('rejects a WordPress error with its body, readable as responseJSON', async () => {
            const req = Rest.post('tickets/5/close');
            FakeXhr.sent[0].respond(403, '{"code":"rest_forbidden","message":"No"}');
            const error = await req.catch(e => e);
            expect(error).toMatchObject({ code: 'rest_forbidden', message: 'No', xhr_status: 403 });
            expect(error.responseJSON.message).toBe('No');
        });

        it('renews the nonce on rest_cookie_invalid_nonce', async () => {
            const req = Rest.get('tickets');
            FakeXhr.sent[0].respond(403, '{"code":"rest_cookie_invalid_nonce","message":"x"}');
            await req.catch(() => {});
            const renew = FakeXhr.sent[1];
            expect(renew.url).toBe('/wp-admin/admin-ajax.php');
            expect(renew.body).toBe('action=fluent_support_renew_rest_nonce');
            renew.respond(200, '{"nonce":"renewed"}');
            expect(window.fluentSupportAdmin.rest.nonce).toBe('renewed');
        });

        it('runs done/fail/always as jQuery did: synchronously, in order, before then()', async () => {
            const calls = [];
            const req = Rest.get('tickets');
            req.always((r) => calls.push(['always', r]));
            req.then((r) => calls.push(['then', r]));
            req.done((r) => calls.push(['done', r])).fail(() => calls.push(['fail']));
            FakeXhr.sent[0].respond(200, '{"id":1}');
            // done/always ran inside respond(), before any microtask
            expect(calls).toEqual([['always', { id: 1 }], ['done', { id: 1 }]]);
            await req;
            expect(calls).toEqual([['always', { id: 1 }], ['done', { id: 1 }], ['then', { id: 1 }]]);

            // a callback added after the request settled runs at once
            let id;
            req.done((r) => { id = r.id; });
            expect(id).toBe(1);
        });

        it('passes the error to fail/always, on the request and on a chained promise', async () => {
            const calls = [];
            const req = Rest.get('tickets');
            req.fail((e) => calls.push(['fail', e.code])).always((e) => calls.push(['always', e.code])).done(() => calls.push(['done']));
            FakeXhr.sent[0].respond(500, '{"code":"boom"}');
            expect(calls).toEqual([['fail', 'boom'], ['always', 'boom']]);

            const chained = Rest.get('tickets').then(() => {}).fail((e) => calls.push(['chained', e.code]));
            FakeXhr.sent[1].respond(500, '{"code":"boom2"}');
            await chained.catch(() => {});
            expect(calls.at(-1)).toEqual(['chained', 'boom2']);
        });

        it('does not report a failed request as unhandled when done/fail/always is chained', async () => {
            const unhandled = vi.fn();
            process.on('unhandledRejection', unhandled);
            Rest.get('tickets').always(() => {});
            FakeXhr.sent[0].respond(500, '{"code":"boom"}');
            Rest.get('tickets/1?_method=DELETE').always(() => {});
            await new Promise(r => setTimeout(r, 10));
            process.off('unhandledRejection', unhandled);
            expect(unhandled).not.toHaveBeenCalled();
        });

        it('sends the headers jQuery sent', () => {
            Rest.get('tickets');
            expect(FakeXhr.sent[0].headers).toMatchObject({
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': expect.stringContaining('application/json')
            });
        });

        it('abort() on a chained promise aborts the request and settles it quietly', async () => {
            let done = false;
            const chain = Rest.get('tickets/5/widgets').then(() => {}).catch((e) => e).always(() => { done = true; });
            chain.abort();
            await chain;
            await Promise.resolve();
            expect(done).toBe(true);

            const req = Rest.get('tickets');
            req.abort();
            await expect(req).rejects.toMatchObject({ aborted: true });
        });
    });

    it('keeps the route out of the error message, which may be rendered as HTML', async () => {
        window.fluentSupportAdmin = { rest: { url: pretty, nonce: 'n' } };
        const error = await Rest.get('tickets/<img src=x onerror=alert(1)>#/view').then(() => null, e => e);
        expect(error.message).toBe('Invalid REST route.');
        expect(JSON.stringify(error)).not.toContain('<img');
    });
});

describe('portal helper (resources/customer_portal/Rest.js)', () => {
    let Rest, open;

    beforeEach(async () => {
        vi.resetModules();
        open = vi.spyOn(window.XMLHttpRequest.prototype, 'open');
        Rest = (await import('@/customer_portal/Rest.js')).default;
    });
    afterEach(() => {
        open.mockRestore();
        delete window.fs_customer_portal;
    });

    it.each([['pretty', pretty], ['plain', plain]])('refuses crafted routes on %s permalinks without sending them', async (label, base) => {
        window.fs_customer_portal = { rest: { url: base, nonce: 'n' } };
        const invalid = { message: 'Invalid REST route.', code: 'fst_invalid_rest_route' };
        await expect(Rest.get(`customer-portal/tickets/${PATCHSTACK_PARAM}`)).rejects.toEqual(invalid);
        await expect(Rest.get('customer-portal/tickets/1?x=1&rest_route=/wp/v2/users&x=')).rejects.toEqual(invalid);
        await expect(Rest.get('../../wp/v2/users')).rejects.toEqual(invalid);
        await expect(Rest.post('customer-portal/tickets/1?x=1&%20_method=DELETE&x=')).rejects.toEqual(invalid);
        await expect(Rest.uploadFile('../../wp/v2/media')).rejects.toEqual(invalid);
        expect(open).not.toHaveBeenCalled();
    });

    it('does not change the caller\'s data and tolerates a nested null', async () => {
        window.fs_customer_portal = { rest: { url: pretty, nonce: 'n' } };
        const send = vi.spyOn(window.XMLHttpRequest.prototype, 'send').mockImplementation(() => {});
        const data = { filters: { status: null, x: 1 } };
        Rest.get('customer-portal/tickets', data);
        expect(data).toEqual({ filters: { status: null, x: 1 } });
        expect(open.mock.calls[0][1]).toMatch(/^https:\/\/site\.test\/wp-json\/fluent-support\/v2\/customer-portal\/tickets\?filters%5Bx%5D=1&query_timestamp=\d+$/);
        send.mockRestore();
    });
});

// Layer one: a crafted ID does not match the route, so the page never mounts and sends nothing
describe('route params', () => {
    it('agent routes match only numeric IDs', async () => {
        const { default: routes } = await import('@/admin/routes.js');
        const router = createRouter({ history: createMemoryHistory(), routes });
        const crafted = '5%2Fdelete%3F_method%3DDELETE%23';

        expect(router.resolve(PATCHSTACK_PATH).name).not.toBe('view_customer');
        expect(router.resolve(`/tickets/${crafted}/view`).name).not.toBe('view_ticket');
        expect(router.resolve(`/tickets/1%3Frest_route%3D%2Fwp%2Fv2%2Fusers%26x%3D/view`).name).not.toBe('view_ticket');
        expect(router.resolve(`/mailboxes/${crafted}/settings`).name).not.toBe('box_settings');
        expect(router.resolve(`/mailboxes/..%2F..%2Fwp%2Fv2%2Fusers/email_settings`).name).not.toBe('email_settings');
        expect(router.resolve(`/workflows/${crafted}/edit`).name).not.toBe('edit-workflow');

        expect(router.resolve('/customers/12').params.customer_id).toBe('12');
        expect(router.resolve('/tickets/5/view').params.ticket_id).toBe('5');
        expect(router.resolve('/mailboxes/3/settings').params.box_id).toBe('3');
        expect(router.resolve('/workflows/7/edit').params.workflow_id).toBe('7');
    });

    it('portal ticket route matches only a numeric ID', async () => {
        const { default: routes } = await import('@/customer_portal/routes.js');
        const router = createRouter({ history: createMemoryHistory(), routes });
        expect(router.resolve('/ticket/1%3Frest_route%3D%2Fwp%2Fv2%2Fusers%26_method%3DPOST%26x%3D/view').name).not.toBe('view_ticket');
        expect(router.resolve('/ticket/..%2F..%2Fwp%2Fv2%2Fusers%23/view').name).not.toBe('view_ticket');
        expect(router.resolve('/ticket/5/view').params.ticket_id).toBe('5');
        expect(router.resolve('/ticket/create').name).toBe('create_ticket');
    });
});
