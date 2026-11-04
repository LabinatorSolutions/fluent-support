// @vitest-environment jsdom
// The purchases card: summary stats, invoice references and the "Related" renewal rows.

import { describe, it, expect, afterEach } from 'vitest';
import { createApp, h, reactive, nextTick } from 'vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const subscription = (invoice, renewals) => ({
    title: 'Fluent Support Pro License',
    meta: '$19.00',
    subtitle: 'Single Site - Monthly',
    subtitle_meta: '2026-10-15T06:08:02+00:00',
    subtitle_meta_type: 'date',
    reference: { text: '#' + invoice, url: 'https://shop.test/orders/' + invoice },
    badge: { text: 'active', type: 'success' },
    info: '3 renewals · Next renewal February 15, 2027',
    related: renewals.map(no => ({
        title: 'Fluent Support Pro License',
        meta: '$19.00',
        subtitle: 'Single Site - Monthly',
        subtitle_meta: '2026-10-15T06:08:02+00:00',
        subtitle_meta_type: 'date',
        reference: { text: '#' + no, url: 'https://shop.test/orders/' + no },
    })),
});

const summaryWidget = (items) => ({
    title: 'FluentCart Purchases',
    stats_style: 'summary',
    stats: [
        { label: 'Lifetime Value', value: '$768.00', url: 'https://shop.test/customers/2' },
        { label: 'Total Purchases:', value: '10' },
        { label: 'Since', value: '2026-10-15T06:08:00+00:00', type: 'date' },
    ],
    sections: [{ label: 'Recent Purchases', items }],
});

let app;

const mount = async (state) => {
    const { default: StructuredWidget } = await import('@/admin/Modules/Tickets/parts/_StructuredWidget.vue');
    const el = document.createElement('div');
    document.body.appendChild(el);

    app = createApp({ render: () => h(StructuredWidget, { widget: state.widget }) });
    app.config.warnHandler = () => {};
    app.component('ElTag', { render() { return h('span', { class: 'el-tag' }, this.$slots.default?.()); } });
    app.component('ElTooltip', { render() { return h('div', this.$slots.default?.()); } });
    app.config.globalProperties.$t = (s) => s;
    app.config.globalProperties.$dayjs = dayjs;
    app.mount(el);
    await nextTick();
    return el;
};

const text = (el, selector) => [...el.querySelectorAll(selector)].map(node => node.textContent.trim());

describe('structured widget summary layout', () => {
    afterEach(() => {
        app?.unmount();
        document.body.innerHTML = '';
    });

    it('summary_style_draws_hero_value_and_one_line_instead_of_tiles', async () => {
        const el = await mount(reactive({ widget: summaryWidget([subscription('INV-33', [])]) }));

        expect(el.querySelector('.fs_sw_summary_value').textContent.trim()).toBe('$768.00');
        expect(el.querySelector('.fs_sw_summary_label').textContent.trim()).toBe('Lifetime Value');
        expect(text(el, '.fs_sw_summary_item')).toEqual(['Total Purchases: 10', 'Since Oct 15, 2026']);
        expect(el.querySelector('.fs_sw_stats')).toBeNull();
    });

    it('widgets_without_summary_style_keep_the_stat_tiles', async () => {
        const widget = summaryWidget([]);
        delete widget.stats_style;
        const el = await mount(reactive({ widget }));

        expect(el.querySelector('.fs_sw_summary')).toBeNull();
        expect(el.querySelectorAll('.fs_sw_stat').length).toBe(3);
    });

    it('item_shows_price_date_invoice_link_and_existing_badge', async () => {
        const el = await mount(reactive({ widget: summaryWidget([subscription('INV-33', [])]) }));
        const item = el.querySelector('.fs_sw_item');

        expect(item.querySelector('.fs_sw_item_meta').textContent.trim()).toBe('$19.00');
        expect(item.querySelector('.fs_sw_item_sub_meta').textContent.trim()).toBe('Oct 15, 2026');
        const reference = item.querySelector('a.fs_sw_item_reference');
        expect(reference.textContent.trim()).toBe('#INV-33');
        expect(reference.getAttribute('href')).toBe('https://shop.test/orders/INV-33');
        expect(item.querySelector('.fs_sw_item_ref .el-tag').textContent.trim()).toBe('active');
        // Cut-off titles stay readable on hover.
        expect(item.querySelector('.fs_sw_item_title').getAttribute('title')).toBe('Fluent Support Pro License');
    });

    it('related_renewals_stay_hidden_until_the_toggle_is_clicked', async () => {
        const el = await mount(reactive({ widget: summaryWidget([subscription('INV-33', ['INV-36', 'INV-35', 'INV-34'])]) }));
        const toggle = el.querySelector('.fs_sw_item_toggle');

        expect(el.querySelector('.fs_sw_related')).toBeNull();
        expect(toggle.getAttribute('aria-expanded')).toBe('false');

        toggle.click();
        await nextTick();

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(text(el, '.fs_sw_related .fs_sw_item_reference')).toEqual(['#INV-36', '#INV-35', '#INV-34']);
    });

    it('items_without_related_rows_have_no_toggle', async () => {
        const el = await mount(reactive({ widget: summaryWidget([subscription('INV-31', [])]) }));

        expect(el.querySelector('.fs_sw_item_toggle')).toBeNull();
    });

    it('opened_related_rows_close_when_another_customers_widget_loads', async () => {
        const state = reactive({ widget: summaryWidget([subscription('INV-33', ['INV-36'])]) });
        const el = await mount(state);
        el.querySelector('.fs_sw_item_toggle').click();
        await nextTick();
        expect(el.querySelector('.fs_sw_related')).not.toBeNull();

        // Same component instance, new customer's data at the same position.
        state.widget = summaryWidget([subscription('INV-90', ['INV-91'])]);
        await nextTick();
        await nextTick();

        expect(el.querySelector('.fs_sw_related')).toBeNull();
        expect(el.querySelector('.fs_sw_item_toggle').getAttribute('aria-expanded')).toBe('false');
    });

    it('new_fields_render_markup_as_text', async () => {
        const markup = '<img src=x onerror="window.__fsSw=1">';
        const item = subscription('INV-33', ['INV-36']);
        item.reference.text = markup;
        item.subtitle = markup;
        item.related[0].title = markup;
        const el = await mount(reactive({ widget: summaryWidget([item]) }));
        el.querySelector('.fs_sw_item_toggle').click();
        await nextTick();

        expect(el.querySelector('img')).toBeNull();
        expect(el.querySelector('.fs_sw_item_reference').textContent).toBe(markup);
        expect(window.__fsSw).toBeUndefined();
    });

    it('reference_link_with_unsafe_url_renders_as_plain_text', async () => {
        const item = subscription('INV-33', []);
        item.reference.url = 'javascript:alert(1)';
        const el = await mount(reactive({ widget: summaryWidget([item]) }));

        expect(el.querySelector('a.fs_sw_item_reference')).toBeNull();
        expect(el.querySelector('span.fs_sw_item_reference').textContent.trim()).toBe('#INV-33');
    });
});
