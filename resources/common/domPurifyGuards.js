// Rules every DOMPurify instance gets before it cleans ticket, AI or widget HTML.
//
// Element Plus classes: ticket/AI content can contain literal `class="el-overlay"` style
// markup (e.g. copied dev-tools output, or an AI draft echoing such content back). The
// admin panel ships Element Plus's real .el-overlay/.el-dialog CSS globally, so such
// classes would otherwise become live full-viewport overlays that break real dialogs
// (like delete confirmations) rendered elsewhere on the page. Any `el-`-prefixed class
// token is stripped before it reaches the DOM.
//
// Link targets: links may open in a new tab (`target="_blank"`), which DOMPurify drops
// by default. Any other target value is removed, and a new-tab link always gets
// rel="noopener noreferrer" so the opened page can't reach back through window.opener.
export function applyPurifyGuards(purifyInstance) {
    if (!purifyInstance || purifyInstance.__fsPurifyGuards) {
        return;
    }

    purifyInstance.addHook('uponSanitizeAttribute', (node, data) => {
        if (data.attrName === 'class' && data.attrValue) {
            data.attrValue = data.attrValue
                .split(/\s+/)
                .filter((cls) => !/^el-/.test(cls))
                .join(' ');
        }

        if (data.attrName === 'target') {
            const isNewTabLink = node.nodeName === 'A' && data.attrValue === '_blank';
            data.forceKeepAttr = isNewTabLink;
            data.keepAttr = isNewTabLink;
        }
    });

    purifyInstance.addHook('afterSanitizeAttributes', (node) => {
        if (node.nodeName !== 'A' || node.getAttribute('target') !== '_blank') {
            return;
        }

        const rel = new Set((node.getAttribute('rel') || '').split(/\s+/).filter(Boolean));
        rel.add('noopener');
        rel.add('noreferrer');
        node.setAttribute('rel', Array.from(rel).join(' '));
    });

    purifyInstance.__fsPurifyGuards = true;
}

// FORCE_BODY keeps a widget's <style> block even when it is the first node.
export function sanitizeWidgetHtml(html) {
    applyPurifyGuards(window.DOMPurify);

    return window.DOMPurify.sanitize(html || '', {
        FORCE_BODY: true
    });
}
