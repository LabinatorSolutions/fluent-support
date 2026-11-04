// @vitest-environment jsdom
// The "Closed Ticket" notice reads ticket.resolved_at and ticket.closed_by_person. When a
// reply, the Close button or a re-open changed only the status, the notice read the
// values from before and showed "This ticket was closed on null."

import { describe, it, expect, vi } from 'vitest';

vi.mock('@/admin/Components/IconPack.vue', () => ({ default: { render: () => null } }));

// Rest.js wires jQuery ajax handlers when the component tree is imported.
const noop = () => jq;
const jq = new Proxy(noop, { get: () => noop });
globalThis.jQuery = jq;
globalThis.window.jQuery = jq;

// Importing the full views warns about strings the test has no map for.
vi.spyOn(console, 'warn').mockImplementation(() => {});
vi.spyOn(console, 'log').mockImplementation(() => {});

const closer = { id: 7, full_name: 'Sam Agent' };
const closedTicket = { status: 'closed', resolved_at: '2026-10-22 10:15:00', closed_by_person: closer };

const openTicket = () => ({ id: 3, status: 'active', resolved_at: null });

describe('admin ViewTicket closed notice data', () => {
    const context = async () => {
        const { default: ViewTicket } = await import('@/admin/Modules/Tickets/ViewTicket.vue');
        const ctx = {
            ticket: openTicket(),
            ticket_id: 3,
            conversations: [],
            show_response_box: 'response',
            appVars: { pref: {} },
        };
        ctx.applyTicketStatus = ViewTicket.methods.applyTicketStatus.bind(ctx);
        return { ViewTicket, ctx };
    };

    it('a reply that closes the ticket brings when and by whom', async () => {
        const { ViewTicket, ctx } = await context();

        ViewTicket.methods.recordNewResponse.call(ctx, { id: 11, conversation_type: 'response', content: '' }, {
            ticket: closedTicket,
            update_data: {},
        });

        expect(ctx.ticket.status).toBe('closed');
        expect(ctx.ticket.resolved_at).toBe('2026-10-22 10:15:00');
        expect(ctx.ticket.closed_by_person).toEqual(closer);
    });

    it('a re-open clears the old close details', async () => {
        const { ctx } = await context();
        ctx.ticket = { id: 3, ...closedTicket };

        ctx.applyTicketStatus({ status: 'active', resolved_at: null });

        expect(ctx.ticket.status).toBe('active');
        expect(ctx.ticket.resolved_at).toBe(null);
        expect(ctx.ticket.closed_by_person).toBe(null);
    });
});

describe('portal Ticket closed notice data', () => {
    it('closing from the portal brings the close date', async () => {
        const { default: PortalTicket } = await import('@/customer_portal/components/Ticket.vue');
        const ctx = { ticket: openTicket() };

        PortalTicket.methods.applyTicketStatus.call(ctx, { id: 3, status: 'closed', resolved_at: '2026-10-22 10:15:00' });

        expect(ctx.ticket.status).toBe('closed');
        expect(ctx.ticket.resolved_at).toBe('2026-10-22 10:15:00');
        expect(ctx.ticket.closed_by_person).toBe(null);
    });
});
