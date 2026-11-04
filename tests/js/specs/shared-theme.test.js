// @vitest-environment jsdom
// Admin dark mode on the shared Fluent theme: the old fs-theme choice carries over once,
// only the shared class is set, and the shared event fires for editors and charts.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

const setOsDark = (dark) => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: dark, addEventListener: () => {} });
};

const loadTheme = async () => {
    vi.resetModules();
    return (await import('@/admin/Bits/Theme.js')).default;
};

// fs-dark-mode is the pre-shared class; it must never come back.
const classes = (el) => ['fluent_theme_dark', 'fs-dark-mode'].filter(c => el.classList.contains(c));

describe('shared Fluent admin theme', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.className = '';
        document.body.className = '';
        setOsDark(false);
    });

    it('carries a Dark choice made under the old fs-theme key over to the shared key', async () => {
        localStorage.setItem('fs-theme', 'dark');

        const Theme = await loadTheme();

        expect(Theme.getCurrentTheme()).toBe('dark');
        expect(localStorage.getItem('fluent_theme_mode')).toBe('dark');
        expect(localStorage.getItem('fs-theme')).toBeNull();
        expect(classes(document.body)).toEqual(['fluent_theme_dark']);
        expect(classes(document.documentElement)).toEqual(['fluent_theme_dark']);
    });

    it('defaults to System and follows the OS', async () => {
        setOsDark(true);

        const Theme = await loadTheme();

        expect(Theme.getCurrentTheme()).toBe('system');
        expect(Theme.isDark()).toBe(true);
        expect(localStorage.getItem('fluent_theme_mode')).toBe('system:dark');
    });

    it('a pick sets the shared class and fires fluent_theme_changed, never the old event', async () => {
        const Theme = await loadTheme();
        const shared = vi.fn();
        const old = vi.fn();
        window.addEventListener('fluent_theme_changed', shared);
        window.addEventListener('fs-theme-changed', old);

        Theme.apply('dark');
        expect(classes(document.body)).toEqual(['fluent_theme_dark']);
        expect(shared).toHaveBeenLastCalledWith(expect.objectContaining({ detail: { mode: 'dark' } }));

        Theme.apply('light');
        expect(classes(document.body)).toEqual([]);
        expect(localStorage.getItem('fluent_theme_mode')).toBe('light');
        expect(old).not.toHaveBeenCalled();

        window.removeEventListener('fluent_theme_changed', shared);
        window.removeEventListener('fs-theme-changed', old);
    });

    it('no stylesheet or component targets the pre-shared fs-dark-mode class', () => {
        const root = path.resolve(__dirname, '../../../resources');
        const hits = [];
        const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).forEach((entry) => {
            const file = path.join(dir, entry.name);
            if (entry.isDirectory()) {
                walk(file);
            } else if (/\.(scss|css|vue|js)$/.test(entry.name) && entry.name !== 'fluent-theme.js'
                && fs.readFileSync(file, 'utf8').includes('fs-dark-mode')) {
                hits.push(path.relative(root, file));
            }
        });
        walk(root);

        expect(hits).toEqual([]);
    });

    it('a choice made in another Fluent plugin (storage event) applies here', async () => {
        await loadTheme();

        localStorage.setItem('fluent_theme_mode', 'dark');
        window.dispatchEvent(new StorageEvent('storage', { key: 'fluent_theme_mode' }));

        expect(classes(document.body)).toEqual(['fluent_theme_dark']);
    });
});
