// @vitest-environment jsdom
// The "People Watching" poll keeps running after a failed request.

import { describe, it, expect, vi, afterEach } from 'vitest';
import { createApp, h } from 'vue';

vi.mock('@/admin/Components/IconPack.vue', () => ({ default: { render: () => null } }));

// A jQuery-style promise that fails (or succeeds) synchronously.
const settled = (ok) => {
    const chain = {
        then(cb) { if (ok) cb({ live_activity: [] }); return chain; },
        catch(cb) { if (!ok) cb(new Error('network')); return chain; },
        always(cb) { cb(); return chain; },
    };
    return chain;
};

describe('active agents poll', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('polls again after a failed request', async () => {
        vi.useFakeTimers();
        const { default: ActiveAgents } = await import('@/admin/Modules/Tickets/_active_agents.vue');
        const calls = [];
        const results = [false, true];

        const app = createApp({ render: () => h(ActiveAgents, { ticket: { id: 7, live_activity: [] } }) });
        app.config.warnHandler = () => {};
        app.component('ElTooltip', { render() { return h('div', this.$slots.default?.()); } });
        app.config.globalProperties.$t = (s) => s;
        app.config.globalProperties.$_n = (one) => one;
        app.config.globalProperties.$get = (url) => { calls.push(url); return settled(results.shift() ?? true); };
        app.config.globalProperties.$del = () => settled(true);
        vi.spyOn(console, 'error').mockImplementation(() => {});
        app.mount(document.createElement('div'));

        vi.advanceTimersByTime(30000);
        expect(calls.length).toBe(1);

        // Failed: the next poll still comes, within the longer wait.
        vi.advanceTimersByTime(80000);
        expect(calls.length).toBe(2);
        app.unmount();
    });
});
