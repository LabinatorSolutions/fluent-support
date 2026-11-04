// @vitest-environment jsdom
// Mounts the real customer page, so a raw v-html on any widget field fails here.

import { describe, it, expect, beforeAll, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import createDOMPurify from 'dompurify';

const HIT = 'window.__fsXss=(window.__fsXss||0)+1';
// Each sink gets its own marker, so a missing wrap names the field that broke.
const payload = (id) => '<img src="x" onerror="' + HIT + '"><b id="ok-' + id + '">ok</b>';

vi.mock('@/admin/Bits/Rest.js', () => ({ default: { get: () => Promise.resolve({}) } }));
vi.mock('@/admin/Modules/Customers/_CustomerForm', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Modules/Tickets/parts/_CrmProfile', () => ({ default: { render: () => null } }));

const response = {
    customer: { id: 1, first_name: 'Ann', last_name: 'Lee', email: 'ann@example.test', status: 'active', tickets_count: 0 },
    tickets: [],
    widgets: {
        widget: { title: 'Widget', body_html: payload('body') },
        woo_purchases: {
            title: 'WooCommerce Purchases',
            stats: [{ label: 'Lifetime Value', value: payload('ltv') }],
            sections: [{ label: 'Recent Purchases', items: [{ title: payload('item'), subtitle: payload('sub') }] }],
        },
        // The pre-2.4.5 Pro WooCommerce shape: nothing to draw, so it is skipped.
        legacy_purchases: { header: 'Legacy Purchases', orders: [{ id: 9, status: 'completed' }], products: {} },
    },
    fluentcrm_profile: null,
};

// The page loads the customer, then its tickets from the ticket list endpoint.
const responses = {
    'customers/1': response,
    tickets: { tickets: { data: [], total: 0 } },
};

const pending = (path) => ({
    then(cb) { cb(responses[path] || {}); return pending(path); },
    catch() { return pending(path); },
    always(cb) { cb && cb(); return pending(path); },
});

const flush = async () => {
    await Promise.resolve();
    await nextTick();
    await nextTick();
};

const expectSanitized = (el, ids) => {
    ids.forEach(id => expect.soft(el.querySelector('#ok-' + id), id + ' rendered').not.toBeNull());
    expect(el.querySelector('[onerror], script')).toBeNull();
    expect(window.__fsXss).toBe(0);
};

describe('customer page sanitizes third-party widget HTML', () => {
    let el;

    beforeAll(async () => {
        window.DOMPurify = createDOMPurify(window);
        window.__fsXss = 0;

        const { default: CustomerPage } = await import('@/admin/Modules/Customers/CustomerPage.vue');
        el = document.createElement('div');
        document.body.appendChild(el);

        const app = createApp(CustomerPage, { customer_id: 1 });
        app.config.warnHandler = () => {};
        app.config.globalProperties.$get = (path) => pending(path);
        app.config.globalProperties.$t = (s) => s;
        app.config.globalProperties.$setTitle = () => {};
        app.config.globalProperties.$timeDiff = () => '';
        app.config.globalProperties.dateTimeFormat = () => '';
        // Element Plus isn't installed here; a bare column would call its row slot without a row.
        app.component('el-table-column', { render: () => null });
        app.config.globalProperties.$handleError = () => {};
        app.config.globalProperties.appVars = { me: { permissions: [] }, support_agents: [], rest: { url: '/rest' } };
        app.mount(el);
        await flush();
    });

    it('sanitizes body_html', () => {
        expectSanitized(el, ['body']);
    });

    it('draws structured widgets as text', () => {
        const widget = el.querySelector('.fs_structured_widget');
        expect(widget).not.toBeNull();
        expect(widget.textContent).toContain('onerror=');
        expect(widget.querySelector('img, b')).toBeNull();
    });

    it('skips a widget with nothing to draw', () => {
        expect(el.textContent).not.toContain('Legacy Purchases');
    });
});
