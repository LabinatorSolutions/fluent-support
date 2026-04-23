# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Fluent Support is a WordPress helpdesk/support ticket plugin with a modern architecture combining PHP (backend) and Vue 3 (frontend). The plugin uses a custom Laravel-like framework (WPFluent) for routing, ORM, and dependency injection.

## Build Commands

### Frontend Development (Vite)
```bash
npm install --legacy-peer-deps   # Install dependencies
npm run dev                      # Start Vite dev server with HMR (port 5175)
npm run build                    # Production build (admin + portal)
npm run build:admin              # Build admin app only
npm run build:portal             # Build customer portal only
npm run build:block              # Build Gutenberg block (React, separate from Vite)
npm run rtl                      # Generate RTL CSS variants
npm run watch                    # Vite build in watch mode (no HMR)
```

### Release Build
```bash
sh build.sh --node-build --rtl_css --with_pro
```

### PHP Testing
```bash
# First-time setup: Install test database
bin/install-wp-tests.sh db_name db_user db_pass db_host

# Run tests
phpunit

# PHPUnit is required as dev dependency
```

### Code Standards
```bash
# PHP Code Sniffer (WordPress Coding Standards with customizations)
phpcs --standard=.phpcs.xml
```

## Architecture

### Entry Point & Initialization
- **Main plugin file**: `fluent-support.php`
- **Bootstrap**: `boot/app.php` creates the Application instance
- **Configuration**: `config/app.php` (plugin metadata, REST namespace, hook prefixes)
- **Autoloading**: Composer PSR-4 autoloader loads `FluentSupport\App\*` from `app/` and `FluentSupport\Framework\*` from `vendor/wpfluent/framework/`

### Backend (PHP)

**MVC + Service Layer Pattern**:
- **Models** (`app/Models/`): ORM models extending WPFluent's Eloquent-like base
  - Main models: `Ticket`, `Conversation`, `Customer`, `Agent`, `MailBox`, `Product`, `Tag`, `Attachment`, `Activity`
  - Models support relationships, eager loading, and query scopes

- **Controllers** (`app/Http/Controllers/`): Handle REST API requests (24 controllers)
  - Examples: `TicketController`, `CustomerController`, `SettingsController`, `AgentController`, `AuthController`

- **Services** (`app/Services/`): Business logic layer (22 service classes)
  - `Helper.php`: Global helper functions (55KB)
  - `TicketHelper.php`: Ticket-specific helpers
  - `CustomerPortalService.php`: Portal logic
  - `Tickets/`: Ticket-specific services
  - `Integrations/`: Third-party integrations (Slack, Telegram, Firebase, etc.)
  - `EmailNotification/`: Email notification system

- **Policies** (`app/Http/Policies/`): Authorization layer
  - Examples: `AdminSettingsPolicy`, `AgentTicketPolicy`, `PortalPolicy`, `PublicPolicy`

- **Hooks** (`app/Hooks/`):
  - `Handlers/`: 23 event handler classes (AuthHandler, EmailNotificationHandler, Menu, ActivityLogger, etc.)
  - `actions.php`, `filters.php`: WordPress hook registrations

- **Modules** (`app/Modules/`): Feature modules
  - `PermissionManager.php`: Role/permission system
  - `StatModule.php`: Statistics
  - `Reporting/`: Analytics

### Frontend (Vue 3)

**Two Separate Single-Page Applications**:

1. **Admin Panel** (`resources/admin/`) — built via `vite.config.mjs`
   - Entry: `start.js` → builds to `assets/admin/start.js` (code-split with vendor chunks)
   - Root component: `Application.vue`
   - Router: `routes.js` (11+ routes)
   - UI library: Element Plus v2.9.7
   - Date library: dayjs (available as `this.$dayjs()` in components)
   - Modules (11 feature modules):
     - `Dashboard/`: Main dashboard
     - `Tickets/`: Ticket management UI
     - `Settings/`: 26+ settings pages
     - `Customers/`: Customer management
     - `Reports/`: Analytics UI
     - `MailBoxes/`: Email configuration
     - `SavedReplies/`: Canned responses
     - `Workflows/`: Automation UI
     - `ActivityLogger/`: Activity log viewer
   - Framework wrapper: `Bits/FluentFramework.js` provides global mixins for REST, hooks, utilities

2. **Customer Portal** (`resources/customer_portal/`) — built via `vite.config.portal.mjs`
   - Entry: `portal.js` → builds to `assets/customer_portal/portal.js` (standalone bundle, all deps inlined)
   - Root component: `Application.vue`
   - Customer-facing ticket management
   - Separate REST client and auth system

