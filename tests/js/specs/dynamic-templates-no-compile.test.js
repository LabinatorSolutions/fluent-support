// @vitest-environment jsdom
// Mounts DynamicTemplates with server widgets. Server strings must never be compiled or run.

import { describe, it, expect, beforeAll, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import createDOMPurify from 'dompurify';

const HIT = 'window.__fsXss=(window.__fsXss||0)+1';
// Customer text concatenated into a template: no quotes or tags, so esc_html leaves it intact.
const CUSTOMER_TITLE = '{{ _openBlock.constructor(String.fromCharCode(' + [...HIT].map(c => c.charCodeAt(0)).join(',') + '))() }}';

vi.mock('@/admin/Bits/Rest.js', () => ({
    default: {
        get: () => Promise.resolve({
            widgets: [
                { type: 'vue-template', component: '<template><span id="tpl">' + CUSTOMER_TITLE + '</span></template>' },
                { type: 'vue-template', component: '<template><span id="tpl-script">x</span></template><script>export default { mounted() { ' + HIT + ' } }</script>' },
                { type: 'vue-component', component: { template: '<span id="obj-tpl">' + CUSTOMER_TITLE + '</span>' } },
                { type: 'vue-component', component: 'RegisteredWidget', innerHTML: '<img src="x" onerror="' + HIT + '">' },
                { type: 'html', title: 'HTML widget', content: '<span id="html-ok">Safe html</span>' },
            ],
        }),
    },
}));

const flush = async () => {
    await Promise.resolve();
    await nextTick();
    await nextTick();
};

describe('dynamic templates never compile server strings', () => {
    beforeAll(() => {
        window.DOMPurify = createDOMPurify(window);
        window.__fsXss = 0;
        const components = [{ name: 'RegisteredWidget', component: { template: '<div id="registered">Registered</div>' } }];
        window.fluentSupportAdmin = {
            hooks: { applyFilters: (name, value) => (name === 'ticket_sidebar_components' ? components : value) },
        };
    });

    it('drops template widgets, keeps registered components, and runs no code', async () => {
        const { default: DynamicTemplates } = await import('@/admin/Bits/DynamicTemplates/DynamicTemplates.vue');
        const el = document.createElement('div');
        document.body.appendChild(el);

        const app = createApp(DynamicTemplates, { filter: 'ticket_sidebar' });
        app.config.warnHandler = () => {};
        app.mount(el);
        await flush();
        await flush();

        // The html widget proves the list rendered, so a missing template is not a broken mount.
        expect(el.querySelector('#html-ok')).not.toBeNull();

        expect.soft(el.querySelector('#tpl, #tpl-script'), 'vue-template compiled').toBeNull();
        expect.soft(el.querySelector('#obj-tpl'), 'server component object compiled').toBeNull();
        expect.soft(window.__fsXss, 'server code ran').toBe(0);
        // A registered component still renders by name, and innerHTML did not overwrite it.
        expect.soft(el.querySelector('#registered')?.textContent, 'innerHTML field applied').toBe('Registered');
        expect.soft(el.querySelector('[onerror], script')).toBeNull();
    });
});
