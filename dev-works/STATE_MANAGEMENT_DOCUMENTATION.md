# State Management Documentation

## Fluent Support — Vue 3 Frontend Architecture Analysis & Pinia Migration Plan

---

## 1. Executive Summary

Fluent Support is a WordPress helpdesk plugin with two independent Vue 3 Single-Page Applications: an **Admin Panel** (157+ components across 9 modules) and a **Customer Portal** (11 components). The entire frontend currently operates **without any dedicated state management library** — no Vuex, no Pinia, no reactive store of any kind.

All state is managed through a combination of:

- **Local component `data()`** — the dominant pattern, with some components managing 25–55+ state properties
- **Global `window.*` objects** — injected from PHP on page load (`window.fluentSupportAdmin`, `window.fs_customer_portal`)
- **A global Vue mixin** — via `FluentFramework.js`, providing REST methods, localStorage helpers, and utility functions
- **`$emit` events** — child-to-parent communication for form updates and action triggers
- **localStorage** — under a single key `__fluentsupport_data` for UI preferences and filter persistence

This architecture has reached its scalability ceiling. The two largest components (`AllTickets.vue` at 1,948 lines and `ViewTicket.vue` at 1,661 lines) contain tightly coupled API logic, filter state, pagination, modal state, and UI flags. Shared domain data (agents, products, tags, statuses, priorities) is duplicated across 15+ components via direct reads from `window.fluentSupportAdmin`.

This document proposes a **phased, non-breaking migration to Pinia** using the **Options API** exclusively, with a service layer for API logic, in-memory caching, and localStorage-backed filter persistence.

---

## 2. Current State Analysis

### 2.1 Application Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        PHP Backend                          │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  window.fluentSupportAdmin = {                       │   │
│  │    rest: { url, nonce },                             │   │
│  │    me: { permissions: [...] },                       │   │
│  │    support_agents: [...],                            │   │
│  │    support_products: [...],                          │   │
│  │    ticket_statuses: {...},                           │   │
│  │    admin_priorities: {...},                          │   │
│  │    client_priorities: {...},                         │   │
│  │    mailboxes: [...],                                 │   │
│  │    has_pro, i18n, asset_url, ...                     │   │
│  │  }                                                   │   │
│  └──────────────────────────────────────────────────────┘   │
│                           │                                 │
│                    Page Load Injection                       │
└───────────────────────────┼─────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Vue 3 SPA (Admin)                        │
│  ┌──────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │ start.js │→ │FluentFramework│→ │  Global Mixin         │ │
│  │          │  │   .js (440L) │  │  $get,$post,$del,...   │ │
│  │          │  │              │  │  $saveData,$getData    │ │
│  │          │  │              │  │  $notify,$handleError  │ │
│  └──────────┘  └──────────────┘  └───────────────────────┘ │
│                           │                                 │
│           ┌───────────────┼──────────────────┐              │
│           ▼               ▼                  ▼              │
│  ┌──────────────┐ ┌─────────────┐ ┌────────────────┐       │
│  │  Dashboard   │ │   Tickets   │ │   Settings     │       │
│  │  (9 comp.)   │ │  (31 comp.) │ │  (31 comp.)    │       │
│  └──────────────┘ └─────────────┘ └────────────────┘       │
│  ┌──────────────┐ ┌─────────────┐ ┌────────────────┐       │
│  │  Customers   │ │   Reports   │ │   Workflows    │       │
│  │  (3 comp.)   │ │  (25 comp.) │ │  (7 comp.)     │       │
│  └──────────────┘ └─────────────┘ └────────────────┘       │
│  ┌──────────────┐ ┌─────────────┐ ┌────────────────┐       │
│  │  MailBoxes   │ │SavedReplies │ │ActivityLogger  │       │
│  │  (6 comp.)   │ │  (1 comp.)  │ │  (5 comp.)     │       │
│  └──────────────┘ └─────────────┘ └────────────────┘       │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Component Inventory

