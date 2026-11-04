// @vitest-environment jsdom
// Customers screens: when two list requests overlap (a tab switch, a search, a page change),
// only the latest may update the rows, totals, errors and loading state.

import { describe, it, expect, vi } from 'vitest';

vi.mock('@/admin/Modules/Customers/_CustomerForm', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Modules/Tickets/parts/_CrmProfile', () => ({ default: { render: () => null } }));

// A jQuery-style request the test settles by hand, in any order.
const deferred = () => {
    const cbs = { then: [], catch: [], always: [] };
    const req = {
        then(cb) { cbs.then.push(cb); return req; },
        catch(cb) { cbs.catch.push(cb); return req; },
        always(cb) { cbs.always.push(cb); return req; },
        resolve(data) { cbs.then.forEach(cb => cb(data)); cbs.always.forEach(cb => cb()); },
        reject(err) { cbs.catch.forEach(cb => cb(err)); cbs.always.forEach(cb => cb()); },
    };
    return req;
};

// A plain object standing in for the component instance.
const instance = (component, props = {}, extra = {}) => {
    const requests = [];
    const ctx = {
        ...props,
        $t: (s) => s,
        $handleError: vi.fn(),
        $get: vi.fn(() => { const r = deferred(); requests.push(r); return r; }),
        $router: { replace: () => ({ catch: () => {} }) },
        $route: { query: {} },
        $getData: (key, fallback) => fallback,
        appVars: { rest: { url: '/rest', nonce: 'n' }, me: { permissions: [] } },
        ...extra,
    };
    Object.assign(ctx, component.data.call(ctx));
    Object.keys(component.methods).forEach((name) => {
        ctx[name] = component.methods[name].bind(ctx);
    });
    return { ctx, requests };
};

describe('customer profile tickets', () => {
    it('keeps the newer tab when an older request answers last', async () => {
        const { default: CustomerPage } = await import('@/admin/Modules/Customers/CustomerPage.vue');
        const { ctx, requests } = instance(CustomerPage, { customer_id: 7 });

        ctx.setStatusTab('open');
        ctx.setStatusTab('closed');

        requests[1].resolve({ tickets: { data: [{ id: 2, status: 'closed' }], total: 1 } });
        expect(ctx.loadingTickets).toBe(false);

        // The Open response arrives late: it must not replace the Closed rows.
        requests[0].resolve({ tickets: { data: [{ id: 1, status: 'new' }], total: 9 } });
        expect(ctx.tickets.map(t => t.id)).toEqual([2]);
        expect(ctx.ticketPagination.total).toBe(1);
    });

    it('an older request finishing early does not hide the newer one\'s loading state', async () => {
        const { default: CustomerPage } = await import('@/admin/Modules/Customers/CustomerPage.vue');
        const { ctx, requests } = instance(CustomerPage, { customer_id: 7 });

        ctx.setStatusTab('open');
        ctx.setStatusTab('closed');

        requests[0].reject(new Error('aborted'));
        expect(ctx.loadingTickets).toBe(true);
        expect(ctx.$handleError).not.toHaveBeenCalled();
    });
});

describe('customer list', () => {
    it('keeps the newer view when an older request answers last', async () => {
        const { default: Customers } = await import('@/admin/Modules/Customers/Customers.vue');
        const { ctx, requests } = instance(Customers, {}, { clearSelection: () => {} });
        ctx.clearSelection = () => {};

        ctx.setView('active');
        ctx.setView('inactive');

        requests[1].resolve({ customers: { data: [{ id: 20 }], total: 1 } });
        requests[0].resolve({ customers: { data: [{ id: 10 }, { id: 11 }], total: 2 }, search_meta: { mode: 'words' } });

        expect(ctx.customers.map(c => c.id)).toEqual([20]);
        expect(ctx.pagination.total).toBe(1);
        expect(ctx.searchMeta).toBeNull();
        expect(ctx.loading).toBe(false);
    });
});
