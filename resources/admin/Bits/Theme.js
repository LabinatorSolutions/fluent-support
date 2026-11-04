import createFluentTheme from './fluent-theme';

// The shared Fluent admin theme: one choice for every Fluent plugin, every tab and the
// FluentHub sidebar. Dark styles hang off `body.fluent_theme_dark`; code a class can't reach
// (editors, charts) listens for the `fluent_theme_changed` window event. `fs-theme` is the
// pre-shared key, migrated once into `fluent_theme_mode`.
const theme = createFluentTheme({
    legacyKeys: ['fs-theme'],
});

class Theme {
    static MODE_LIGHT = 'light';
    static MODE_DARK = 'dark';
    static MODE_SYSTEM = 'system';

    static getCurrentTheme() {
        return theme.mode();
    }

    static isDark() {
        return theme.isDark();
    }

    static isSystem() {
        return theme.mode() === this.MODE_SYSTEM;
    }

    /* A user choice: stores it and tells other tabs and Fluent plugins. */
    static apply(mode) {
        theme.set(mode);
    }
}

export default Theme;