| Module | Components | Largest Component (Lines) |
|--------|-----------|--------------------------|
| Tickets | 31 | AllTickets.vue (1,948), ViewTicket.vue (1,661) |
| Settings | 31 | SupportStaffs.vue (632) |
| Reports | 25 | Overview.vue (1,013) |
| Dashboard | 9 | Dashboard.vue (411) |
| Workflows | 7 | AllWorkflows.vue (534) |
| MailBoxes | 6 | ChooseMailBox.vue (~310) |
| ActivityLogger | 5 | ActivityLogger.vue (~280) |
| Customers | 3 | Customers.vue (515) |
| SavedReplies | 1 | Replies.vue (~350) |
| Shared (Pieces/Components) | 30+ | _wp_editor.vue (508) |
| Customer Portal | 11 | Tickets.vue (~240) |
| **Total** | **~186** | |

### 2.3 State Management Patterns in Use

| Pattern | Usage Level | Locations |
|---------|------------|-----------|
| Local `data()` | Very High | All 186 .vue files |
| Props Drilling | High | Most parent→child relationships |
| `$emit` Events | High | 75+ components |
| `window.*` Globals | High | 2 global objects accessed everywhere |
| localStorage | Medium | 4+ files (via `$saveData`/`$getData`) |
| Global Mixin | Medium | FluentFramework.js → all components |
| Composables | Medium | 4 composable files |
| Watchers | Medium | 6+ major components |
| Custom DOM Events | Low | 3–4 cross-component events |
| Vuex / Pinia | **None** | 0 stores |
| `provide` / `inject` | **None** | 0 usages |

### 2.4 REST API Infrastructure

- **Admin REST Client**: `resources/admin/Bits/Rest.js` (68 lines) — jQuery AJAX wrapper
- **Portal REST Client**: `resources/customer_portal/Rest.js` (240 lines) — XMLHttpRequest-based
- **Total Endpoints**: 80+ REST endpoints across all modules
- **Components with API calls**: 50+ components contain direct `this.$get`/`this.$post` calls
- **Auth**: WordPress nonce via `X-WP-Nonce` header, auto-refresh on 401
- **Method Override**: PUT/PATCH/DELETE sent as POST with `X-HTTP-Method-Override` header

### 2.5 Shared Data Sources (from `window.fluentSupportAdmin`)

| Data | Accessed In | Loading |
|------|------------|---------|
| `support_agents` | 15+ components | PHP page injection |
| `support_products` | 12+ components | PHP page injection |
| `mailboxes` | 8+ components | PHP page injection |
| `ticket_statuses` | 7+ components | PHP page injection |
| `admin_priorities` | 8+ components | PHP page injection |
| `client_priorities` | 8+ components | PHP page injection |
| `ticket_tags` | 6+ components | PHP page injection |
| `me.permissions` | 17+ components | PHP page injection |
| `i18n` | All components | PHP page injection |

---

## 3. Identified Problems

### 3.1 No Centralized State Management

Every component independently manages its own state. There is no single source of truth for any domain entity. When a ticket is updated in `ViewTicket.vue`, the ticket list in `AllTickets.vue` knows nothing about it — requiring a full refetch on navigation.

### 3.2 Monolithic Components

Two components exceed 1,600 lines, mixing API logic, filter state, pagination, UI flags, keyboard shortcuts, and business logic:

- **`AllTickets.vue`** (1,948 lines): 24+ data properties, ~100 methods, watchers on route params and filters
- **`ViewTicket.vue`** (1,661 lines): 55+ data properties, ~80 methods, manages conversations, drafts, merges, splits, watchers, sidebar data

### 3.3 Duplicate Shared Data

Every component that needs agents, products, or priorities reads from `window.fluentSupportAdmin` and copies data into local state:

```javascript
// Repeated in 15+ components
this.agents = this.appVars.support_agents;
this.products = this.appVars.support_products;
```

