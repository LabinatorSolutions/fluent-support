# Fluent Support

WordPress helpdesk and support ticket plugin.

## Development Setup

```bash
pnpm install
```
`pnpm-lock.yaml` is the lockfile. The `npm run …` commands below work the same as `pnpm run …`.

### Dev mode (Vite HMR on port 5175)
```bash
npm run dev
```
This starts the Vite dev server with hot module replacement. PHP serves assets from `http://localhost:5175` when the server is running, and falls back to built assets when it's not.

### Production build
```bash
npm run build
```
Builds both the admin app (code-split with vendor chunks) and the customer portal (standalone bundle).

### Individual builds
```bash
npm run build:admin    # Admin app only
npm run build:portal   # Customer portal only
npm run build:block    # Gutenberg block (React, separate from Vite)
```

### RTL CSS
```bash
npm run rtl
```
Generates `-rtl.css` variants from all built CSS files.

## Build Architecture

The frontend uses **Vite** with two separate build configs:

- **Admin app** (`vite.config.mjs`) — Vue 3 SPA with Element Plus, code-split into `start.js` + shared vendor chunks. Entry: `resources/admin/start.js`
- **Customer portal** (`vite.config.portal.mjs`) — Vue 3 SPA, standalone single-file bundle with all dependencies inlined. Entry: `resources/customer_portal/portal.js`
- **Block editor** — React/JSX Gutenberg block, built separately via `@wordpress/scripts`

### Output structure
```
assets/
  admin/
    start.js              # Admin Vue app
    global_admin.js        # Admin menu interactions
    global_summary.js      # Admin bar widget
    css/
      alpha-admin.css      # Admin styles
      style.css            # Merged vendor/component CSS
      all_public.css       # Login/signup styles
  customer_portal/
    portal.js              # Standalone portal app
    login_helper.js        # Login form handler
  portal/css/
    app.css                # Portal styles
  vendor.js                # Shared vendor libs (admin only)
  vendor-element-plus.js   # Element Plus (admin only)
  block-editor/customer-portal/
    block.json             # Gutenberg block metadata (registered from here)
    index.js               # Gutenberg block editor script
```

### Vite dev/prod mode
The `config/app.php` `env` key controls mode (`dev` or `production`). The `npm run dev` and `npm run build` scripts set this automatically via `vite-mode.js`. In dev mode, the `Vite.php` helper checks if the dev server is running and proxies assets from it; if the server is down, it falls back to built assets.

## Translations

Vue strings use `$t('text')`, `$_n('%s ticket', '%s tickets', n)` and `$_x('Open', 'Ticket status')` (helpers in `resources/admin/Bits/i18n.js` and `resources/customer_portal/i18n.js`). `i18n.node.js` collects the literal calls, plus the long texts in `resources/long_translations.json`, into the generated `app/Services/TranslationStrings.php`, which PHP localizes for each app and the POT picks up.

```bash
npm run translate         # regenerate TranslationStrings.php, then lint it
npm run translate:check   # lint only
```

Run `translate` after adding or changing UI strings: the static test tier fails on a stale map. The lint also rejects split sentences, em/en dashes, template literals and placeholder strings without a `translators:` comment. See `docs/js-translations.md`.

## Release Build

```bash
bash build.sh --node-build --rtl_css --with-pro   # full release; no flags = interactive
```

Requires node/pnpm, PHP, Composer and WP-CLI.

1. Refreshes `TranslationStrings.php`, runs the translation lint, and regenerates `language/fluent-support.pot` (and Pro's) with `wp i18n make-pot`. `--skip-i18n` skips this step.
2. With `--node-build`, cleans and rebuilds `assets/`. With `--rtl_css`, adds `-rtl.css` variants.
3. Copies the files listed in `dev/whitelist.sh` to `builds/fluent-support/`. All release-only changes are made in that copy, so your working tree is never touched:
   - forces `env=production` in `config/app.php`;
   - dumps a `--no-dev --classmap-authoritative` Composer autoloader;
   - adds `index.php` files;
   - removes dotfiles and source maps.
4. Writes `builds/fluent-support-<version>.zip` (and `builds/fluent-support-pro-<version>.zip` with `--with-pro`). The folder inside each zip stays unversioned.

## DB Changelog

- `waiting_since` in fs_tickets has been added

## Run the tests

If you don't have a testing database then please run the following command from your project root:
```bash
bin/install-wp-tests.sh db_name db_user db_pass db_host
```

You may need to install `phpunit` package as dev deps. To run the tests,
just run `phpunit` command in the terminal from project root. To get more
details on `phpunit` type `phpunit --help` on your terminal. You can test
both free and pro version at the same time. All test file names should start
with `test-`. Make sure you have all your test file in the `tests` folder.
You can include or exclude your files in `phpunit.xml.dist` file.