3. **WordPress Block** (`resources/block-editor/`) — built via `@wordpress/scripts`
   - React-based Gutenberg block for portal shortcode
   - Built with `@wordpress/block-editor`
   - Separate from Vite pipeline, run `npm run build:block`

### REST API

- **Base namespace**: `fluent-support/v2`
- **Routes**: Defined in `app/Http/Routes/api.php` (249+ lines)
- **Authorization**: Policy-based using WPFluent Router's `withPolicy()` method
- **Nonce protection**: WordPress nonce in `X-WP-Nonce` header
- **HTTP methods**: Supports GET, POST, PUT, PATCH, DELETE via method override pattern

Example route structure:
```php
$router->prefix('tickets')
    ->withPolicy('AgentTicketPolicy')
    ->group(function ($router) {
        $router->get('/', 'TicketController@index');
        $router->post('/', 'TicketController@createTicket');
        $router->get('/{ticket_id}', 'TicketController@getTicket')->int('ticket_id');
    });
```

### Frontend-Backend Communication

**REST Client** (`Rest.js` in both admin and portal):
- Uses jQuery AJAX with automatic nonce handling
- Injected globals:
  - Admin: `window.fluentSupportAdmin` (REST URL, nonce, permissions, i18n)
  - Portal: `window.fs_customer_portal`
- Auto-refreshes nonce on 401 responses

**Vue Framework Integration**:
- `FluentFramework.js` wraps Vue 3 app with global methods
- REST methods: `$get()`, `$post()`, `$put()`, `$patch()`, `$del()`
- WordPress hooks API: `addFilter()`, `applyFilters()`, `addAction()`, `doAction()`
- Utilities: date formatting, slugify, currency, notifications

### Database

**Tables** (prefix: `wp_fs_`):
- `fs_tickets`: Main ticket table
- `fs_conversations`: Ticket responses/messages
- `fs_persons`: Unified customer/agent table
- `fs_agents`: Agent configuration
- `fs_mailboxes`: Email box settings
- `fs_products`: Product/category system
- `fs_tags`, `fs_taggables`, `fs_tag_relations`: Tag system
- `fs_attachments`: File uploads
- `fs_activity`: Activity logging
- `fs_ai_activity_logs`: AI usage tracking
- `fs_meta`: Key-value metadata

**Migrations**: Located in `database/Migrations/` (13 migration classes)
- Run via `database/DBMigrator.php` on plugin activation

### Build System (Vite)

**Two Vite configs**:
- `vite.config.mjs` — Admin app build (code-split with vendor chunks)
- `vite.config.portal.mjs` — Customer portal build (standalone single-file bundle)

**Vite plugins**:
- `@vitejs/plugin-vue`: Vue 3 SFC support
- `unplugin-auto-import`: Auto-imports Element Plus components
- `unplugin-vue-components`: Auto-registers Vue components
- `vite-plugin-static-copy`: Copies images and libs to assets/
- Custom plugins: manifest→PHP converter, CSS chunk merger, cache-bust query strings

**Path aliases**: `@` → `resources/`, `~element-plus` → `node_modules/element-plus`

**Compiled assets**:
- Admin JS: `resources/admin/start.js` → `assets/admin/start.js` + `assets/vendor.js` + `assets/vendor-element-plus.js`
- Portal JS: `resources/customer_portal/portal.js` → `assets/customer_portal/portal.js` (standalone)
- Gutenberg Block: `resources/block-editor/blocks/customer-portal/index.js` → `assets/block-editor/js/fs_block.js`
- Global scripts: `assets/admin/global_admin.js`, `assets/admin/global_summary.js`, `assets/customer_portal/login_helper.js`
- Admin CSS: `assets/admin/css/alpha-admin.css` (main) + `assets/admin/css/style.css` (merged vendor/component CSS)
- Portal CSS: `assets/portal/css/app.css`
- Static: `resources/images` → `assets/images`, `resources/libs` → `assets/libs`

**Asset resolution (PHP)**:
- `app/Vite.php` handles dev/prod asset resolution via `Vite::getEnqueuePath()`
- Dev mode: serves from Vite dev server (`http://localhost:5175`) with HMR, falls back to built assets if server is down
- Production: resolves paths via manifest in `config/vite_config.php`
- Mode controlled by `config/app.php` `env` key (`dev` or `production`), set automatically by `vite-mode.js`

