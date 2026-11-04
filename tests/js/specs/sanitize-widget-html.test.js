// @vitest-environment jsdom
// jsdom, not happy-dom: happy-dom makes DOMPurify drop safe attributes browsers keep.

import { describe, it, expect, beforeAll } from 'vitest';
import createDOMPurify from 'dompurify';
import { sanitizeWidgetHtml } from '@/common/domPurifyGuards.js';

describe('sanitizeWidgetHtml', () => {
    beforeAll(() => {
        // The helper reads the global DOMPurify the admin app enqueues.
        window.DOMPurify = createDOMPurify(window);
    });

    it('removes scripts, event handlers, javascript: links and iframes', () => {
        const html = sanitizeWidgetHtml(
            '<img src="x" onerror="alert(1)">' +
            '<script>alert(2)</script>' +
            '<a href="javascript:alert(3)">link</a>' +
            '<iframe src="https://evil.test"></iframe>'
        );

        expect(html).not.toMatch(/onerror|<script|javascript:|<iframe/i);
        expect(html).toContain('<img src="x">');
        expect(html).toContain('link');
    });

    it('removes handlers that fire without loading anything, and mXSS', () => {
        const html = sanitizeWidgetHtml(
            '<details open ontoggle="alert(1)"><summary>s</summary></details>' +
            '<svg><animate attributeName="x" dur="1s" onbegin="alert(2)"></animate></svg>' +
            '<iframe srcdoc="<script>alert(3)</script>"></iframe>' +
            '<math><mtext><table><mglyph><style><img src=x onerror=alert(4)></style></mglyph></table></mtext></math>'
        );

        expect(html).not.toMatch(/ontoggle|onbegin|onerror|srcdoc|<iframe|<script/i);
    });

    it('keeps target on external links', () => {
        const html = sanitizeWidgetHtml('<a href="https://shop.test/order/1" target="_blank">Order #1</a>');

        expect(html).toContain('target="_blank"');
        expect(html).toContain('href="https://shop.test/order/1"');
    });

    it('keeps a widget <style> block, even as the first node', () => {
        const css = '<style>.fct_row{color:red}</style>';

        expect(sanitizeWidgetHtml(css + '<div class="fct_row">A</div>')).toContain(css);
        expect(sanitizeWidgetHtml('<div class="fct_row">A</div>' + css)).toContain(css);
    });

    it('keeps inline styles and SVG icons', () => {
        const html = sanitizeWidgetHtml(
            '<span style="color: green">Active</span>' +
            '<svg width="10" height="10"><path d="M0 0L10 10"></path></svg>'
        );

        expect(html).toContain('style="color: green"');
        expect(html).toContain('<path d="M0 0L10 10"></path>');
    });

    it('strips Element Plus classes so widget markup cannot become a live overlay', () => {
        const html = sanitizeWidgetHtml('<div class="el-overlay fct_box">x</div>');

        expect(html).toContain('class="fct_box"');
        expect(html).not.toContain('el-overlay');
    });

    it('returns an empty string for missing HTML', () => {
        expect(sanitizeWidgetHtml(undefined)).toBe('');
        expect(sanitizeWidgetHtml(null)).toBe('');
        expect(sanitizeWidgetHtml('')).toBe('');
    });
});
