// @vitest-environment jsdom
// Mounts the real ticket sidebar, so a raw v-html on any widget field fails here.

import { describe, it, expect, beforeAll, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import createDOMPurify from 'dompurify';

const HIT = 'window.__fsXss=(window.__fsXss||0)+1';
// Each sink gets its own marker, so a missing wrap names the field that broke.
const payload = (id) => '<img src="x" onerror="' + HIT + '"><b id="ok-' + id + '">ok</b>';

vi.mock('@/admin/Bits/Rest.js', () => ({ default: { get: () => Promise.resolve({}) } }));
vi.mock('@/admin/Modules/Tickets/parts/_Tags.vue', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Modules/Tickets/parts/_TaskTimer.vue', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Modules/Tickets/parts/_CrmProfile', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Modules/Tickets/_FluentBookingSidebarWidget.vue', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Modules/Customers/_CustomerForm', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Pieces/RemoteSelector', () => ({ default: { render: () => null } }));
vi.mock('@/admin/Bits/DynamicTemplates/DynamicTemplates.vue', () => ({ default: { render: () => null } }));

const response = {
    other_tickets: [],
    other_tickets_total: 0,
    other_tickets_more: false,
    extra_widgets: {
        widget: { title: 'Widget', body_html: payload('body') },
        fluent_cart_purchases: {
            title: 'FluentCart Purchases',
            stats: [{ label: 'Lifetime Value', value: payload('ltv') }],
            sections: [{ label: 'Recent Purchases', items: [{ title: payload('item'), subtitle: payload('sub'), badge: { text: payload('badge'), type: 'success' } }] }],
        },
        // The pre-2.4.5 Pro WooCommerce shape: nothing to draw, so it is skipped.
        woo_purchases: { header: 'Legacy Purchases', orders: [{ id: 9, status: 'completed' }], products: {} },
    },
};

const pending = () => ({
    then(cb) { cb(response); return pending(); },
    catch() { return pending(); },
    always() { return pending(); },
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

describe('ticket sidebar sanitizes third-party widget HTML', () => {
    let el;

    beforeAll(async () => {
        window.DOMPurify = createDOMPurify(window);
        window.__fsXss = 0;

        const { default: TicketSidebar } = await import('@/admin/Modules/Tickets/_TicketSidebar.vue');
        el = document.createElement('div');
        document.body.appendChild(el);

        const app = createApp(TicketSidebar, {
            ticket_id: 1,
            ticket: { id: 1, customer: {}, watchers: [] },
            watchers: [],
            watcher_ids: [],
            can_manage_tickets: true,
        });
        app.config.warnHandler = () => {};
        app.config.globalProperties.$get = () => pending();
        app.config.globalProperties.$t = (s) => s;
        app.config.globalProperties.$dayjs = () => ({ format: () => '' });
        app.config.globalProperties.$handleError = () => {};
        app.config.globalProperties.dateTimeFormat = () => '';
        app.config.globalProperties.appVars = {
            me: { permissions: [] },
            support_agents: [],
            client_priorities: [],
            admin_priorities: [],
        };
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