**Development vs Production**:
- Development: Vite dev server with HMR on port 5175, `Vite::injectViteClient()` adds HMR script
- Production: Minified with terser, console.log stripped, no source maps

## Key Patterns

### Routing Pattern
Routes are fluent and chainable:
```php
$router->prefix('tickets')
    ->withPolicy('AgentTicketPolicy')
    ->group(function ($router) {
        $router->get('/{ticket_id}', 'TicketController@getTicket')->int('ticket_id');
    });
```

### Model Relationships
Models use Laravel-style relationships:
```php
// In Ticket model
public function customer() {
    return $this->belongsTo(Customer::class, 'customer_id');
}
```

### Permission Checking
Use `PermissionManager` for authorization:
```php
Helper::checkPermission('fst_manage_settings');
```

### WordPress Hooks
Custom hooks follow pattern `fluent_support/{event_name}`:
```php
do_action('fluent_support/ticket_created', $ticket, $customer);
```

### Vue Component Auto-Import
Element Plus components are auto-imported (no manual imports needed):
```vue
<template>
  <el-button @click="handleClick">Button</el-button>
</template>
```

## Development Workflow

1. **Start Vite dev server**: `npm run dev` (enables HMR on port 5175)
2. **Make changes** to Vue components in `resources/` — changes hot-reload instantly
3. **PHP changes** are immediate (no build needed)
4. **Production build**: `npm run build` (builds both admin + portal)
5. **RTL**: `npm run rtl` after production build to generate RTL CSS variants

## Testing

### PHP Unit Tests
- Test files: `tests/test-*.php`
- Bootstrap: `tests/bootstrap.php`
- Configuration: `phpunit.xml.dist`
- Test database setup required before first run

### Frontend Testing
No automated frontend tests currently configured.

## WordPress Integration

### Plugin Activation
- Handler: `app/Hooks/Handlers/ActivationHandler.php`
- Runs migrations
- Creates default mailbox
- Sets default settings
- Creates admin role

### Cron Tasks
- WordPress cron: hourly, daily, weekly tasks
- Action Scheduler: half-hourly tasks (uses WooCommerce Action Scheduler library)

### Shortcodes
- `[fluent_support_portal]`: Renders customer portal

### Admin Menu
- Handler: `app/Hooks/Handlers/Menu.php`
- Registers admin menu items and enqueues assets

## Code Style

### PHP
- WordPress Coding Standards with customizations (see `.phpcs.xml`)
- 4 spaces indentation (no tabs)
- Short array syntax `[]` allowed
- PSR-4 autoloading

### Vue/JavaScript
- Vue 3 Composition API and Options API both used
- ES6+ syntax
- Element Plus UI components
- Single File Components (`.vue`)

## Important Notes

- **Don't edit `assets/` directly**: These are built from `resources/`
- **Models use WPFluent ORM**: Not standard Laravel Eloquent (some differences)
- **REST nonce handling**: Automatic in `Rest.js`, manual refresh on 401
- **Two separate Vue apps**: Admin and Portal are independent SPAs with separate Vite configs
- **Portal is standalone**: All dependencies inlined — no shared vendor chunks with admin
- **Asset loading via `Vite.php`**: Use `Vite::getEnqueuePath()` for JS/CSS, not raw `$assets` paths
- **Composer autoloader**: Run `composer dump-autoload` after adding new classes
- **Database changes**: Create migration in `database/Migrations/`
- **No moment.js**: Use dayjs — available as `this.$dayjs()` in Vue components or `import dayjs from 'dayjs'`
- **ESM only**: Use `import`/`export`, not `require()` — Vite outputs ES modules

## Common Tasks

### Add a new REST endpoint
1. Add route in `app/Http/Routes/api.php`
2. Create/update controller method in `app/Http/Controllers/`
3. Add policy check if needed in `app/Http/Policies/`

### Add a new admin page
1. Create component in `resources/admin/Modules/{ModuleName}/`
2. Add route in `resources/admin/routes.js`
3. Build assets: `npm run dev` or `npm run build`

### Add a new model
1. Create model in `app/Models/`
2. Extend `FluentSupport\App\Models\Model`
3. Create migration in `database/Migrations/`
4. Run migration on next activation or manually

### Add a new service
1. Create service in `app/Services/`
2. Use in controllers or other services
3. Consider adding to service container in `boot/bindings.php` if needed

### Debug REST API
- Check browser console for failed requests
- Verify nonce is being sent in `X-WP-Nonce` header
- Check policy authorization in `app/Http/Policies/`
- Enable WordPress debug mode: `define('WP_DEBUG', true);`
