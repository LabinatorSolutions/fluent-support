// @vitest-environment jsdom
// The "Connect with one URL" card of the MCP settings screen: its four states,
// the reason shown when sign-in cannot run, and the turn-on buttons.

import { describe, it, expect } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';
import McpHubConnect from '@/admin/Modules/Settings/_McpHubConnect.vue';

const readyHub = (over = {}) => ({
    ready: true,
    user_can_connect: true,
    can_manage: true,
    included: true,
    hub_reason: '',
    sign_in_reason: '',
    endpoint_url: 'https://example.test/wp-json/fluent-hub/mcp',
    manage_url: 'https://example.test/wp-admin/admin.php?page=fluent-hub',
    profile_url: 'https://example.test/wp-admin/profile.php',
    clients: [
        { key: 'claude-code', label: 'Claude Code', note: 'Run this in your terminal.', snippet: 'claude mcp add fluent-example-test https://example.test/wp-json/fluent-hub/mcp' },
        { key: 'cursor', label: 'Cursor', note: 'Add to mcp.json.', snippet: '{"mcpServers":{}}' }
    ],
    products: [
        { slug: 'fluent-support', name: 'Fluent Support', served: true, can_enable: false },
        { slug: 'fluent-crm', name: 'FluentCRM', served: false, can_enable: true },
        { slug: 'fluent-cart', name: 'FluentCart', served: false, can_enable: false }
    ],
    ...over
});

function mount(hub, enabling = '') {
    const emitted = [];
    const copied = [];
    const el = document.createElement('div');
    const app = createApp({ render: () => h(McpHubConnect, { hub, enabling, onEnable: (target) => emitted.push(target) }) });
    app.config.warnHandler = () => {};
    app.component('ElTabs', { render() { return h('div', this.$slots.default?.()); } });
    app.component('ElTabPane', { props: ['label'], render() { return h('span', { class: 'tab' }, this.label); } });
    app.component('ElInput', { props: ['modelValue'], render() { return h('pre', this.modelValue); } });
    app.component('ElButton', {
        props: ['loading', 'disabled'],
        emits: ['click'],
        render() { return h('button', { disabled: this.disabled || this.loading, onClick: () => this.$emit('click') }, this.$slots.default?.()); }
    });
    app.config.globalProperties.$t = (s) => s;
    app.config.globalProperties.$_x = (s) => s;
    app.config.globalProperties.$copyToClipboard = (text) => copied.push(text);
    app.mount(el);

    const find = (id) => el.querySelector('[data-testid="' + id + '"]');
    const button = (text) => [...el.querySelectorAll('button')].find(b => b.textContent.trim() === text);
    return { el, find, button, emitted, copied };
}