If shared data is refreshed (via `renewOptions()`), only the `window` object updates — components retain stale local copies.

### 3.4 Inconsistent Permission Checking

Permissions are checked client-side using two inconsistent patterns:

```javascript
// Pattern A (modern)
this.appVars.me.permissions.includes('fst_manage_settings')

// Pattern B (legacy)
this.me.permissions.indexOf('fst_sensitive_data') != -1
```

No route guards exist — all 19+ admin routes are unguarded. Permission checks happen ad-hoc inside component templates and methods.

### 3.5 Tightly Coupled API Logic

API calls are embedded directly in component methods with inline response handling:

```javascript
// ViewTicket.vue — typical tight coupling
updateTicketAttr(propName, propValue) {
    this.$put(`tickets/${this.ticket.id}/property`, { ... })
        .then(response => {
            each(response.update_data, (data, key) => {
                this.ticket[key] = data;
            });
            this.fetchTicket();  // Full refetch after partial update
        });
}
```

This pattern makes it impossible to share mutation logic, deduplicate requests, or implement optimistic updates.

### 3.6 Redundant Refetch Patterns

After mutations (create, update, delete), components always perform a full list refetch:

```javascript
deleteCustomer(id) {
    this.$del(`customers/${id}`)
        .then(() => this.fetchCustomers());  // Full refetch of entire list
}
```

No local array mutation or cache invalidation strategy exists.

### 3.7 Filter/Pagination State Fragility

Each list component independently manages its own filter/pagination state with manual localStorage persistence:

```javascript
// AllTickets.vue
this.$saveData("tickets_filter", this.filters);
this.$saveData("tickets_pref", { order_by, order_type, per_page, ... });
```

URL query params are partially used (e.g., `?status_type=open`) but not consistently synchronized with localStorage.

### 3.8 No Request Deduplication

If a user rapidly toggles filters or navigates, multiple concurrent requests can be fired with no cancellation mechanism. There is no AbortController usage anywhere in the codebase.

### 3.9 Mixed Promise Styles

The codebase uses both jQuery Deferred (`.then().always().catch()`) and modern async/await:

```javascript
// jQuery Deferred (dominant pattern)
this.$get("tickets", query).then(...).always(...).catch(...);

// Modern async/await (Reports module)
const response = await this.$get('reports/overview', { ... });
```

---

## 4. Proposed Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                      Vue 3 App (Admin)                          │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                    Pinia Store Layer                       │  │
│  │                                                           │  │
│  │  ┌─────────────┐ ┌──────────────┐ ┌───────────────────┐  │  │
│  │  │ app.store   │ │ permission   │ │ sharedOptions     │  │  │
│  │  │             │ │   .store     │ │   .store          │  │  │
│  │  │ config,     │ │ permissions, │ │ agents, products, │  │  │
│  │  │ featureFlags│ │ can(), role  │ │ tags, statuses,   │  │  │
│  │  │ appReady    │ │              │ │ priorities, boxes │  │  │
│  │  └─────────────┘ └──────────────┘ └───────────────────┘  │  │
│  │                                                           │  │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────┐  │  │
│  │  │tickets.store │ │customers     │ │savedReplies      │  │  │
│  │  │              │ │  .store      │ │  .store           │  │  │
│  │  │ ticketsById, │ │ list,search, │ │ list, editing,   │  │  │
│  │  │ filters,     │ │ pagination,  │ │ search, cache    │  │  │
│  │  │ pagination,  │ │ activeCustom │ │                   │  │  │
│  │  │ activeTicket │ │              │ │                   │  │  │
│  │  └──────────────┘ └──────────────┘ └──────────────────┘  │  │
│  │                                                           │  │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────┐  │  │
│  │  │ dashboard    │ │ reports      │ │ workflows.store  │  │  │
│  │  │   .store     │ │   .store     │ │                  │  │  │
│  │  └──────────────┘ └──────────────┘ └──────────────────┘  │  │
│  │                                                           │  │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────┐  │  │
│  │  │ mailboxes    │ │ settings     │ │ activityLog      │  │  │
│  │  │   .store     │ │   .store     │ │   .store         │  │  │
│  │  └──────────────┘ └──────────────┘ └──────────────────┘  │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                  │
│                              ▼                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                   Service Layer                            │  │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────┐  │  │
│  │  │ticketService │ │customerSvc   │ │settingsService   │  │  │
│  │  │              │ │              │ │                   │  │  │
│  │  │ fetchList()  │ │ fetchList()  │ │ fetchSettings()  │  │  │
│  │  │ fetchById()  │ │ create()     │ │ saveSettings()   │  │  │
│  │  │ create()     │ │ update()     │ │                   │  │  │
│  │  │ updateProp() │ │ delete()     │ │                   │  │  │
│  │  │ merge()      │ │ bulkDelete() │ │                   │  │  │
│  │  └──────────────┘ └──────────────┘ └──────────────────┘  │  │
│  └───────────────────────────┬───────────────────────────────┘  │
│                              ▼                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                     REST Client                            │  │
│  │              resources/admin/Bits/Rest.js                  │  │
│  │         (jQuery AJAX + nonce + method override)            │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Store Design Pattern

