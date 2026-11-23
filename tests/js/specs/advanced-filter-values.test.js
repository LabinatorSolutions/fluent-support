import { describe, expect, it } from 'vitest';
import {
    countAdvancedFilterData,
    hasAdvancedFilterValue
} from '@/admin/Modules/Tickets/parts/RichFilters/advancedFilterUtils.js';

describe('advanced filter value validation', () => {
    it.each([
        '',
        '   ',
        [],
        [''],
        ['   '],
        null,
        undefined,
        NaN,
        Infinity,
        -Infinity,
        false,
        {}
    ])('rejects an empty or unsupported value: %s', (value) => {
        expect(hasAdvancedFilterValue(value)).toBe(false);
    });

    it.each([
        'active',
        ['active'],
        1,
        0,
        [1],
        [null, 2]
    ])('accepts a populated string or finite numeric value: %s', (value) => {
        expect(hasAdvancedFilterValue(value)).toBe(true);
    });

    it('counts valid filters across groups', () => {
        const groups = [
            [
                { value: '' },
                { value: [1] },
                { value: ['active'] }
            ],
            [
                { value: null },
                { value: [null, 2] }
            ]
        ];

        expect(countAdvancedFilterData(groups)).toBe(3);
    });

    it('ignores malformed groups and filter entries', () => {
        expect(countAdvancedFilterData(null)).toBe(0);
        expect(countAdvancedFilterData([null, 'invalid', [null, { value: [] }]])).toBe(0);
    });
});
