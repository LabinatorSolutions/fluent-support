// @vitest-environment jsdom
// jsdom, not happy-dom: happy-dom makes DOMPurify drop safe attributes browsers keep.

import { describe, it, expect, beforeAll } from 'vitest';
import createDOMPurify from 'dompurify';
import { applyPurifyGuards } from '@/common/domPurifyGuards.js';

describe('applyPurifyGuards', () => {
    let purify;

    beforeAll(() => {
        purify = createDOMPurify(window);
        applyPurifyGuards(purify);
    });

    it('keeps target="_blank" on links and adds rel="noopener noreferrer"', () => {
        const html = purify.sanitize('<a href="https://example.test" target="_blank">x</a>');

        expect(html).toContain('target="_blank"');
        expect(html).toContain('rel="noopener noreferrer"');
    });

    it('keeps existing rel tokens on a new-tab link', () => {
        const html = purify.sanitize('<a href="https://example.test" target="_blank" rel="nofollow">x</a>');

        expect(html).toMatch(/rel="nofollow noopener noreferrer"/);
    });

    it('removes any other target value', () => {
        for (const target of ['_self', '_parent', '_top', 'some-frame']) {
            const html = purify.sanitize(`<a href="https://example.test" target="${target}">x</a>`);

            expect(html).not.toContain('target=');
            expect(html).not.toContain('rel=');
        }
    });

    it('removes target on elements that are not links', () => {
        const html = purify.sanitize('<form target="_blank"><span target="_blank">x</span></form>');

        expect(html).not.toContain('target=');
    });

    it('leaves same-tab links alone', () => {
        const html = purify.sanitize('<a href="https://example.test" rel="nofollow">x</a>');

        expect(html).toBe('<a href="https://example.test" rel="nofollow">x</a>');
    });

    it('still strips Element Plus classes', () => {
        const html = purify.sanitize('<div class="el-overlay keep-me">x</div>');

        expect(html).toContain('class="keep-me"');
        expect(html).not.toContain('el-overlay');
    });

    it('adds its hooks only once per instance', () => {
        applyPurifyGuards(purify);
        const html = purify.sanitize('<a href="https://example.test" target="_blank">x</a>');

        expect(html.match(/noopener/g)).toHaveLength(1);
    });
});