All stores use the **Pinia Options API** pattern exclusively. Below is the canonical store template:

```javascript
// resources/admin/stores/tickets.store.js
import { defineStore } from 'pinia';
import ticketService from '@/admin/services/ticketService';

export const useTicketsStore = defineStore('tickets', {
    state: () => ({
        // Entity data (ID-indexed for O(1) lookups)
        ticketsById: {},

        // List state
        ticketIds: [],
        pagination: {
            per_page: 20,
            current_page: 1,
            total: 0,
        },

        // Filter state
        filters: {
            status_type: 'open',
            agent_id: '',
            product_id: '',
            priority: '',
            client_priority: '',
            mailbox_id: '',
            waiting_for_reply: '',
        },
        search: '',
        filterType: 'simple',
        advancedFilters: [],
        orderBy: 'id',
        orderType: 'DESC',

        // Active entity
        activeTicketId: null,

        // UI state
        loading: false,
        firstLoad: true,

        // Cache metadata
        _lastFetchParams: null,
        _lastFetchTime: null,
    }),

    getters: {
        tickets(state) {
            return state.ticketIds
                .map(id => state.ticketsById[id])
                .filter(Boolean);
        },

        activeTicket(state) {
            return state.activeTicketId
                ? state.ticketsById[state.activeTicketId]
                : null;
        },

        hasStaleCache(state) {
            if (!state._lastFetchTime) return true;
            const STALE_MS = 5 * 60 * 1000; // 5 minutes
            return Date.now() - state._lastFetchTime > STALE_MS;
        },
    },

    actions: {
        async fetchTickets(options = {}) {
            const { silent = false, force = false } = options;

            if (!force && !this.hasStaleCache) return;
            if (!silent) this.loading = true;

            try {
                const params = this._buildQueryParams();
                const response = await ticketService.fetchList(params);

                // Normalize into ID-indexed map
                const byId = {};
                const ids = [];
                response.tickets.data.forEach(ticket => {
                    byId[ticket.id] = ticket;
                    ids.push(ticket.id);
                });

                this.ticketsById = { ...this.ticketsById, ...byId };
                this.ticketIds = ids;
                this.pagination.total = response.tickets.total;
                this._lastFetchParams = params;
                this._lastFetchTime = Date.now();
            } catch (error) {
                throw error; // Let component handle notification
            } finally {
                this.loading = false;
                this.firstLoad = false;
            }
        },

        invalidateCache() {
            this._lastFetchTime = null;
        },

        // ... more actions
    },
});
```

