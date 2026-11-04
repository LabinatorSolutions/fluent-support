/**
 * Keyboard shortcut helpers shared by the ticket list and ticket view.
 *
 * Shortcuts are written for macOS as ⌘ + ⌥ / ⌘ + ⇧. Windows and Linux use
 * Ctrl in place of ⌘, because the Windows key combinations are taken by the
 * operating system. ⌘ (metaKey) is still accepted everywhere so nothing that
 * worked before stops working.
 */

export function isMacPlatform() {
    const platform = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || navigator.userAgent || '';
    return /mac|iphone|ipad/i.test(platform);
}

/**
 * @param {KeyboardEvent} event
 * @param {boolean} [mac]
 * @returns {{primary: boolean, alt: boolean, shift: boolean}}
 */
export function shortcutModifiers(event, mac = isMacPlatform()) {
    return {
        primary: !!event.metaKey || (!mac && !!event.ctrlKey),
        alt: !!event.altKey,
        shift: !!event.shiftKey
    };
}

/**
 * True when the key press belongs to a field the user is typing in. Ctrl + Alt
 * is AltGr on many keyboard layouts (AltGr + Q types "@" on German ones), so
 * shortcuts must never fire from a text field.
 *
 * @param {EventTarget|null} target
 */
export function isTypingTarget(target) {
    if (!target || !target.closest) {
        return false;
    }
    return !!target.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"]');
}

/**
 * The ticket before or after `currentId` in `tickets`, or null at either end
 * or when the current ticket is not in the list.
 *
 * @param {Array} tickets
 * @param {number|string} currentId
 * @param {1|-1} step
 */
export function adjacentTicket(tickets, currentId, step) {
    const list = tickets || [];
    const index = list.findIndex(ticket => String(ticket.id) === String(currentId));
    if (index === -1) {
        return null;
    }
    return list[index + step] || null;
}
