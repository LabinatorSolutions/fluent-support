/**
 * Shared Fluent admin theme (light / dark / system).            @fluent-theme-toolkit v1
 *
 * Every Fluent plugin reads and writes the same `fluent_theme_mode` localStorage key and
 * toggles the same `fluent_theme_dark` class, so one choice applies in every plugin, every
 * tab and the FluentHub sidebar. Copied from fluent-plugins-skills/dark-mode-unify; the
 * rules are in its CONTRACT.md. Fix bugs there, not only in this copy.
 *
 *   import createFluentTheme from './fluent-theme';
 *   const theme = createFluentTheme({
 *       legacyKeys: ['fs-theme'],          // migrated once into the shared key
 *       aliasClasses: ['fs-dark-mode'],    // older classes the plugin's CSS still targets
 *   });
 *   theme.set('dark');                     // user picked a mode
 *   theme.mode();                          // 'light' | 'dark' | 'system'
 *   theme.isDark();
 */
const KEY = 'fluent_theme_mode';
const DARK_CLASS = 'fluent_theme_dark';
const CHANNEL = 'fluent_theme_changed';
const MODES = ['light', 'dark', 'system'];

function storage(fn, fallback) {
    try {
        return fn(window.localStorage);
    } catch (e) {
        return fallback; // Private window or blocked storage: follow the OS, keep going.
    }
}

export default function createFluentTheme(options = {}) {
    const legacyKeys = options.legacyKeys || [];
    const keepLegacy = !!options.keepLegacy; // something else still reads the old key
    const aliasClasses = options.aliasClasses || [];
    const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    const listeners = [];
    let channel = null;

    function systemTheme() {
        return media && media.matches ? 'dark' : 'light';
    }

    /* The selected mode. Unset or unknown means system. */
    function mode() {
        let stored = storage(s => s.getItem(KEY), '') || '';

        if (!stored) {
            const legacy = legacyKeys.map(k => storage(s => s.getItem(k), '')).find(Boolean);
            if (legacy) {
                stored = legacy;
            }
        }

        const selected = stored.split(':')[0];
        return MODES.indexOf(selected) === -1 ? 'system' : selected;
    }

    function resolve(selected) {
        return selected === 'system' ? systemTheme() : selected;
    }

    function mark(isDark) {
        [document.documentElement, document.body].forEach(el => {
            if (!el) {
                return;
            }
            el.classList.toggle(DARK_CLASS, isDark);
            aliasClasses.forEach(cls => el.classList.toggle(cls, isDark));
        });

        if (!document.body) {
            // Loaded in <head>: mark <body> as soon as it exists.
            document.addEventListener('DOMContentLoaded', () => mark(resolve(mode()) === 'dark'), {once: true});
        }
    }

    /* `persist` is false for changes that came from another tab, so they cannot bounce back. */
    function apply(selected, persist) {
        const resolved = resolve(selected);
        mark(resolved === 'dark');

        if (persist) {
            const value = selected === 'system' ? 'system:' + resolved : selected;
            storage(s => {
                s.setItem(KEY, value);
                legacyKeys.forEach(k => (keepLegacy ? s.setItem(k, resolved) : s.removeItem(k)));
            });
        }

        window.dispatchEvent(new CustomEvent(CHANNEL, {detail: {mode: resolved}}));
        listeners.forEach(fn => fn(resolved, selected));
        return resolved;
    }

    function set(selected) {
        if (MODES.indexOf(selected) === -1) {
            selected = 'system';
        }
        apply(selected, true);
        if (channel) {
            channel.postMessage({mode: selected});
        }
    }

    if (typeof BroadcastChannel !== 'undefined') {
        channel = new BroadcastChannel(CHANNEL + ':' + window.location.origin);
        channel.onmessage = event => {
            if (event.data && MODES.indexOf(event.data.mode) !== -1) {
                apply(event.data.mode, false);
            }
        };
    }

    // Another tab, or a plugin that only writes the key.
    window.addEventListener('storage', event => {
        if (event.key === KEY) {
            apply(mode(), false);
        }
    });

    if (media && media.addEventListener) {
        media.addEventListener('change', () => {
            if (mode() === 'system') {
                apply('system', true);
            }
        });
    }

    // Writes back too, which migrates a legacy key and refreshes the system:<resolved> hint.
    apply(mode(), true);

    return {
        mode,
        set,
        resolve: () => resolve(mode()),
        isDark: () => resolve(mode()) === 'dark',
        toggle: () => set(resolve(mode()) === 'dark' ? 'light' : 'dark'),
        onChange: fn => listeners.push(fn),
    };
}