### 5.1 Store Naming Convention

| Store | File | Domain |
|-------|------|--------|
| `useAppStore` | `app.store.js` | Global config, feature flags, environment |
| `usePermissionStore` | `permission.store.js` | Role-based access checks |
| `useSharedOptionsStore` | `sharedOptions.store.js` | Agents, products, tags, statuses, priorities |
| `useTicketsStore` | `tickets.store.js` | Ticket list, filters, pagination, active ticket |
| `useCustomersStore` | `customers.store.js` | Customer list, search, pagination |
| `useSavedRepliesStore` | `savedReplies.store.js` | Canned responses, template cache |
| `useDashboardStore` | `dashboard.store.js` | Dashboard stats, notices |
| `useReportsStore` | `reports.store.js` | Report data, date ranges |
| `useWorkflowsStore` | `workflows.store.js` | Workflow list, active workflow |
| `useMailboxesStore` | `mailboxes.store.js` | Mailbox list, active mailbox settings |
| `useSettingsStore` | `settings.store.js` | Business settings, integrations |
| `useActivityLogStore` | `activityLog.store.js` | Activity/AI log entries, filters |

---

## 6. Recommended Folder Structure

```
resources/admin/
├── Bits/
│   ├── elements.js              (existing — Element Plus setup)
│   ├── FluentFramework.js       (existing — to be gradually thinned)
│   ├── Rest.js                  (existing — unchanged)
│   ├── Errors.js                (existing)
│   ├── firebase.js              (existing)
│   └── TicketActivityService.js (existing)
├── stores/                      (NEW)
│   ├── index.js                 (Pinia instance creation)
│   ├── app.store.js
│   ├── permission.store.js
│   ├── sharedOptions.store.js
│   ├── tickets.store.js
│   ├── customers.store.js
│   ├── savedReplies.store.js
│   ├── dashboard.store.js
│   ├── reports.store.js
│   ├── workflows.store.js
│   ├── mailboxes.store.js
│   ├── settings.store.js
│   └── activityLog.store.js
├── services/                    (NEW)
│   ├── ticketService.js
│   ├── customerService.js
│   ├── savedReplyService.js
│   ├── dashboardService.js
│   ├── reportService.js
│   ├── workflowService.js
│   ├── mailboxService.js
│   ├── settingsService.js
│   ├── activityLogService.js
│   └── productService.js
├── Composable/                  (existing)
├── Modules/                     (existing — components)
├── Components/                  (existing — shared components)
├── Pieces/                      (existing — form elements, modals)
├── start.js                     (existing — add Pinia registration)
└── routes.js                    (existing — add route guards)
```

---

## 7. Caching Strategy

### 7.1 In-Memory Cache (Pinia State)

All entity data lives in Pinia store state as ID-indexed maps:

```javascript
state: () => ({
    ticketsById: {},      // { 1: {...}, 2: {...} }
    _lastFetchTime: null, // Cache timestamp
})
```

**Cache Rules:**

1. **Stale After**: 5 minutes for list data; immediately stale after any mutation
2. **On SPA Navigation Back**: Check `_lastFetchTime`. If stale, perform a **silent background refresh** (no loading spinner). If fresh, use cached data instantly.
3. **After Mutation**: Call `invalidateCache()` and optionally refetch

### 7.2 localStorage (Filter Preferences Only)

localStorage should **only** store UI preferences and filter defaults — never entity data:

| Key | Data | Module |
|-----|------|--------|
| `tickets_filter` | Filter selections | Tickets |
| `tickets_pref` | Sort order, per_page | Tickets |
| `tickets_filter_type` | simple/advanced | Tickets |
| `ticketsMenuCollapsed` | Sidebar state | Tickets |
| `fieldVisibility` | Column visibility | Tickets |
| `ticketLayout` | default/compact | Tickets |

### 7.3 Cache Invalidation

