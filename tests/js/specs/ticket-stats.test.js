// Ticket Stats widget rows: durations, and the "Not yet" / "Not recorded" rules.

import { describe, it, expect } from 'vitest';
import { formatDuration, buildTicketStats } from '@/admin/Modules/Tickets/parts/ticketStats';

const $t = (s, ...args) => {
    let i = 0;
    return s.replace(/%(\d)\$s|%s/g, (m, n) => String(n ? args[n - 1] : args[i++]));
};
const helpers = { $t, sourceLabel: (s) => s.charAt(0).toUpperCase() + s.slice(1), formatDate: (d) => (d ? 'D:' + d : null) };
const byKey = (rows) => Object.fromEntries(rows.map(r => [r.key, r]));

describe('formatDuration', () => {
    it('picks the two largest units', () => {
        expect(formatDuration(3 * 86400 + 4 * 3600 + 59, $t)).toBe('3d 4h');
        expect(formatDuration(2 * 3600 + 14 * 60, $t)).toBe('2h 14m');
        expect(formatDuration(45 * 60 + 30, $t)).toBe('45m');
        expect(formatDuration(20, $t)).toBe('Under a minute');
        expect(formatDuration(null, $t)).toBe('Under a minute');
    });
});

describe('buildTicketStats', () => {
    it('shows the first response time, replies and channel', () => {
        const rows = byKey(buildTicketStats({ id: 1, status: 'active', source: 'email', created_at: '2026-01-01', first_response_time: 3600 * 2, response_count: '3' }, helpers));
        expect(rows.created.hint).toBe('via Email');
        expect(rows.first_response.value).toBe('2h 0m');
        expect(rows.replies.value).toBe('3');
        expect(rows.resolved).toBeUndefined();
    });

    it('says Not yet on a new ticket and Not recorded when the time was never stored', () => {
        expect(byKey(buildTicketStats({ id: 1, status: 'new' }, helpers)).first_response.value).toBe('Not yet');
        expect(byKey(buildTicketStats({ id: 1, status: 'active', first_response_time: null }, helpers)).first_response.value).toBe('Not recorded');
    });

    it('names the agent who started an outreach ticket', () => {
        const rows = byKey(buildTicketStats({ id: 1, status: 'active', source: 'agent_initiated', created_by_agent: { full_name: 'Ana Agent' } }, helpers));
        expect(rows.created.hint).toBe('by Ana Agent');
    });

    it('adds the resolution time on closed tickets', () => {
        const rows = byKey(buildTicketStats({ id: 1, status: 'closed', total_close_time: 90000, resolved_at: '2026-01-02' }, helpers));
        expect(rows.resolved.value).toBe('1d 1h');
        expect(rows.resolved.hint).toBe('D:2026-01-02');
    });

    it('returns nothing before the ticket has loaded', () => {
        expect(buildTicketStats({}, helpers)).toEqual([]);
    });
});
