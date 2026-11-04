// @vitest-environment jsdom
// Leaving a ticket asks first whenever the reply box holds something to send:
// text, a pasted image or embed, or an attached file.

import { describe, it, expect, vi } from 'vitest';

vi.mock('@/admin/Components/IconPack.vue', () => ({ default: { render: () => null } }));

// Rest.js wires jQuery ajax handlers when the component tree is imported.
const noop = () => jq;
const jq = new Proxy(noop, { get: () => noop });
globalThis.jQuery = jq;
globalThis.window.jQuery = jq;

// Importing the full view warns about strings the test has no map for.
vi.spyOn(console, 'warn').mockImplementation(() => {});
vi.spyOn(console, 'log').mockImplementation(() => {});

const unsent = async (box, open = 'response') => {
    const { default: ViewTicket } = await import('@/admin/Modules/Tickets/ViewTicket.vue');
    const ctx = { show_response_box: open, $refs: { createResponse: { attachments: [], ...box } } };
    return ViewTicket.methods.hasUnsentReply.call(ctx);
};

describe('ViewTicket hasUnsentReply', () => {
    it('counts text', async () => {
        expect(await unsent({ response_body: '<p>Hello</p>' })).toBe(true);
    });

    it('counts an image-only reply', async () => {
        expect(await unsent({ response_body: '<p><img src="data:image/png;base64,AAAA"></p>' })).toBe(true);
    });

    it('counts an attached file with no text', async () => {
        expect(await unsent({ response_body: '', attachments: [{ id: 3 }] })).toBe(true);
    });

    it('treats empty markup, or a closed box, as nothing to lose', async () => {
        expect(await unsent({ response_body: '<p>&nbsp;</p><p><br></p>' })).toBe(false);
        expect(await unsent({ response_body: '<p>Hello</p>' }, '')).toBe(false);
    });
});