```
┌──────────────┐       ┌──────────────────┐
│   Mutation    │──────>│ invalidateCache()│
│ (create/     │       │                  │
│  update/     │       │ _lastFetchTime   │
│  delete)     │       │   = null         │
└──────────────┘       └────────┬─────────┘
                                │
                                ▼
                    ┌───────────────────────┐
                    │  Next read triggers   │
                    │  fresh fetch          │
                    └───────────────────────┘
```

---

## 8. Filter & URL Sync Strategy

### 8.1 Priority Chain

```
URL Query Params  >  localStorage  >  Default Values
   (highest)          (medium)         (lowest)
```

### 8.2 Implementation

On component mount:
1. Read URL query params (e.g., `?status_type=open&agent_id=5`)
2. If URL params exist, use them (override everything)
3. Else, read from localStorage via store action
4. Else, use store defaults

On filter change:
1. Update store state
2. Persist to localStorage
3. Update URL query params (via `router.replace`)
4. Trigger fetch

```javascript
// In store action
setFilters(filters, router) {
    Object.assign(this.filters, filters);
    this.pagination.current_page = 1;

    // Persist to localStorage
    const savedData = JSON.parse(
        localStorage.getItem('__fluentsupport_data') || '{}'
    );
    savedData['tickets_filter'] = this.filters;
    localStorage.setItem('__fluentsupport_data', JSON.stringify(savedData));

    // Sync to URL
    if (router) {
        router.replace({ query: this._buildUrlQuery() });
    }
}
```

---

## 9. Permission Handling Strategy

### 9.1 Centralized Permission Store

```javascript
// resources/admin/stores/permission.store.js
import { defineStore } from 'pinia';

export const usePermissionStore = defineStore('permission', {
    state: () => ({
        permissions: [],
        userId: null,
        role: '',
    }),

    getters: {
        can: (state) => (permission) => {
            return state.permissions.includes(permission);
        },
        canAny: (state) => (permissionList) => {
            return permissionList.some(p => state.permissions.includes(p));
        },
        canAll: (state) => (permissionList) => {
            return permissionList.every(p => state.permissions.includes(p));
        },
    },

    actions: {
        initialize(appVars) {
            this.permissions = appVars.me?.permissions || [];
            this.userId = appVars.me?.id;
            this.role = appVars.me?.role || '';
        },
    },
});
```

### 9.2 Usage in Components

```javascript
// Replace inconsistent .includes() / .indexOf() patterns
import { mapState } from 'pinia';
import { usePermissionStore } from '@/admin/stores/permission.store';

export default {
    computed: {
        ...mapState(usePermissionStore, ['can']),
    },
    // Template: v-if="can('fst_manage_settings')"
}
```

### 9.3 Route Guards (New)

```javascript
// In routes.js or start.js
router.beforeEach((to, from, next) => {
    const permissionStore = usePermissionStore();
    const requiredPermission = to.meta?.permission;
    if (requiredPermission && !permissionStore.can(requiredPermission)) {
        next({ name: 'dashboard' });
    } else {
        next();
    }
});
```

---

## 10. API Service Layer Strategy

### 10.1 Service Pattern

Each domain gets a service file that wraps REST calls:

```javascript
// resources/admin/services/ticketService.js
import Rest from '@/admin/Bits/Rest';

export default {
    fetchList(params) {
        return Rest.get('tickets', params);
    },

    fetchById(id, params = {}) {
        return Rest.get(`tickets/${id}`, params);
    },

    create(data) {
        return Rest.post('tickets', data);
    },

    updateProperty(id, propName, propValue) {
        return Rest.put(`tickets/${id}/property`, {
            prop_name: propName,
            prop_value: propValue,
        });
    },

    close(id) {
        return Rest.post(`tickets/${id}/close`);
    },

    merge(id, data) {
        return Rest.post(`tickets/${id}/merge_tickets`, data);
    },

    split(id, data) {
        return Rest.post(`tickets/${id}/split_ticket`, data);
    },

    bulkAction(data) {
        return Rest.post('tickets/bulk-actions', data);
    },

    bulkDelete(data) {
        return Rest.delete('tickets/bulk', data);
    },

    fetchDraft(id) {
        return Rest.get(`tickets/${id}/draft`);
    },

    deleteDraft(id) {
        return Rest.delete(`tickets/${id}/draft`);
    },
};
```

