// @vitest-environment jsdom
// API dates are site time ("Y-m-d H:i:s"). Formatting shows them as stored and
// relative times compare them with server_time (also site time), whatever the
// browser's own timezone is.
process.env.TZ = 'America/New_York';

import { describe, it, expect, beforeAll, vi } from 'vitest';
import FluentFramework from '@/admin/Bits/FluentFramework';

// Rest.js wires jQuery ajax handlers on import; the date helpers never call it.
vi.mock('@/admin/Bits/Rest', () => ({ default: {} }));

describe('site-time date helpers', () => {
    let helpers;

    beforeAll(() => {
        window.fluentSupportAdmin = { server_time: '2026-01-02 12:00:00' };
        // The admin app exposes these as dateTimeFormat, localDate and $timeDiff; none of them reads instance state.
        helpers = FluentFramework.prototype;
    });

    it('formats a stored date as the same clock time', () => {
        expect(helpers.dateTimeFormat('2026-01-02 10:00:00', 'YYYY-MM-DD HH:mm')).toBe('2026-01-02 10:00');
        expect(helpers.localDate('2026-01-02 10:00:00').format('HH:mm')).toBe('10:00');
    });

    it('measures relative time against server_time, not the browser clock zone', () => {
        expect(helpers.humanDiffTime('2026-01-02 10:00:00')).toBe('2 hours ago');
    });
});
