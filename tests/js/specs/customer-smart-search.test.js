// @vitest-environment jsdom
// The customers list search box: the `/` field menu helpers, and the key handling that
// picks a field without also running the search. Ported from FluentCRM's contact search.

import { describe, it, expect, vi } from 'vitest';
import { getSearchFields, findSlashToken, filterSearchFields } from '@/admin/Modules/Customers/Search/customerSearchFields';
import CustomerSearcher from '@/admin/Modules/Customers/Search/CustomerSearcher.vue';
import CustomerSearchSummary from '@/admin/Modules/Customers/Search/CustomerSearchSummary.vue';

const $t = (s, ...args) => args.reduce((out, arg) => out.replace(/%s/, arg), s);
const fields = getSearchFields($t);

// A plain object standing in for the component instance.
const searcher = (state = {}) => {
    const ctx = {
        $t,
        model: '',
        slash: { open: false, query: '', start: -1, end: -1, index: 0 },
        suppressEnter: false,
        menuId: 'menu',
        $emit: vi.fn(),
        $nextTick: (fn) => fn && fn(),
        $refs: {},
        ...state,
    };
    Object.keys(CustomerSearcher.methods).forEach((name) => {
        ctx[name] = CustomerSearcher.methods[name].bind(ctx);
    });
    Object.defineProperty(ctx, 'slashResults', {
        get: () => filterSearchFields(fields, ctx.slash.query),
    });
    return ctx;
};

const key = (name) => ({ key: name, preventDefault: vi.fn(), stopPropagation: vi.fn() });

describe('customer search field keys', () => {
    it('match the server field map, including the created: date key', () => {
        // Keep in step with CustomerSearch::FIELDS (aliases like since:, first_name: are server-only).
        expect(fields.map(f => f.key)).toEqual([
            'email', 'name', 'first', 'last', 'title', 'address', 'city', 'state', 'zip', 'country',
            'status', 'tickets', 'open', 'created', 'activity', 'user', 'id'
        ]);
    });
});

describe('findSlashToken', () => {
    it('finds a / at the start or after a space, up to the cursor', () => {
        expect(findSlashToken('/ci', 3)).toEqual({ query: 'ci', start: 0, end: 3 });
        expect(findSlashToken('anna /ti', 8)).toEqual({ query: 'ti', start: 5, end: 8 });
    });

    it('ignores a / inside a word, a URL or a finished key', () => {
        expect(findSlashToken('a/b', 3)).toBeNull();
        expect(findSlashToken('https://acme.com', 16)).toBeNull();
        expect(findSlashToken('/city:', 6)).toBeNull();
    });
});

describe('filterSearchFields', () => {
    it('puts key-prefix matches before label matches', () => {
        const keys = filterSearchFields(fields, 'c').map(f => f.key);
        expect(keys.slice(0, 3)).toEqual(['city', 'country', 'created']);
    });

    it('matches labels too', () => {
        expect(filterSearchFields(fields, 'postal').map(f => f.key)).toEqual(['zip']);
    });
});

describe('customer searcher keys', () => {
    it('Enter on an open menu picks the field and does not search', () => {
        const ctx = searcher({ model: 'anna /ci', slash: { open: true, query: 'ci', start: 5, end: 8, index: 0 } });

        ctx.onKeydown(key('Enter'));
        expect(ctx.model).toBe('anna city:');
        expect(ctx.slash.open).toBe(false);

        ctx.fire();
        expect(ctx.$emit).not.toHaveBeenCalledWith('search', expect.anything());

        // The next Enter searches.
        ctx.fire();
        expect(ctx.$emit).toHaveBeenCalledWith('search', 'anna city:');
    });

    it('arrows wrap around the menu', () => {
        const ctx = searcher({ slash: { open: true, query: 'c', start: 0, end: 2, index: 0 } });
        ctx.onKeydown(key('ArrowUp'));
        expect(ctx.slash.index).toBe(ctx.slashResults.length - 1);
        ctx.onKeydown(key('ArrowDown'));
        expect(ctx.slash.index).toBe(0);
    });

    it('Esc closes the menu and keeps the typed text', () => {
        const ctx = searcher({ model: '/ci', slash: { open: true, query: 'ci', start: 0, end: 3, index: 0 } });
        ctx.onKeydown(key('Escape'));
        expect(ctx.slash.open).toBe(false);
        expect(ctx.model).toBe('/ci');
    });

    it('a tips example runs at once; a field chip only appends its key', () => {
        const ctx = searcher({ model: 'anna' });
        ctx.insertKey('city:');
        expect(ctx.model).toBe('anna city:');
        expect(ctx.$emit).not.toHaveBeenCalled();

        ctx.applyQuery('status:blocked');
        expect(ctx.$emit).toHaveBeenCalledWith('search', 'status:blocked');
    });
});

describe('customer search summary', () => {
    const summary = (meta) => {
        const ctx = { $t, meta };
        ctx.fieldLabels = CustomerSearchSummary.computed.fieldLabels.call(ctx);
        return {
            parts: CustomerSearchSummary.computed.parts.call(ctx),
            unknown: CustomerSearchSummary.computed.unknownKeysText.call(ctx),
        };
    };

    it('labels ids, words and scoped terms from search_meta', () => {
        const { parts } = summary({
            mode: 'words', text: 'anna', words: ['anna'], ids: [12],
            scoped: [{ key: 'created', field: 'created_at', value: '2026-05' }, { key: 'city', field: 'city', value: '' }],
            unknown: [],
        });
        expect(parts).toEqual([
            { label: 'Customer ID', values: ['#12'] },
            { label: 'Name or email contains', values: ['anna'] },
            { label: 'Created at', values: ['2026-05'] },
            { label: 'City', values: ['(empty)'] },
        ]);
    });

    it('words the unknown-key warning for one or several keys', () => {
        expect(summary({ unknown: ['zip2'] }).unknown).toBe('zip2: is not a search field, so it was matched as plain text.');
        expect(summary({ unknown: ['a', 'b'] }).unknown).toBe('a:, b: are not search fields, so they were matched as plain text.');
    });
});