### 10.2 Benefits

1. **Single location** for all endpoint URLs per domain
2. **Easily testable** — mock the service, not jQuery AJAX
3. **Reusable** — stores and components can both call services
4. **Consistent** — parameter formatting happens in one place

---

## 11. Migration Strategy (Phased, Non-Breaking)

### Phase 1 — Foundation (1–2 days)

Install Pinia, register in app entry, create directory structure. No component changes.

### Phase 2 — Shared Stores (2–3 days)

Create `app.store.js`, `permission.store.js`, and `sharedOptions.store.js`. Hydrate from `window.fluentSupportAdmin`. Components can start reading from stores instead of `this.appVars.*` — but the old pattern still works.

### Phase 3 — Module Stores (per module, 2–5 days each)

Migrate one module at a time, starting with the highest-impact:

1. **Tickets** (highest complexity, highest benefit)
2. **Customers** (simpler CRUD, good validation of patterns)
3. **Saved Replies** (simple store with caching)
4. **Dashboard** (aggregated data)
5. **Reports** (complex but isolated)
6. **Workflows** (moderate complexity)
7. **MailBoxes** (moderate complexity)
8. **Settings** (multiple sub-pages, shared store)
9. **ActivityLogger** (simple list)

For each module:
- Create service file
- Create store file
- Migrate `data()` state to store
- Replace `this.$get`/`this.$post` calls with store actions
- Wire components via `mapState`/`mapActions`/`mapWritableState`
- Test thoroughly

### Phase 4 — Cleanup (ongoing)

- Remove duplicate local state
- Reduce prop drilling using stores
- Replace `$emit`-based refresh with store invalidation
- Add route guards
- Remove legacy `window.*` reads in favor of stores

### Phase 5 — Optimization (after stabilization)

- Add AbortController to service layer
- Add request deduplication
- Add optimistic updates for common mutations
- Add background refresh debouncing

**Critical Rule**: At every phase, the existing codebase must continue to work. Components that have not been migrated must still function with the old patterns. No big-bang rewrites.

---

## 12. Performance Considerations

### 12.1 Bundle Size Impact

- Pinia: ~1.5 KB gzipped (minimal impact)
- Service files: Code already exists in components, just relocated

### 12.2 Memory

- ID-indexed maps grow linearly with data. For typical helpdesk usage (20–100 tickets per page), this is negligible.
- Stores should clear entity data on logout or when navigating to unrelated modules (optional).

### 12.3 Reactivity

- Pinia state is reactive. Using `mapState` in components provides automatic re-rendering.
- Avoid deeply nested reactive objects where possible. Prefer flat state shapes.

### 12.4 Network

- Silent background refresh prevents visible loading spinners on SPA navigation
- Cache prevents redundant API calls when navigating back to previously visited pages
- AbortController prevents wasted requests when filters change rapidly

---

## 13. Risk Assessment & Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Breaking existing functionality during migration | Medium | High | Migrate one module at a time. Keep old patterns working alongside new. Thorough regression testing per module. |
| Store state becoming stale or inconsistent | Medium | Medium | Clear cache invalidation rules. Always invalidate after mutations. Use `_lastFetchTime` timestamps. |
| Bundle size increase | Low | Low | Pinia is 1.5 KB gzipped. Service layer is code relocation, not addition. |
| Developer learning curve | Low | Medium | Options API stores are familiar to anyone who knows Vue Options API. Provide clear examples and patterns. |
| localStorage conflicts | Low | Medium | Continue using existing `__fluentsupport_data` key structure. Store only filter/preference data. |
| jQuery AJAX compatibility | Low | Low | Service layer wraps existing Rest.js. No change to underlying HTTP client. |
| Pro plugin compatibility | Medium | High | Maintain all WordPress hooks (`applyFilters`, `addAction`). Expose stores globally for extension access. |

