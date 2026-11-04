// @vitest-environment jsdom
// The ticket bulk bar: what it offers per permission, the request each panel
// sends, and the keyboard and focus rules it shares with FluentCRM's bar.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick, h } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { buildTicketBulkCatalog, buildTicketBulkPayload, countTagCoverage } from '@/admin/Modules/Tickets/BulkActions/bulkActionCatalog';

vi.mock('@/admin/services/ticketListService', () => ({ default: {} }));
vi.mock('@/admin/Modules/Tickets/_CreateResponse', () => ({ default: { render: () => null } }));

const $t = (s, ...args) => args.reduce((out, arg) => out.replace(/%(\d\$)?s/, arg), s);
const $_n = (one, many, n) => (n === 1 ? one : many).replace('%s', n);

const MANAGE = ['fst_manage_other_tickets'];

const appVars = (permissions, extra = {}) => ({
    me: { permissions },
    admin_priorities: { normal: 'Normal', medium: 'Medium', critical: 'Critical' },
    support_products: [{ id: 3, title: 'Plugin' }],
    agent_groups: [],
    support_agents: [{ id: 7, full_name: 'Ana Agent' }],
    ticket_tags: [{ id: 1, title: 'billing' }, { id: 2, title: 'bug' }],
    ...extra,
});

const keys = (catalog) => [
    ...catalog.primary.map(a => a.key),
    ...catalog.groups.flatMap(g => g.items.map(i => i.key)),
    ...(catalog.destructive ? [catalog.destructive.key] : []),
];

describe('bulk action catalog', () => {
    it('hides assign and delete without their capabilities', () => {
        const catalog = buildTicketBulkCatalog({ $t, appVars: appVars(MANAGE) });
        expect(keys(catalog)).toEqual(['reply', 'tags', 'close', 'priority', 'product', 'reopen']);
    });

    it('offers assign, agent groups and delete with the capabilities', () => {
        const vars = appVars([...MANAGE, 'fst_assign_agents', 'fst_delete_tickets'], { agent_groups: [{ id: 4, title: 'Tier 1', agent_ids: [7] }] });
        expect(keys(buildTicketBulkCatalog({ $t, appVars: vars }))).toEqual(['reply', 'assign', 'tags', 'close', 'priority', 'product', 'reopen', 'agent_group', 'delete']);
    });

    it('offers workflows only when there are manual workflows', () => {
        const vars = appVars(MANAGE, { manual_workflows: [{ id: 9, title: 'Escalate' }] });
        expect(keys(buildTicketBulkCatalog({ $t, appVars: vars }))).toContain('workflow');
    });

    it('maps each action to its bulk-actions request', () => {
        expect(buildTicketBulkPayload({ key: 'tags' }, { mode: 'remove', ids: [1] })).toEqual({ bulk_action: 'remove_tags', tag_ids: [1] });
        expect(buildTicketBulkPayload({ key: 'tags' }, { mode: 'add', ids: [1] })).toEqual({ bulk_action: 'assign_tags', tag_ids: [1] });
        expect(buildTicketBulkPayload({ key: 'priority' }, 'critical')).toEqual({ bulk_action: 'change_priority', priority: 'critical' });
        expect(buildTicketBulkPayload({ key: 'product' }, 3)).toEqual({ bulk_action: 'change_product', product_id: 3 });
        expect(buildTicketBulkPayload({ key: 'reopen' })).toEqual({ bulk_action: 'reopen_tickets' });
        expect(buildTicketBulkPayload({ key: 'assign' }, 7)).toEqual({ bulk_action: 'assign_agent', agent_id: 7 });
        expect(buildTicketBulkPayload({ key: 'workflow' }, 9)).toBeNull();
    });

    it('counts how many selected tickets carry each tag', () => {
        expect(countTagCoverage([{ tags: [{ id: 1 }, { id: 2 }] }, { tags: [{ id: 1 }] }])).toEqual({ 1: 2, 2: 1 });
    });
});

