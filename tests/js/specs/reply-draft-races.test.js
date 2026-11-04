// @vitest-environment jsdom
// The reply draft's server ID arrives after the first autosave returns. A late
// ID must never land on another ticket's draft or a note, and sending while
// that save is in flight must still delete the draft.

import { describe, it, expect, vi } from 'vitest';

vi.mock('@/admin/Components/IconPack.vue', () => ({ default: { render: () => null } }));

// Rest.js wires jQuery ajax handlers when the component tree is imported.
const noop = () => jq;
const jq = new Proxy(noop, { get: () => noop });
globalThis.jQuery = jq;
globalThis.window.jQuery = jq;

vi.spyOn(console, 'warn').mockImplementation(() => {});
vi.spyOn(console, 'log').mockImplementation(() => {});

// Like a jqXHR: done/always run synchronously in registration order, then() a tick later.
const request = () => {
    let settled = false;
    let value;
    const sync = [];
    const later = [];
    const req = {
        done(cb) { settled ? cb(value) : sync.push(cb); return req; },
        always(cb) { settled ? cb(value) : sync.push(cb); return req; },
        then(cb) { later.push(cb); return req; },
        catch() { return req; },
        resolve(response) {
            settled = true;
            value = response;
            sync.forEach(cb => cb(response));
            queueMicrotask(() => later.forEach(cb => cb(response)));
        }
    };
    return req;
};

const resolved = (response) => {
    const req = request();
    req.resolve(response);
    return req;
};

const viewTicket = async () => (await import('@/admin/Modules/Tickets/ViewTicket.vue')).default;
const createResponse = async () => (await import('@/admin/Modules/Tickets/_CreateResponse.vue')).default;

const viewContext = (ViewTicket, ticketId) => ({
    ...ViewTicket.methods,
    $route: { params: { ticket_id: String(ticketId) } },
    _draftGeneration: 1,
    draftData: null,
    draftResponse: null
});

describe('ViewTicket syncDraft', () => {
    it('records the draft ID the first save returns', async () => {
        const ctx = viewContext(await viewTicket(), 5);
        const save = request();

        ctx.syncDraft({ content: '<p>Hi</p>', draftID: '' }, save, 5);
        save.resolve({ draftID: 42 });

        expect(ctx.draftData.id).toBe(42);
    });

    it('ignores the save a closing box flushes for the ticket the agent left', async () => {
        const ctx = viewContext(await viewTicket(), 9);

        ctx.syncDraft({ content: '<p>Ticket 5 text</p>', draftID: '' }, request(), 5);

        expect(ctx.draftData).toBe(null);
        expect(ctx.draftResponse).toBe(null);
    });

    it("drops a late ID once the agent moved to another ticket", async () => {
        const ctx = viewContext(await viewTicket(), 5);
        const save = request();
        ctx.syncDraft({ content: '<p>Ticket 5 text</p>', draftID: '' }, save, 5);

        // Route watcher: new ticket, new generation; the agent starts a draft there.
        ctx._draftGeneration++;
        ctx.$route.params.ticket_id = '9';
        ctx.draftData = null;
        ctx.syncDraft({ content: '<p>Ticket 9 text</p>', draftID: '' }, request(), 9);
        save.resolve({ draftID: 42 });

        expect(ctx.draftData.id).toBeUndefined();
        expect(ctx.draftData.value.content).toBe('<p>Ticket 9 text</p>');
    });
});

describe('CreateResponse draft ID', () => {
    it('a note composer never adopts the reply draft ID', async () => {
        const CreateResponse = await createResponse();
        const note = { type: 'note', draftID: '' };
        const reply = { type: 'response', draftID: '' };

        CreateResponse.watch['draft.id'].call(note, 42);
        CreateResponse.watch['draft.id'].call(reply, 42);

        expect(note.draftID).toBe('');
        expect(reply.draftID).toBe(42);
    });

    it('sending during the first draft save waits for it, then deletes the draft', async () => {
        const CreateResponse = await createResponse();
        const emitted = [];
        const posts = [];
        const save = request();
        const ctx = {
            ...CreateResponse.methods,
            ...CreateResponse.data.call({ attachment_store: null }),
            ticket: { id: 5 },
            type: 'response',
            last_conversation_id: null,
            draftID: '',
            response_body: '<p>Hi</p>',
            _pendingDraftSave: save,
            $t: (s) => s,
            $notify: () => {},
            $emit: (...args) => emitted.push(args),
            $post: (action, data) => {
                posts.push(action);
                return resolved({ message: 'Sent', response: {}, ticket: {} });
            }
        };

        ctx.create('no');
        expect(posts).toEqual([]);
        expect(ctx.creating).toBe(true);

        save.resolve({ draftID: 42 });
        await new Promise(resolve => setTimeout(resolve));

        expect(posts).toEqual(['tickets/5/responses']);
        expect(emitted.find(([name]) => name === 'discardDraft')).toEqual(['discardDraft', 42]);
    });

    it('scheduling during the first draft save still schedules, never sends now', async () => {
        const CreateResponse = await createResponse();
        const emitted = [];
        const posts = [];
        const save = request();
        const ctx = {
            ...CreateResponse.methods,
            ...CreateResponse.data.call({ attachment_store: null }),
            ticket: { id: 5 },
            type: 'response',
            last_conversation_id: null,
            draftID: '',
            response_body: '<p>Later</p>',
            _pendingDraftSave: save,
            $refs: {},
            $t: (s) => s,
            $notify: () => {},
            $emit: (...args) => emitted.push(args),
            $post: (action, data) => {
                posts.push(data);
                return resolved({ message: 'Scheduled', response: {}, ticket: {} });
            }
        };

        ctx.create('yes', false, 1793264400);
        save.resolve({ draftID: 42 });
        await new Promise(resolve => setTimeout(resolve));

        expect(posts).toHaveLength(1);
        expect(posts[0].scheduled_at).toBe(1793264400);
        expect(posts[0].close_ticket).toBe('yes');
        expect(emitted.find(([name]) => name === 'scheduled')).toBeTruthy();
        expect(emitted.find(([name]) => name === 'created')).toBeUndefined();
    });
});
