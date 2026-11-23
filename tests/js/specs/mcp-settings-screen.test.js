// @vitest-environment jsdom
// The MCP settings screen: the state badge, the Status card, and when the
// application password method is folded behind FluentHub's one URL.

import { describe, it, expect } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import McpIntegration from '@/admin/Modules/Settings/McpIntegration.vue';

const status = (over = {}) => ({
    mcp_enabled: true,
    adapter_available: true,
    toolkit_installed: true,
    toolkit_active: true,
    toolkit_version: '2.2.0',
    adapter_version: '0.7.1',
    abilities_api: true,
    can_auto_install: false,
    endpoint_url: 'https://example.test/wp-json/fluent-support/mcp',
    tools_count: 24,
    app_passwords_url: 'https://example.test/wp-admin/profile.php',
    current_user_login: 'agent',
    is_local_dev: false,
    hub: null,
    ...over
});

const hub = (over = {}) => ({
    ready: true, user_can_connect: true, can_manage: true, included: true, hub_reason: '', sign_in_reason: '',
    manage_url: 'https://example.test/hub', profile_url: '',
    clients: [{ key: 'claude', label: 'Claude', note: 'Paste this URL.', snippet: 'https://example.test/wp-json/fluent-hub/mcp' }],
    products: [],
    ...over
});

// A jQuery-style promise that settles at once.
const settled = (data) => {
    const chain = {
        then(cb) { const next = cb(data); return next && next.then ? next : chain; },
        catch() { return chain; },
        always(cb) { cb(); return chain; }
    };
    return chain;
};

async function mount(response) {
    const el = document.createElement('div');
    const app = createApp({ render: () => h(McpIntegration) });
    app.config.warnHandler = () => {};
    const pass = (tag) => ({ render() { return h(tag, this.$slots.default?.()); } });
    ['ElSkeleton', 'ElSkeletonItem', 'ElTabs', 'ElCheckbox'].forEach(name => app.component(name, pass('div')));
    app.component('ElTabPane', { props: ['label'], render() { return h('span', { class: 'tab' }, this.label); } });
    app.component('ElSwitch', { render: () => h('i') });
    app.component('ElInput', { props: ['modelValue'], render() { return h('input', { value: this.modelValue }); } });
    app.component('ElButton', { emits: ['click'], render() { return h('button', { onClick: () => this.$emit('click') }, this.$slots.default?.()); } });
    app.directive('loading', {});
    Object.assign(app.config.globalProperties, {
        $t: (s, ...args) => args.reduce((text, arg) => text.replace('%s', arg), s),
        $_x: (s) => s,
        $_n: (one) => one,
        $setTitle: () => {},
        $notify: () => {},
        $handleError: () => {},
        $copyToClipboard: () => {},
        $get: (url) => settled(url === 'settings/mcp' ? response : { snippets: { 'claude-code': { snippet: 'claude mcp add fs-example-test', instructions: 'Paste it.' } } }),
        $post: () => settled({})
    });
    app.mount(el);
    await nextTick();
    await nextTick();

    return { el, find: (id) => el.querySelector('[data-testid="' + id + '"]') };
}

describe('MCP settings screen', () => {
    it('off: a Disabled badge, the switch and the who-can-connect note only', async () => {
        const { el, find } = await mount(status({ mcp_enabled: false }));

        expect(find('mcp-state').textContent).toBe('Disabled');
        expect(find('mcp-active')).not.toBeNull();
        expect(find('mcp-connect')).toBeNull();
        expect(el.textContent).not.toContain('Status');
        expect(el.textContent).toContain('Mcp_Who_Can_Connect');
    });

    it('on without an adapter: Adapter Required, and no way to connect yet', async () => {
        const { el, find } = await mount(status({ adapter_available: false, toolkit_active: false, toolkit_installed: false }));

        expect(find('mcp-state').textContent).toBe('Adapter Required');
        expect(find('mcp-adapter-missing')).not.toBeNull();
        expect(find('mcp-connect')).toBeNull();
        expect(el.textContent).toContain('Get FluentHub');
    });

    it('on an old WordPress it says what is missing', async () => {
        const { find } = await mount(status({ adapter_available: false, abilities_api: false }));
        expect(find('mcp-adapter-missing').textContent).toContain('The MCP server needs WordPress 6.9 or newer.');
    });

    it('on without FluentHub: Connected, the tool count, and the application password method open', async () => {
        const { el, find } = await mount(status({ toolkit_active: false, toolkit_version: null }));

        expect(find('mcp-state').textContent).toBe('Connected');
        expect(el.textContent).toContain('WordPress MCP Adapter 0.7.1');
        expect(find('mcp-count').textContent).toBe('24');
        expect(find('mcp-hub-row')).toBeNull();
        expect(find('mcp-password-toggle')).toBeNull();
        expect(find('mcp-password-method')).not.toBeNull();
        expect(find('mcp-snippet').textContent).toBe('claude mcp add fs-example-test');
        expect([...el.querySelectorAll('.tab')].map(t => t.textContent)).toEqual(['Claude Code', 'OpenAI Codex', 'Cursor', 'Other', 'Claude Desktop']);
    });

    it('with the one URL usable: it leads, and the application password method is folded', async () => {
        const { el, find } = await mount(status({ hub: hub() }));

        expect(el.textContent).toContain('FluentHub 2.2.0');
        expect(find('mcp-hub-row').textContent).toContain('On');
        expect(find('mcp-hub-ready')).not.toBeNull();
        expect(find('mcp-password-method')).toBeNull();

        find('mcp-password-toggle').click();
        await nextTick();
        expect(find('mcp-password-method')).not.toBeNull();
        expect(find('mcp-password-toggle').textContent.trim()).toBe('Hide the application password method');
    });

    it('with FluentHub present but sign-in not usable: the row says Off and the password method stays open', async () => {
        const { find } = await mount(status({ hub: hub({ ready: false, sign_in_reason: 'app_passwords_off' }) }));

        expect(find('mcp-hub-row').textContent).toContain('Off');
        expect(find('mcp-hub-not-ready')).not.toBeNull();
        expect(find('mcp-password-toggle')).toBeNull();
        expect(find('mcp-password-method')).not.toBeNull();
    });
});
