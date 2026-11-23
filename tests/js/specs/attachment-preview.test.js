// @vitest-environment jsdom

import { describe, it, expect, vi, afterEach } from 'vitest';
import { parseMarkdownFile } from '@/common/markdown.js';
import { shouldPreviewAttachment } from '@/common/attachmentPreview.js';

const click = (extra = {}) => ({ button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false, ...extra });
const pdf = { secureUrl: 'https://site.test/index.php?fst_file=a', file_type: 'application/pdf', title: 'a.pdf' };
const md = { secureUrl: 'https://site.test/index.php?fst_file=b', file_type: 'text/plain', title: 'notes.md' };

describe('parseMarkdownFile', () => {
    it('opens links in a new tab', () => {
        const html = parseMarkdownFile('[docs](https://example.com)');
        expect(html).toContain('target="_blank"');
        expect(html).toContain('rel="noopener noreferrer"');
    });

    it('drops anything that loads from another site', () => {
        const html = parseMarkdownFile(
            '![x](https://evil.test/p.png)\n\n<video src="https://evil.test/v.mp4"></video>\n\n<p style="background:url(https://evil.test/s)">a</p>'
        );
        expect(html).not.toMatch(/<img|<video|style=|evil\.test/);
    });

    it('drops raw HTML that loads or sends to another site', () => {
        const html = parseMarkdownFile([
            '<input type="image" src="https://evil.test/pixel">',
            '<div><style>@import url(https://evil.test/p.css);</style></div>',
            '<table background="https://evil.test/bg"><tr><td background="https://evil.test/td">x</td></tr></table>',
            '<form action="https://evil.test/f"><button>x</button></form>',
        ].join('\n\n'));
        expect(html).not.toMatch(/evil\.test|<style|<form|<input/);
    });

    it('keeps only code-block language classes', () => {
        const html = parseMarkdownFile('<div class="fs_overlay language-js wp-core-ui">x</div>\n\n<span class="notice">y</span>');
        expect(html).toContain('<div class="language-js">x</div>');
        expect(html).toContain('<span>y</span>');
    });

    it('keeps what Markdown renders: task lists, aligned tables, code, numbered lists', () => {
        const html = parseMarkdownFile('- [x] done\n\n| a |\n|:-:|\n| 1 |\n\n```js\nlet a\n```\n\n3. three');
        expect(html).toContain('<input checked="" disabled="" type="checkbox">');
        expect(html).toContain('<td align="center">1</td>');
        expect(html).toContain('<code class="language-js">');
        expect(html).toContain('<ol start="3">');
    });

    it('removes scripts and event handlers', () => {
        const html = parseMarkdownFile('<script>alert(1)</script>\n\n<a href="#" onclick="alert(1)">x</a>');
        expect(html).not.toMatch(/<script|onclick/);
    });
});

describe('shouldPreviewAttachment', () => {
    it('does not preview a .md file stored as another type', () => {
        expect(shouldPreviewAttachment(click(), { ...md, file_type: 'text/html' })).toBe(false);
        expect(shouldPreviewAttachment(click(), { ...md, file_type: 'application/octet-stream' })).toBe(false);
    });

    it('previews a plain left click on PDF and Markdown files', () => {
        expect(shouldPreviewAttachment(click(), pdf)).toBe(true);
        expect(shouldPreviewAttachment(click(), md)).toBe(true);
    });

    it('leaves modified clicks and other files to the browser', () => {
        for (const key of ['metaKey', 'ctrlKey', 'shiftKey', 'altKey']) {
            expect(shouldPreviewAttachment(click({ [key]: true }), pdf)).toBe(false);
        }
        expect(shouldPreviewAttachment(click({ button: 1 }), pdf)).toBe(false);
        expect(shouldPreviewAttachment(click(), { ...pdf, file_type: 'image/png', title: 'a.png' })).toBe(false);
    });

    it('leaves remote files over the 10 MB streaming limit to the link', () => {
        const limit = 10 * 1024 * 1024;
        const remote = { ...pdf, driver: 'amazon_s3' };
        expect(shouldPreviewAttachment(click(), { ...remote, file_size: String(limit) })).toBe(true);
        expect(shouldPreviewAttachment(click(), { ...remote, file_size: String(limit + 1) })).toBe(false);
        expect(shouldPreviewAttachment(click(), { ...md, driver: 'dropbox', file_size: limit + 1 })).toBe(false);
        expect(shouldPreviewAttachment(click(), { ...pdf, driver: 'local', file_size: limit * 3 })).toBe(true);
    });

    it('does not treat a .pdf name with another type as a PDF', () => {
        expect(shouldPreviewAttachment(click(), { ...pdf, file_type: 'text/html' })).toBe(false);
    });
});

describe('AttachmentPreviewDialog loadMarkdown', () => {
    const MAX = 2 * 1024 * 1024;
    const unavailable = 'Attachment preview is not available. Please download the file to view it.';

    const reply = (extra = {}) => ({
        ok: true,
        redirected: false,
        headers: { get: () => 'text/plain; charset=utf-8' },
        text: () => Promise.resolve('# Hello'),
        ...extra,
    });

    const load = async (response, attachment = md) => {
        const { default: Dialog } = await import('@/common/AttachmentPreviewDialog.vue');
        const fetchMock = vi.fn(() => Promise.resolve(response));
        vi.stubGlobal('fetch', fetchMock);
        const ctx = {
            attachment,
            previewUrl: attachment.secureUrl + '&fs_preview=1',
            requestId: 0,
            loading: false,
            markdownHtml: '',
            errorMessage: '',
            $t: (text) => text,
        };
        await Dialog.methods.loadMarkdown.call(ctx);
        return { ctx, fetchMock };
    };

    afterEach(() => vi.unstubAllGlobals());

    it('renders the file when the server sends it', async () => {
        const { ctx } = await load(reply());
        expect(ctx.markdownHtml).toContain('<h1>Hello</h1>');
        expect(ctx.errorMessage).toBe('');
        expect(ctx.loading).toBe(false);
    });

    it('shows the error state for a non-OK response', async () => {
        const { ctx } = await load(reply({ ok: false }));
        expect(ctx.errorMessage).toBe(unavailable);
        expect(ctx.markdownHtml).toBe('');
    });

    it('shows the error state when the request was redirected, such as to the login page', async () => {
        const { ctx } = await load(reply({ redirected: true }));
        expect(ctx.errorMessage).toBe(unavailable);
        expect(ctx.markdownHtml).toBe('');
    });

    it('shows the error state for an HTML response', async () => {
        const { ctx } = await load(reply({ headers: { get: () => 'text/html; charset=UTF-8' } }));
        expect(ctx.errorMessage).toBe(unavailable);
        expect(ctx.markdownHtml).toBe('');
    });

    it('skips the request when the stored size is over the limit', async () => {
        const { ctx, fetchMock } = await load(reply(), { ...md, file_size: String(MAX + 1) });
        expect(fetchMock).not.toHaveBeenCalled();
        expect(ctx.errorMessage).toBe(unavailable);
    });

    it('rejects a file over the limit when no size is stored', async () => {
        const { ctx, fetchMock } = await load(reply({ text: () => Promise.resolve('a'.repeat(MAX + 1)) }), { ...md, file_size: null });
        expect(fetchMock).toHaveBeenCalledOnce();
        expect(ctx.errorMessage).toBe(unavailable);
        expect(ctx.markdownHtml).toBe('');
    });
});
