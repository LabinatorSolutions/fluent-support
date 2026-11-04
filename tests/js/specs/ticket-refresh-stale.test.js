// @vitest-environment jsdom
// A thread refresh that comes back after the agent moved to another ticket
// leaves that ticket's thread and status alone.

import { describe, it, expect, vi } from 'vitest';

vi.mock('@/admin/Components/IconPack.vue', () => ({ default: { render: () => null } }));

// The view loads the ticket through ticketService; each case sets what get() answers.
let getTicket;
vi.mock('@/admin/services/ticketService', () => ({ default: { get: (...args) => getTicket(...args) } }));

// Rest.js wires jQuery ajax handlers when the component tree is imported.
const noop = () => jq;
const jq = new Proxy(noop, { get: () => noop });
globalThis.jQuery = jq;
globalThis.window.jQuery = jq;

// Importing the full view warns about strings the test has no map for.
vi.spyOn(console, 'warn').mockImplementation(() => {});
vi.spyOn(console, 'log').mockImplementation(() => {});

describe('ViewTicket refreshConversations', () => {
    it('drops a response for a ticket the agent already left', async () => {
        const { default: ViewTicket } = await import('@/admin/Modules/Tickets/ViewTicket.vue');
        let resolve;
        const ctx = {
            ticket_id: 'A',
            conversations: ['B thread'],
            ticket: { status: 'active' },
            $t: (s) => s,
            $notify: vi.fn(),
            $handleError: vi.fn(),
        };

        getTicket = () => new Promise((r) => { resolve = r; });
        ctx.applyTicketStatus = ViewTicket.methods.applyTicketStatus.bind(ctx);
        ViewTicket.methods.refreshConversations.call(ctx);
        ctx.ticket_id = 'B';
        resolve({ responses: ['A thread'], ticket: { status: 'closed' } });
        await Promise.resolve();
        await Promise.resolve();

        expect(ctx.conversations).toEqual(['B thread']);
        expect(ctx.ticket.status).toBe('active');
        expect(ctx.$notify).not.toHaveBeenCalled();
    });

    it('applies a response for the ticket still on screen', async () => {
        const { default: ViewTicket } = await import('@/admin/Modules/Tickets/ViewTicket.vue');
        const ctx = {
            ticket_id: 'A',
            conversations: [],
            ticket: { status: 'active' },
            $t: (s) => s,
            $notify: vi.fn(),
            $handleError: vi.fn(),
        };

        getTicket = () => Promise.resolve({ responses: ['A thread'], ticket: { status: 'closed' } });
        ctx.applyTicketStatus = ViewTicket.methods.applyTicketStatus.bind(ctx);
        ViewTicket.methods.refreshConversations.call(ctx);
        await Promise.resolve();
        await Promise.resolve();

        expect(ctx.conversations).toEqual(['A thread']);
        expect(ctx.ticket.status).toBe('closed');
        expect(ctx.$notify).toHaveBeenCalledOnce();
    });
});