---

## 14. Testing Strategy

### 14.1 Store Unit Tests

Each store should have unit tests covering:
- Initial state
- Getter computations
- Action side effects (API calls via mocked services)
- Cache invalidation logic
- Filter persistence

### 14.2 Integration Testing

- Component renders with store-provided data
- Filter changes trigger store actions
- Pagination works through store
- Mutations invalidate cache
- Navigation between modules preserves/clears state correctly

### 14.3 Manual Regression Checklist (Per Module)

- [ ] List view loads correctly
- [ ] Pagination works (page change, per_page change)
- [ ] Filters work (each filter type)
- [ ] Search works (with debounce)
- [ ] URL query params reflect filter state
- [ ] Browser back/forward preserves state
- [ ] Create flow works and list updates
- [ ] Edit flow works and item updates
- [ ] Delete flow works and item removed from list
- [ ] Bulk operations work
- [ ] Permission-gated features render correctly
- [ ] SPA navigation to/from module works
- [ ] localStorage preferences persist across page reload

---

## 15. Long-Term Scalability Plan

### 15.1 Short Term (Current Migration)

- Establish Pinia as the state management standard
- Create service layer for API abstraction
- Centralize shared data (agents, products, permissions)
- Improve permission checking consistency

### 15.2 Medium Term (Post-Migration)

- Replace jQuery AJAX with `fetch` or `axios` in service layer
- Add AbortController for request cancellation
- Implement optimistic updates for common operations
- Add WebSocket/SSE support for real-time ticket updates (replacing Firebase polling)
- Consider splitting the largest components into smaller sub-components

### 15.3 Long Term (Architecture Evolution)

- Evaluate migrating from Options API to Composition API (if team alignment exists)
- Consider TypeScript for store/service type safety
- Add automated E2E testing (Cypress/Playwright)
- Evaluate server-state libraries (TanStack Query) for advanced caching if entity complexity grows
- Consider code splitting per module for reduced initial bundle size

---

## Appendix A: Current Permission Strings

| Permission | Used In |
|-----------|---------|
| `fst_manage_settings` | ViewTicket.vue, Report.vue, ActivityLogger.vue, SupportStaffs.vue |
| `fst_sensitive_data` | _TicketSidebar.vue, Report.vue |
| `fst_agent_today_performance` | Dashboard.vue |
| `fst_draft_reply` | ViewTicket.vue |
| `fst_approve_draft_reply` | ViewTicket.vue |
| `fst_delete_tickets` | ViewTicket.vue |

## Appendix B: Key File Paths

| File | Path | Lines |
|------|------|-------|
| App Entry | `resources/admin/start.js` | 85 |
| Routes | `resources/admin/routes.js` | 295 |
| FluentFramework | `resources/admin/Bits/FluentFramework.js` | 440 |
| REST Client | `resources/admin/Bits/Rest.js` | 68 |
| AllTickets | `resources/admin/Modules/Tickets/AllTickets.vue` | 1,948 |
| ViewTicket | `resources/admin/Modules/Tickets/ViewTicket.vue` | 1,661 |
| Overview Report | `resources/admin/Modules/Reports/Overview.vue` | 1,013 |
| TicketSidebar | `resources/admin/Modules/Tickets/_TicketSidebar.vue` | 980 |
| SupportStaffs | `resources/admin/Modules/Settings/SupportStaffs.vue` | 632 |
| Portal Entry | `resources/customer_portal/portal.js` | ~120 |
| Portal REST | `resources/customer_portal/Rest.js` | 240 |