describe('MCP hub connect card', () => {
    it('ready: leads with the one URL, prints the instructions as given, links to FluentHub', () => {
        const { el, find, button, copied } = mount(readyHub());

        expect(find('mcp-hub-ready')).not.toBeNull();
        expect(el.textContent).toContain('Connect with one URL');
        expect(el.textContent).toContain('There is no password to copy, and the same URL gives the app your other Fluent plugins.');
        expect([...el.querySelectorAll('.tab')].map(t => t.textContent)).toEqual(['Claude Code', 'Cursor']);
        expect(find('mcp-hub-snippet').textContent).toBe('claude mcp add fluent-example-test https://example.test/wp-json/fluent-hub/mcp');
        expect(el.textContent).toContain('Run this in your terminal.');
        expect(find('mcp-hub-manage').getAttribute('href')).toBe('https://example.test/wp-admin/admin.php?page=fluent-hub');
        expect(find('mcp-hub-manage').textContent).toBe('Manage connections in FluentHub');

        button('Copy').click();
        expect(copied).toEqual(['claude mcp add fluent-example-test https://example.test/wp-json/fluent-hub/mcp']);
    });

    it('ready: an admin sees the plugins on the URL and can turn one on, never off', () => {
        const { find, emitted, el } = mount(readyHub());

        expect(find('mcp-product-fluent-support').textContent).toContain('On');
        expect(find('mcp-product-fluent-support').querySelector('button')).toBeNull();
        expect(find('mcp-product-fluent-cart').textContent).toContain('Off');
        expect(find('mcp-product-fluent-cart').querySelector('button')).toBeNull();

        const turnOn = find('mcp-product-fluent-crm').querySelector('button');
        expect(turnOn.textContent.trim()).toBe('Turn on');
        turnOn.click();
        expect(emitted).toEqual(['fluent-crm']);
        expect(el.textContent).toContain("Connected AI apps can then read and change this plugin's data, for the users the plugin allows.");
        expect(el.textContent).not.toContain('Turn off');
    });

    it('ready: a user who cannot manage FluentHub gets no plugin list and a link to their own apps', () => {
        const { find, el } = mount(readyHub({ can_manage: false }));

        expect(find('mcp-hub-ready')).not.toBeNull();
        expect(find('mcp-hub-products')).toBeNull();
        expect(el.textContent).not.toContain('your other Fluent plugins');
        expect(find('mcp-hub-manage').getAttribute('href')).toBe('https://example.test/wp-admin/profile.php');
    });

    it('buttons are disabled while something is being turned on', () => {
        const { find } = mount(readyHub(), 'fluent-crm');
        expect(find('mcp-product-fluent-crm').querySelector('button').disabled).toBe(true);
    });

    it('hub off: an admin gets the turn-on button and what it does for the whole site', () => {
        const { find, button, emitted, el } = mount(readyHub({ ready: false, hub_reason: 'off', sign_in_reason: 'hub_off' }));

        expect(find('mcp-hub-turn-on')).not.toBeNull();
        expect(find('mcp-hub-ready')).toBeNull();
        expect(el.textContent).toContain('no password to copy');
        expect(el.textContent).toContain('Mcp_Hub_Turn_On_Help');
        button('Turn on FluentHub sign-in').click();
        expect(emitted).toEqual(['hub']);
    });

    it('hub off: someone who cannot manage it is told an administrator can turn it on', () => {
        const { find, el } = mount(readyHub({ ready: false, hub_reason: 'off', can_manage: false }));

        expect(find('mcp-hub-turn-on')).toBeNull();
        expect(find('mcp-hub-not-ready').textContent).toContain('The FluentHub MCP server is off. A site administrator can turn it on.');
        expect(el.querySelector('button')).toBeNull();
        expect(find('mcp-hub-open')).toBeNull();
    });

    it('sign-in off alone is a link to FluentHub, not a button', () => {
        const { find, el } = mount(readyHub({ ready: false, sign_in_reason: 'off' }));

        expect(find('mcp-hub-not-ready').textContent).toContain('Sign-in is switched off in FluentHub.');
        expect(el.querySelector('button')).toBeNull();
        expect(find('mcp-hub-open').getAttribute('href')).toBe('https://example.test/wp-admin/admin.php?page=fluent-hub');
    });

    it('picks a tab when the hub is turned on while the card stays mounted', async () => {
        const state = reactive({ hub: readyHub({ ready: false, hub_reason: 'off', clients: [] }) });
        const el = document.createElement('div');
        const app = createApp({ render: () => h(McpHubConnect, { hub: state.hub }) });
        app.config.warnHandler = () => {};
        app.component('ElTabs', { props: ['modelValue'], render() { return h('div', { 'data-active': this.modelValue }, this.$slots.default?.()); } });
        app.component('ElTabPane', { render: () => h('span') });
        app.component('ElButton', { render() { return h('button', this.$slots.default?.()); } });
        app.config.globalProperties.$t = (s) => s;
        app.config.globalProperties.$_x = (s) => s;
        app.mount(el);
        expect(el.querySelector('[data-testid="mcp-hub-turn-on"]')).not.toBeNull();

        state.hub = readyHub();
        await nextTick();
        await nextTick();
        expect(el.querySelector('[data-testid="mcp-hub-ready"]')).not.toBeNull();
        expect(el.querySelector('[data-active]').getAttribute('data-active')).toBe('claude-code');
    });

    it('says why sign-in cannot run', () => {
        const reason = (over) => mount(readyHub({ ready: false, ...over })).find('mcp-hub-not-ready').textContent;

        expect(reason({ hub_reason: 'adapter_missing' })).toContain('FluentHub needs a newer MCP adapter for it.');
        expect(reason({ hub_reason: 'adapter_outdated' })).toContain('FluentHub needs a newer MCP adapter for it.');
        expect(reason({ included: false })).toContain('Fluent Support is switched off in the FluentHub MCP server.');
        expect(reason({ sign_in_reason: 'plain_permalinks' })).toContain('Sign-in needs pretty permalinks.');
        expect(reason({ sign_in_reason: 'app_passwords_off' })).toContain('Sign-in needs application passwords');
    });

    it('a user who is not allowed does not get the URL', () => {
        const { find, el } = mount(readyHub({ user_can_connect: false, can_manage: false }));

        expect(find('mcp-hub-ready')).toBeNull();
        expect(find('mcp-hub-snippet')).toBeNull();
        expect(find('mcp-hub-not-ready').textContent).toContain("Your account has no access to this plugin's AI tools.");
        expect(el.textContent).not.toContain('fluent-hub/mcp');

        // Each plugin decides who may use its tools: even someone who manages FluentHub is not sent there for it.
        const manager = mount(readyHub({ user_can_connect: false }));
        expect(manager.find('mcp-hub-not-ready').textContent).toContain("Your account has no access to this plugin's AI tools.");
        expect(manager.find('mcp-hub-open')).toBeNull();
    });
});
