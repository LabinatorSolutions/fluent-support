// Portal ticket list: "Waiting for your reply" shows when the support team replied last.

import { describe, it, expect, vi } from 'vitest';

vi.mock('@/customer_portal/components/_TicketFilter.vue', () => ({ default: { render: () => null } }));
vi.mock('@/customer_portal/components/pieces/Pagination', () => ({ default: { render: () => null } }));

describe('isWaitingOnCustomer', async () => {
    const { default: Tickets } = await import('@/customer_portal/components/Tickets.vue');
    const waiting = Tickets.methods.isWaitingOnCustomer;

    it('is true when the agent replied after the customer', () => {
        expect(waiting({ status: 'active', last_agent_response: '2026-10-08 10:00:00', last_customer_response: '2026-10-07 09:00:00' })).toBe(true);
        expect(waiting({ status: 'active', last_agent_response: '2026-10-08 10:00:00', last_customer_response: null })).toBe(true);
    });

    it('is false when the customer replied last, nobody from support replied, or the ticket is closed', () => {
        expect(waiting({ status: 'active', last_agent_response: '2026-10-07 10:00:00', last_customer_response: '2026-10-08 09:00:00' })).toBe(false);
        expect(waiting({ status: 'new', last_agent_response: null, last_customer_response: '2026-10-08 09:00:00' })).toBe(false);
        expect(waiting({ status: 'closed', last_agent_response: '2026-10-08 10:00:00', last_customer_response: '2026-10-07 09:00:00' })).toBe(false);
    });
});
