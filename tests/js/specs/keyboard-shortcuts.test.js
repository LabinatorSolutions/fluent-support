// @vitest-environment jsdom
// Shortcut modifiers per platform, no shortcuts while typing, and next/previous order.

import { describe, it, expect } from 'vitest';
import { shortcutModifiers, isTypingTarget, adjacentTicket } from '@/admin/Bits/shortcuts';

const key = (props) => ({ metaKey: false, ctrlKey: false, altKey: false, shiftKey: false, ...props });

describe('shortcutModifiers', () => {
    it('uses ⌘ on macOS and ignores Ctrl there', () => {
        expect(shortcutModifiers(key({ metaKey: true, altKey: true }), true)).toEqual({ primary: true, alt: true, shift: false });
        expect(shortcutModifiers(key({ ctrlKey: true, altKey: true }), true).primary).toBe(false);
    });

    it('uses Ctrl on Windows and still accepts the Windows key', () => {
        expect(shortcutModifiers(key({ ctrlKey: true, shiftKey: true }), false)).toEqual({ primary: true, alt: false, shift: true });
        expect(shortcutModifiers(key({ metaKey: true, altKey: true }), false).primary).toBe(true);
    });
});

describe('isTypingTarget', () => {
    it('is true inside inputs, textareas and editable content', () => {
        document.body.innerHTML = '<input id="i"><textarea id="t"></textarea><div contenteditable="true"><span id="c">x</span></div><button id="b"></button>';
        expect(isTypingTarget(document.getElementById('i'))).toBe(true);
        expect(isTypingTarget(document.getElementById('t'))).toBe(true);
        expect(isTypingTarget(document.getElementById('c'))).toBe(true);
        expect(isTypingTarget(document.getElementById('b'))).toBe(false);
        expect(isTypingTarget(null)).toBe(false);
    });
});

describe('adjacentTicket', () => {
    const tickets = [{ id: 5 }, { id: 9 }, { id: 2 }];

    it('steps forward and back in list order', () => {
        expect(adjacentTicket(tickets, 9, 1)).toEqual({ id: 2 });
        expect(adjacentTicket(tickets, '9', -1)).toEqual({ id: 5 });
    });

    it('returns null at either end or for a ticket not in the list', () => {
        expect(adjacentTicket(tickets, 2, 1)).toBeNull();
        expect(adjacentTicket(tickets, 5, -1)).toBeNull();
        expect(adjacentTicket(tickets, 77, 1)).toBeNull();
        expect(adjacentTicket([], 5, 1)).toBeNull();
    });
});