describe('ticket bulk bar', () => {
    let el;
    let vm;
    let store;
    let posts;

    const flush = async () => {
        await Promise.resolve();
        await nextTick();
        await nextTick();
    };
    const click = async (selector) => {
        el.querySelector(selector).click();
        await flush();
    };
    const buttonByText = (text) => Array.from(el.querySelectorAll('button')).find(b => b.textContent.trim().startsWith(text));

    beforeEach(async () => {
        document.body.innerHTML = '';
        // jsdom has no layout; a desktop-width list keeps the full bar instead of the compact menu.
        Element.prototype.getBoundingClientRect = () => ({ left: 0, right: 1200, top: 0, bottom: 800, width: 1200, height: 800 });
        Object.defineProperty(document.documentElement, 'clientWidth', { value: 1200, configurable: true });
        posts = [];
        const pinia = createPinia();
        setActivePinia(pinia);
        const { useTicketListStore } = await import('@/admin/stores/ticketList.store');
        store = useTicketListStore();
        store.tickets = [
            { id: 11, status: 'active', priority: 'normal', tags: [{ id: 1 }] },
            { id: 12, status: 'closed', priority: 'normal', tags: [] },
        ];
        store.ticket_selections = [11, 12];

        const { default: Bar } = await import('@/admin/Modules/Tickets/_BulkActions.vue');
        el = document.createElement('div');
        document.body.appendChild(el);

        const app = createApp({ render: () => h(Bar) });
        app.use(pinia);
        app.config.warnHandler = () => {};
        app.component('ElIcon', { render() { return h('i', this.$slots.default?.()); } });
        app.component('ElDialog', { render: () => null });
        app.config.globalProperties.$t = $t;
        app.config.globalProperties.$_n = $_n;
        app.config.globalProperties.appVars = appVars([...MANAGE, 'fst_delete_tickets']);
        app.config.globalProperties.$post = (url, data) => {
            posts.push({ url, data });
            return { then() { return this; }, catch() { return this; }, always() { return this; } };
        };
        app.config.globalProperties.$get = () => ({ then() { return this; }, catch() { return this; }, always() { return this; } });
        app.config.globalProperties.$notify = () => {};
        app.config.globalProperties.$handleError = () => {};
        vm = app.mount(el);
        await flush();
    });

    it('shows the selection count', () => {
        expect(el.querySelector('.fs_bb_count').textContent).toContain('2 selected');
    });

    it('removes a tag from the selection through remove_tags', async () => {
        await click('[data-panel="tags"]');
        await click('.fs_bb_seg button:nth-child(2)');

        // Remove mode lists only tags a selected ticket has.
        const options = Array.from(el.querySelectorAll('.fs_bb_option_label')).map(n => n.textContent.trim());
        expect(options).toEqual(['billing']);

        el.querySelector('.fs_bb_option input').dispatchEvent(new Event('change'));
        await flush();
        const apply = el.querySelector('.fs_bb_apply');
        expect(apply.textContent.trim()).toBe('Remove 1 tag from 2 tickets');
        apply.click();

        expect(posts).toEqual([{ url: 'tickets/bulk-actions', data: { bulk_action: 'remove_tags', tag_ids: [1], ticket_ids: [11, 12] } }]);
    });

    it('reopens only the closed tickets, saying how many', async () => {
        await click('[data-panel="more"]');
        buttonByText('Reopen').click();
        await flush();

        expect(el.querySelector('.fs_bb_info').textContent).toContain('1 of 2 selected tickets are closed');
        expect(el.querySelector('.fs_bb_apply').textContent.trim()).toBe('Reopen 1 ticket');
    });

    it('never focuses the delete button when the delete panel opens', async () => {
        await click('[data-panel="delete"]');
        expect(document.activeElement).not.toBe(el.querySelector('.fs_bb_apply'));
        expect(el.querySelector('.fs_bb_apply').textContent.trim()).toBe('Delete 2 tickets');
    });

    it('closes the panel on Escape, then clears the selection on a second Escape', async () => {
        await click('[data-panel="tags"]');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flush();
        expect(el.querySelector('.fs_bb_panel')).toBeNull();
        expect(store.ticket_selections).toEqual([11, 12]);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flush();
        expect(store.ticket_selections).toEqual([]);
        expect(el.querySelector('.fs_bb_bar')).toBeNull();
    });
});
