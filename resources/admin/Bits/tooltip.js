// Lightweight hover tooltip: v-tip="text" (above), v-tip.bottom="text" or v-tip.right="text". An empty value shows nothing.
// One shared element and four delegated listeners serve every trigger. An el-tooltip mounts
// about 8 components plus a teleport per use, which made long ticket lists slow to mount and
// unmount. Use el-tooltip only when the content needs markup or the tooltip must be interactive.
// The element reuses Element Plus's dark popper classes, so it looks the same in light and dark mode.

const texts = new WeakMap();
const placements = new WeakMap();
const GAP = 8;
const EDGE = 8;

let tipEl = null;
let arrowEl = null;
let activeEl = null;
let listening = false;

function ensureTipEl() {
    if (tipEl) {
        return;
    }
    tipEl = document.createElement('div');
    tipEl.id = 'fs_tip';
    tipEl.className = 'el-popper is-dark fs_tip';
    tipEl.setAttribute('role', 'tooltip');
    arrowEl = document.createElement('span');
    arrowEl.className = 'el-popper__arrow';
    document.body.appendChild(tipEl);
}

function position(el) {
    const rect = el.getBoundingClientRect();
    const tipRect = tipEl.getBoundingClientRect();
    let placement = placements.get(el) || 'top';

    if (placement === 'right') {
        const middle = rect.top + rect.height / 2;
        const top = Math.max(EDGE, Math.min(middle - tipRect.height / 2, window.innerHeight - tipRect.height - EDGE));
        tipEl.setAttribute('data-popper-placement', 'right');
        tipEl.style.transform = `translate(${Math.round(rect.right + GAP)}px, ${Math.round(top)}px)`;
        arrowEl.style.left = '';
        arrowEl.style.top = `${Math.round(Math.max(6, Math.min(middle - top - 5, tipRect.height - 16)))}px`;
        return;
    }

    if (placement === 'top' && rect.top - tipRect.height - GAP < 0) {
        placement = 'bottom';
    } else if (placement === 'bottom' && rect.bottom + tipRect.height + GAP > window.innerHeight) {
        placement = 'top';
    }

    const center = rect.left + rect.width / 2;
    const maxLeft = document.documentElement.clientWidth - tipRect.width - EDGE;
    const left = Math.max(EDGE, Math.min(center - tipRect.width / 2, maxLeft));
    const top = placement === 'top' ? rect.top - tipRect.height - GAP : rect.bottom + GAP;

    tipEl.setAttribute('data-popper-placement', placement);
    tipEl.style.transform = `translate(${Math.round(left)}px, ${Math.round(top)}px)`;
    // The arrow keeps pointing at the trigger when the tooltip is pushed off-center at a viewport edge.
    arrowEl.style.top = '';
    arrowEl.style.left = `${Math.round(Math.max(8, Math.min(center - left - 5, tipRect.width - 18)))}px`;
}

function show(el) {
    const text = texts.get(el);
    if (!text) {
        hide();
        return;
    }
    ensureTipEl();
    activeEl = el;
    tipEl.textContent = text;
    tipEl.appendChild(arrowEl);
    tipEl.style.display = 'block';
    el.setAttribute('aria-describedby', 'fs_tip');
    position(el);
}

function hide() {
    if (activeEl) {
        activeEl.removeAttribute('aria-describedby');
        activeEl = null;
    }
    if (tipEl) {
        tipEl.style.display = 'none';
    }
}

function triggerFrom(node) {
    const el = node && node.closest ? node.closest('[data-fs-tip]') : null;
    return el && texts.has(el) ? el : null;
}

function onOver(event) {
    const el = triggerFrom(event.target);
    if (el && el !== activeEl) {
        show(el);
    }
}

function onOut(event) {
    if (activeEl && !activeEl.contains(event.relatedTarget)) {
        hide();
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        hide();
    }
}

function listen() {
    if (listening) {
        return;
    }
    listening = true;
    document.addEventListener('mouseover', onOver);
    document.addEventListener('mouseout', onOut);
    document.addEventListener('focusin', onOver);
    document.addEventListener('focusout', onOut);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('scroll', hide, true);
    window.addEventListener('blur', hide);
}

function bind(el, binding) {
    texts.set(el, binding.value ? String(binding.value) : '');
    placements.set(el, binding.modifiers.bottom ? 'bottom' : (binding.modifiers.right ? 'right' : 'top'));
    el.setAttribute('data-fs-tip', '');
}

export default {
    mounted(el, binding) {
        bind(el, binding);
        listen();
    },
    updated(el, binding) {
        if (binding.value === binding.oldValue) {
            return;
        }
        bind(el, binding);
        if (el === activeEl) {
            show(el);
        }
    },
    beforeUnmount(el) {
        if (el === activeEl) {
            hide();
        }
        texts.delete(el);
    }
};
