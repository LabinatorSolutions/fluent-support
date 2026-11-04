// @vitest-environment jsdom
// Mounts the real widget components, so a raw v-html left in a template fails here.

import { describe, it, expect, beforeAll, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import createDOMPurify from 'dompurify';
import WidgetHeader from '@/admin/Modules/Tickets/parts/_WidgetHeader.vue';

const PAYLOAD = '<img src="x" onerror="alert(1)"><svg><animate onbegin="alert(2)"></animate></svg>Safe text';

vi.mock('@/admin/Bits/Rest.js', () => ({
    default: {
        get: () => Promise.resolve({
            widgets: [{ type: 'html', title: PAYLOAD, content: PAYLOAD }]
        })
    }
}));

const mount = (component, props = {}) => {
    const el = document.createElement('div');
    document.body.appendChild(el);
    const app = createApp(component, props);
    app.config.warnHandler = () => {};
    app.mount(el);
    return el;
};

const flush = async () => {
    await Promise.resolve();
    await nextTick();
    await nextTick();
};

const expectScriptless = (el) => {
    expect(el.querySelector('[onerror], [onbegin], script')).toBeNull();
    expect(el.textContent).toContain('Safe text');
};

describe('widget components sanitize third-party HTML', () => {
    beforeAll(() => {
        // The admin app enqueues DOMPurify globally.
        window.DOMPurify = createDOMPurify(window);
        window.fluentSupportAdmin = { hooks: { applyFilters: (name, value) => value } };
    });

    it('_WidgetHeader sanitizes an HTML title', () => {
        const el = mount(WidgetHeader, { title: PAYLOAD });

        expectScriptless(el);
    });

    it('_WidgetHeader still renders a plain title as text', () => {
        const el = mount(WidgetHeader, { title: 'Orders <3' });

        expect(el.querySelector('.fs_widget_title').textContent).toBe('Orders <3');
    });

    it('DynamicTemplates sanitizes html widget content and its title', async () => {
        const { default: DynamicTemplates } = await import('@/admin/Bits/DynamicTemplates/DynamicTemplates.vue');
        const el = mount(DynamicTemplates, { filter: 'ticket' });
        await flush();

        expect(el.querySelector('.fs_dynamic_widget')).not.toBeNull();
        expectScriptless(el);
    });
});
