// @vitest-environment jsdom
// Ticket sidebar: moving to another ticket aborts the previous widgets request. The aborted request
// settles later and must not clear the new request's loading state or handle (Opus review).

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('@/admin/Modules/Customers/_CustomerForm', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Modules/Tickets/parts/_CrmProfile', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Bits/DynamicTemplates/DynamicTemplates.vue', () => ({ default: { render: () => null } }));

class FakeXhr {
    constructor() { FakeXhr.sent.push(this); this.headers = {}; }
    open(method, url) { this.method = method; this.url = url; }
    setRequestHeader(k, v) { this.headers[k] = v; }
    getResponseHeader() { return null; }
    send() {}
    abort() { this.onabort && this.onabort(); }
    respond(status, body) { this.status = status; this.responseText = JSON.stringify(body); this.onload(); }
}
FakeXhr.sent = [];

const flush = () => new Promise(resolve => setTimeout(resolve, 0));

describe('_TicketSidebar widgets requests', () => {
    let Sidebar, Rest, realXhr;

    beforeEach(async () => {
        FakeXhr.sent = [];
        realXhr = window.XMLHttpRequest;
        window.XMLHttpRequest = FakeXhr;
        window.fluentSupportAdmin = { rest: { url: 'https://site.test/wp-json/fluent-support/v2', nonce: 'n' } };
        Rest = (await import('@/admin/Bits/Rest.js')).default;
        Sidebar = (await import('@/admin/Modules/Tickets/_TicketSidebar.vue')).default;
    });
    afterEach(() => {
        window.XMLHttpRequest = realXhr;
        delete window.fluentSupportAdmin;
    });

    const instance = () => {
        const ctx = {
            ticket_id: 1,
            loading: false,
            loadingMoreTickets: false,
            otherTicketsMore: true,
            otherTicketsPage: 1,
            other_tickets: [],
            extra_widgets: null,
            widgetsRequest: null,
            loadMoreRequest: null,
            watcherIds: [],
            $get: Rest.get,
            $handleError: vi.fn()
        };
        Object.keys(Sidebar.methods).forEach(name => { ctx[name] = Sidebar.methods[name].bind(ctx); });
        return ctx;
    };

    it('keeps the new request\'s state when the old one is aborted', async () => {
        const ctx = instance();
        ctx.fetchWidgets();
        const first = ctx.widgetsRequest;

        ctx.ticket_id = 2;
        Sidebar.watch.ticket_id.call(ctx);
        const second = ctx.widgetsRequest;
        expect(second).not.toBe(first);

        await flush(); // the aborted request's callbacks run here
        expect(ctx.widgetsRequest).toBe(second);
        expect(ctx.loading).toBe(true);
        // only the aborted request reached $handleError, which ignores aborted requests
        expect(ctx.$handleError.mock.calls.map(([e]) => e.aborted)).toEqual([true]);

        FakeXhr.sent[1].respond(200, { other_tickets: [{ id: 9 }], other_tickets_total: 1, other_tickets_more: false, extra_widgets: [] });
        await flush();
        expect(ctx.loading).toBe(false);
        expect(ctx.widgetsRequest).toBeNull();
        expect(ctx.other_tickets).toEqual([{ id: 9 }]);
    });

    it('a superseded request that still answers does not overwrite the current ticket\'s data', async () => {
        const ctx = instance();
        ctx.fetchWidgets();
        ctx.fetchWidgets(); // fetch_other_tickets refetches without aborting
        FakeXhr.sent[1].respond(200, { other_tickets: [{ id: 2 }], other_tickets_total: 1, other_tickets_more: false });
        FakeXhr.sent[0].respond(200, { other_tickets: [{ id: 1 }], other_tickets_total: 1, other_tickets_more: false });
        await flush();
        expect(ctx.other_tickets).toEqual([{ id: 2 }]);
        expect(ctx.loading).toBe(false);
    });

    it('moving ticket during "load more" does not leave it stuck', async () => {
        const ctx = instance();
        ctx.fetchMoreOtherTickets();
        expect(ctx.loadingMoreTickets).toBe(true);
        ctx.ticket_id = 2;
        Sidebar.watch.ticket_id.call(ctx);
        await flush();
        expect(ctx.loadingMoreTickets).toBe(false);
        expect(ctx.loadMoreRequest).toBeNull();
    });
});
