import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { applyPurifyGuards } from '@/common/domPurifyGuards';

applyPurifyGuards(DOMPurify);

/**
 * Markdown to sanitized HTML. Single newlines become <br> (chat-style text).
 */
export function parseMarkdown(text) {
    if (!text) return '';
    try {
        return DOMPurify.sanitize(marked.parse(text, { gfm: true, breaks: true }));
    } catch (e) {
        return DOMPurify.sanitize(text.replace(/\n/g, '<br>'));
    }
}

// What Markdown renders, plus common inline HTML. Anything else (images, media, <style>,
// forms, background attributes) could load from or send to another site.
const MARKDOWN_FILE_TAGS = [
    'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'dd', 'del', 'details', 'div', 'dl', 'dt',
    'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'input', 'ins', 'kbd', 'li', 'mark', 'ol', 'p',
    'pre', 's', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead',
    'tr', 'u', 'ul',
];
const MARKDOWN_FILE_ATTRS = ['align', 'checked', 'class', 'colspan', 'disabled', 'href', 'open', 'rowspan', 'start', 'title', 'type'];

/**
 * An uploaded Markdown file: nothing that loads from another site, and links open in a
 * new tab so the app is not navigated away.
 */
export function parseMarkdownFile(text) {
    const fragment = DOMPurify.sanitize(marked.parse(text || '', { gfm: true }), {
        ALLOWED_TAGS: MARKDOWN_FILE_TAGS,
        ALLOWED_ATTR: MARKDOWN_FILE_ATTRS,
        RETURN_DOM_FRAGMENT: true,
    });

    // Only task-list checkboxes; other input types (image, submit) are not Markdown.
    fragment.querySelectorAll('input:not([type="checkbox"])').forEach((input) => input.remove());

    // Only code-block language classes, so a file cannot borrow the app's own CSS classes.
    fragment.querySelectorAll('[class]').forEach((node) => {
        const kept = Array.from(node.classList).filter((name) => /^language-[\w-]+$/.test(name));
        if (kept.length) {
            node.setAttribute('class', kept.join(' '));
        } else {
            node.removeAttribute('class');
        }
    });

    fragment.querySelectorAll('a[href]').forEach((link) => {
        link.setAttribute('target', '_blank');
        link.setAttribute('rel', 'noopener noreferrer');
    });

    const wrapper = document.createElement('div');
    wrapper.appendChild(fragment);
    return wrapper.innerHTML;
}
