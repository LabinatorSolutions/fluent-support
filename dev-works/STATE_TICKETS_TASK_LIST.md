# Tickets List Module — Production Migration Checklist

## Pinia State Management Migration for AllTickets.vue

---

## Scope

This checklist covers the migration of the **Tickets List** module to centralized Pinia state management. It is scoped exclusively to listing, filtering, pagination, selection, and bulk operations on the ticket list view.

### In Scope

| File | Lines | Role |
|------|-------|------|
| `resources/admin/Modules/Tickets/AllTickets.vue` | 1,948 | Ticket list, filters, pagination, bulk actions, search |
| `resources/admin/Modules/Tickets/TicketsView.vue` | 137 | Parent wrapper (sidebar collapse, breadcrumbs) |
| `resources/admin/Modules/Tickets/_BulkActions.vue` | 232 | Bulk action bar (assign, tag, close, delete) |
| `resources/admin/Modules/Tickets/_TicketsMenu.vue` | 228 | Sidebar navigation and filtered ticket mini-list |
| `resources/admin/Modules/Tickets/parts/_TicketFilters.vue` | 268 | Custom filter dropdown panel |
| `resources/admin/Modules/Tickets/parts/_LabelSearchDrawer.vue` | 103 | Saved search drawer |
| `resources/admin/Pieces/Pagination.vue` | 57 | Shared pagination component |

### Out of Scope

- `ViewTicket.vue` and all its children (separate migration phase)
- `_AddTicket.vue` (separate migration phase)
- CRM data model, customer module, settings module
- UI redesign, naming convention refactoring, new features
- Customer portal application

---

## Resolved Decisions

These decisions are final. Do not revisit during implementation.

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| 1 | Store property naming | **snake_case** | Matches existing template bindings. Eliminates 50+ template rename risks. Production-safe. |
| 2 | localStorage access | **Keep `$saveData`/`$getData` in component, pass values to store** | Store stays framework-agnostic. Component handles persistence orchestration. |
| 3 | Notification calls | **Keep in component** | Store throws/returns. Component calls `$notify`/`$handleError`. |
| 4 | Router access | **Keep in component** | Store never imports or calls `$router`. Component handles navigation. |
| 5 | DOM manipulation | **Keep in component** | Focus, scroll, refs stay in component lifecycle. |
| 6 | `appVars` access | **Pass into store via action params or init method** | Store does not access `window.fluentSupportAdmin` directly. |
| 7 | `has_pro` checks | **Keep in component** | Store actions are feature-agnostic. Component gates calls based on `has_pro`. |

---

## Prerequisites

These items must be completed before any release in this checklist begins.

- [ ] Pinia is installed: `npm install pinia`
- [ ] Pinia is registered in `resources/admin/start.js`:
  ```javascript
  import { createPinia } from 'pinia';
  const pinia = createPinia();
  // ... existing code ...
  window.fluentSupportAppp = framerwork.app.use(pinia).use(router).mount('#alpha_app');
  ```
- [ ] Directory `resources/admin/stores/` exists
- [ ] Directory `resources/admin/services/` exists
- [ ] `npm run dev` builds successfully with Pinia installed and registered
- [ ] No console errors on page load after Pinia registration
- [ ] Existing functionality verified unchanged after Pinia registration

---

## Release 1 — Service Layer + Store Definition

**Goal:** Create the service layer and store file. Zero UI changes. Zero component modifications. All new files only.

**Risk level:** Minimal. No existing code is modified.

### 1.1 Create Ticket List Service

**File:** `resources/admin/services/ticketListService.js`

This service wraps the existing `Rest.js` client for ticket-list-related API calls only.

- [ ] **1.1.1** Create `resources/admin/services/ticketListService.js`
- [ ] **1.1.2** Import Rest client:
  ```javascript
  import Rest from '@/admin/Bits/Rest';
  ```
- [ ] **1.1.3** `fetchList(params)` — wraps `Rest.get('tickets', params)`
  - Source: `AllTickets.vue:863` — `this.$get("tickets", query)`
- [ ] **1.1.4** `bulkAction(data)` — wraps `Rest.post('tickets/bulk-actions', data)`
  - Source: `_BulkActions.vue:163` — `this.$post('tickets/bulk-actions', data)`
- [ ] **1.1.5** `bulkClose(data)` — wraps `Rest.post('tickets/bulk', data)`
  - Source: `AllTickets.vue:1065` — `this.$post("tickets/bulk", {...})`
- [ ] **1.1.6** `bulkDelete(data)` — wraps `Rest.delete('tickets/bulk', data)`
  - Source: `AllTickets.vue:1046` — `this.$del("tickets/bulk", {...})`
- [ ] **1.1.7** `saveLabelSearch(data)` — wraps `Rest.post('tickets/label-search', data)`
  - Source: `AllTickets.vue:1189` — `this.$post("tickets/label-search", {...})`
- [ ] **1.1.8** `fetchLabelSearches()` — wraps `Rest.get('tickets/label-search')`
  - Source: `AllTickets.vue:1209` — `this.$get("tickets/label-search")`
- [ ] **1.1.9** `deleteLabelSearch(id)` — wraps `Rest.delete('tickets/' + id + '/label-search')`
  - Source: `AllTickets.vue:1244` — `this.$del('tickets/' + id + '/label-search')`

**Implementation notes:**
- Each method returns the jQuery Deferred from `Rest.*()` unchanged. Do not wrap in native Promise yet. The calling code in AllTickets.vue depends on `.then()` + `.always()` + `.catch()` chaining from jQuery Deferred.
- No transformation of request/response data. The service is a thin passthrough.

### 1.2 Create Ticket List Store

**File:** `resources/admin/stores/ticketList.store.js`

- [ ] **1.2.1** Create `resources/admin/stores/ticketList.store.js`
- [ ] **1.2.2** Define store using Options API:
  ```javascript
  import { defineStore } from 'pinia';
  import ticketListService from '@/admin/services/ticketListService';
  ```

#### 1.2.3 State

All properties use **snake_case** to match existing template bindings in `AllTickets.vue`.

- [ ] Define state — exact migration from `AllTickets.vue` `data()` (lines 666–727):

```javascript
state: () => ({
    // Entity data
    tickets: [],                          // line 669

    // Pagination
    pagination: {                         // lines 670-674
        current_page: 1,
        total: 0,
        per_page: 10,
    },

    // Status groups
    ticket_statuses_group: {},            // line 675

    // Filters
    filters: {                            // lines 676-686
        status_type: 'open',
        product_id: [],
        agent_id: [],
        priority: [],
        client_priority: [],
        waiting_for_reply: '',
        ticket_tags: [],
        mailbox_id: [],
        watcher: '',
    },

    // Search
    search: '',                           // line 688
    searchActive: false,                  // line 706

    // Sorting
    order_by: 'last_customer_response',   // line 693
    order_type: 'ASC',                    // line 694

    // Filter mode
    filter_type: 'simple',               // line 704
    advanced_filters: [[]],              // line 703

    // Saved searches
    label_search_id: '',                 // line 687
    label_search_name: '',               // line 689
    labelSearchList: [],                 // line 690

    // Selections
    ticket_selections: [],               // line 695

    // Loading flags
    loading: false,                      // line 668
    first_time_loading: true,            // line 702
    app_ready: false,                    // line 697
    appReady: false,                     // line 699
    doing_bulk: false,                   // line 696

    // Display settings
    fieldVisibility: {                   // lines 711-724
        title: true,
        author: true,
        tags: true,
        source: true,
        description: true,
        response_count: true,
        product: true,
        mailbox: true,
        agent: true,
        status: true,
        client_priority: true,
        waiting_time: true,
    },
    ticketLayout: 'default',             // line 725

    // Cache metadata
    _lastFetchTime: null,
    _lastFetchParams: null,
}),
```

#### 1.2.4 Getters

- [ ] **`activeFilters`** — migrate from `AllTickets.vue` computed (lines 759–782)
  - Returns object of non-empty filter entries
- [ ] **`selectAllChecked`** — migrate from `AllTickets.vue` computed (lines 783–790)
  - Returns `tickets.length > 0 && ticket_selections.length === tickets.length`
- [ ] **`isIndeterminate`** — migrate from `AllTickets.vue` computed (lines 791–793)
  - Returns `ticket_selections.length > 0 && ticket_selections.length < tickets.length`
- [ ] **`hasStaleCache`** — new getter
  - Returns `true` if `_lastFetchTime` is null or older than 5 minutes

**Note:** `filterColumns` and `fieldVisibilityOptions` use `this.$t()` for translations. Keep these as local computed properties in `AllTickets.vue` — they cannot be moved to the store because the store has no access to `$t()`.

#### 1.2.5 Actions

Each action migrates a method from `AllTickets.vue`. Notifications (`$notify`), error handling (`$handleError`), and router calls are **excluded** from store actions — they remain in the component.

- [ ] **`fetchTickets(query)`**
  - Migrates from `AllTickets.vue:826–891`
  - Receives the fully-built query object as a parameter (component builds it)
  - Calls `ticketListService.fetchList(query)`
  - On success: sets `this.tickets`, `this.pagination.total`, sets `window.fsCurrentFilteredTickets` for backward compatibility
  - Sets `this._lastFetchTime = Date.now()` on success
  - Sets `this.loading = false` and `this.first_time_loading = false` in finally
  - Returns the jQuery Deferred so the component can chain `.catch()` for error handling
  - Handles empty page redirect logic (lines 865–873): if `response.tickets.total && !response.tickets.from && current_page > 1`, reset page to 1 and re-fetch

- [ ] **`setStatus(statusKey)`**
  - Sets `this.filters.status_type = statusKey`
  - Does NOT call fetchTickets (component orchestrates the fetch after calling this)

- [ ] **`resetFilters(routeQuery)`**
  - Migrates from lines 1096–1119
  - Resets `this.filters` to defaults, clears search, resets sort, resets page
  - Applies `routeQuery.agent_id` and `routeQuery.watcher` if present

- [ ] **`resetWithOutFetch()`**
  - Migrates from lines 1121–1135
  - Same as resetFilters but no route query application

- [ ] **`clearAllFilters()`**
  - Migrates from lines 1773–1794
  - Resets filters to defaults with spread operator

- [ ] **`resetAdvancedFilters()`**
  - Migrates from lines 1796–1803
  - Resets `this.advanced_filters = [[]]`

- [ ] **`handleCustomFilter(filterType, filterValue)`**
  - Migrates from lines 1813–1838
  - Sets the appropriate filter property based on filterType

- [ ] **`handleSelectAll(checked)`**
  - Migrates from lines 1840–1846
  - Sets `this.ticket_selections` to all ticket IDs or empty array

- [ ] **`handleTicketSelection(ticketId)`**
  - Migrates from lines 1322–1329
  - Toggles ticketId in `this.ticket_selections`

- [ ] **`deleteSelected()`**
  - Migrates from lines 1043–1061
  - Calls `ticketListService.bulkDelete({ ticket_ids: this.ticket_selections })`
  - On success: triggers refetch via return value (component calls fetchTickets)
  - Returns the Deferred for component error handling

- [ ] **`closeSelected()`**
  - Migrates from lines 1063–1083
  - Calls `ticketListService.bulkClose({ ticket_ids, bulk_action: 'close_tickets' })`
  - Returns the Deferred for component notification + error handling

- [ ] **`handleSaveSearch(query)`**
  - Migrates from lines 1164–1205
  - Calls `ticketListService.saveLabelSearch({ query })`
  - Returns the Deferred

- [ ] **`fetchLabelSearches()`**
  - Migrates from lines 1207–1219
  - Calls `ticketListService.fetchLabelSearches()`
  - On success: sets `this.labelSearchList`
  - Returns the Deferred

- [ ] **`handleLabelSearchDelete(id)`**
  - Migrates from lines 1243–1262
  - Calls `ticketListService.deleteLabelSearch(id)`
  - On success: removes item from `this.labelSearchList`, clears `this.label_search_id` if it matches
  - Returns the Deferred

- [ ] **`handleAdvanceSearch(item)`**
  - Migrates from lines 1221–1228
  - Parses `item.advanced_filters`, sets `this.advanced_filters`, `this.filter_type`, clears label search name/id

- [ ] **`handleLabelSearchEdit(item)`**
  - Migrates from lines 1230–1235
  - Sets `this.advanced_filters`, `this.filter_type`, `this.label_search_name`, `this.label_search_id`

- [ ] **`loadFiltersFromStorage(savedData, routeQuery)`**
  - Migrates from `setFromSaveFilters()` (lines 930–968) + `mounted()` URL param logic (lines 1892–1936)
  - Receives pre-read localStorage values and route query from the component
  - Applies in order: localStorage defaults → route query overrides
  - Does NOT call `$saveData`/`$getData` (component provides the values)

- [ ] **`invalidateCache()`**
  - Sets `this._lastFetchTime = null`

### 1.3 Release 1 Verification

- [ ] `npm run dev` succeeds
- [ ] `npm run production` succeeds
- [ ] No console errors on any admin page
- [ ] AllTickets.vue still functions identically (no changes to it)
- [ ] New files exist and are syntactically valid:
  - `resources/admin/services/ticketListService.js`
  - `resources/admin/stores/ticketList.store.js`

### 1.4 Release 1 QA Checklist

| Test | Expected |
|------|----------|
| Navigate to Tickets list page | Loads normally, no errors |
| Navigate to all other admin pages | All function normally |
| Check browser console | Zero new errors or warnings |
| Check build output size | Within 5% of pre-migration bundle |
| Open Vue DevTools → Pinia tab | `ticketList` store visible (empty/default state) |

### 1.5 Release 1 Rollback

1. Revert `start.js` to remove Pinia registration
2. Delete `resources/admin/services/ticketListService.js`
3. Delete `resources/admin/stores/ticketList.store.js`
4. Run `npm run production`
5. Deploy previous build artifacts

**Estimated rollback time:** Under 5 minutes.

---

## Release 2 — Wire AllTickets.vue to Store

**Goal:** AllTickets.vue reads and writes state through the Pinia store instead of local `data()`. All existing behavior preserved exactly.

**Risk level:** High. This is the primary behavioral change. Requires thorough QA.

### 2.1 Import Store in AllTickets.vue

- [ ] **2.1.1** Add imports at top of `<script>` block:
  ```javascript
  import { mapState, mapActions, mapWritableState } from 'pinia';
  import { useTicketListStore } from '@/admin/stores/ticketList.store';
  ```

### 2.2 Remove Migrated Properties from `data()`

- [ ] **2.2.2** Remove these properties from `data()` return (they now live in the store):
  - `loading` (line 668)
  - `tickets` (line 669)
  - `pagination` (lines 670–674)
  - `ticket_statuses_group` (line 675)
  - `filters` (lines 676–686)
  - `label_search_id` (line 687)
  - `search` (line 688)
  - `label_search_name` (line 689)
  - `labelSearchList` (line 690)
  - `order_by` (line 693)
  - `order_type` (line 694)
  - `ticket_selections` (line 695)
  - `doing_bulk` (line 696)
  - `app_ready` (line 697)
  - `appReady` (line 699)
  - `first_time_loading` (line 702)
  - `advanced_filters` (line 703)
  - `filter_type` (line 704)
  - `searchActive` (line 706)
  - `fieldVisibility` (lines 711–724)
  - `ticketLayout` (line 725)

- [ ] **2.2.3** Keep these in local `data()` — they are purely UI/modal state:
  - `add_ticket_modal` (line 698)
  - `add_response_modal` (line 700)
  - `show_filters` (line 701)
  - `openTicketInNewTab` (line 705)
  - `filterPopoverVisible` (line 707)
  - `selectedFilterPath` (line 708)
  - `show_bulk_actions` (line 709)
  - `imageErrors` (line 710)
  - `openLabelSearchDrawer` (line 691)
  - `isLabelSearchOpen` (line 692)
  - `saving` (if used for save search button loading — keep local)

### 2.3 Map Store State in `computed`

- [ ] **2.3.1** Map read-only state (getters):
  ```javascript
  computed: {
      ...mapState(useTicketListStore, [
          'tickets',
          'pagination',
          'ticket_statuses_group',
          'loading',
          'first_time_loading',
          'app_ready',
          'appReady',
          'doing_bulk',
          'labelSearchList',
          // Getters
          'activeFilters',
          'selectAllChecked',
          'isIndeterminate',
          'hasStaleCache',
      ]),
      // Keep these locally — they use this.$t() which the store cannot access
      filterColumns() { /* unchanged */ },
      fieldVisibilityOptions() { /* unchanged */ },
  }
  ```

- [ ] **2.3.2** Map writable state for properties bound via `v-model` in the template:
  ```javascript
  computed: {
      ...mapWritableState(useTicketListStore, [
          'filters',               // v-model="filters.waiting_for_reply" (line 70)
          'search',                // v-model="search" (line 207)
          'order_by',              // v-model="order_by" (line 124)
          'order_type',            // v-model="order_type" (line 136)
          'filter_type',           // v-model binding via switch (line 80)
          'advanced_filters',      // :items="rich_filter" inside v-for
          'ticket_selections',     // checkbox bindings
          'searchActive',          // v-if="searchActive" + direct assignment
          'fieldVisibility',       // v-model="fieldVisibility[field.key]" (line 182)
          'ticketLayout',          // v-model="ticketLayout" (line 165)
          'label_search_id',       // used in template conditionals and assignments
          'label_search_name',     // v-model="label_search_name" (line 580)
      ]),
  }
  ```

### 2.4 Map Store Actions in `methods`

- [ ] **2.4.1** Map store actions:
  ```javascript
  methods: {
      ...mapActions(useTicketListStore, [
          'setStatus',
          'resetFilters',
          'resetWithOutFetch',
          'clearAllFilters',
          'resetAdvancedFilters',
          'handleCustomFilter',
          'handleSelectAll',
          'handleTicketSelection',
          'deleteSelected',
          'closeSelected',
          'handleSaveSearch',
          'fetchLabelSearches',
          'handleLabelSearchDelete',
          'handleAdvanceSearch',
          'handleLabelSearchEdit',
          'loadFiltersFromStorage',
          'invalidateCache',
      ]),
  }
  ```

### 2.5 Rewrite `fetchTickets()` as Local Method

The local `fetchTickets()` method remains in the component. It builds the query, calls the store action, and handles UI concerns (notifications, error handling).

- [ ] **2.5.1** Rewrite `fetchTickets()`:
  ```javascript
  async fetchTickets() {
      const store = useTicketListStore();
      if (!store.app_ready) return false;

      store.ticket_selections = [];
      store.loading = true;

      let query = {
          page: store.pagination.current_page,
          per_page: store.pagination.per_page,
          order_by: store.order_by,
          order_type: store.order_type,
          filter_type: store.filter_type,
      };

      if (store.filter_type === 'advanced' && this.has_pro) {
          query.advanced_filters = JSON.stringify(store.advanced_filters);
      } else {
          query.filters = store.filters;
          query.search = store.search;
          if (!this.has_pro) {
              query.filter_type = 'simple';
              store.filter_type = 'simple';
          }
      }

      const params = {};
      each(query, (val, key) => {
          if (!isEmpty(val)) {
              params[key] = val;
          }
      });

      window.fs_sub_params = params;
      params.t = Date.now();

      try {
          await store.fetchTickets(query);
          this.saveFilters();
      } catch (e) {
          this.$handleError(e);
      }
  },
  ```

### 2.6 Keep These Methods Local in AllTickets.vue

These methods must NOT move to the store. They are UI-only or use component-specific APIs.

- [ ] `gotToTicket(row, event)` (lines 903–912) — router navigation
- [ ] `goToTicketView(ticket, event)` (lines 914–924) — ctrl+click handler
- [ ] `handleImageError(event)` (lines 926–928) — DOM manipulation
- [ ] `getWaitingStatus(ticket)` (lines 998–1020) — display logic
- [ ] `getLastResponse(ticket)` (lines 1022–1033) — display logic
- [ ] `getExcerpt(row)` (lines 1085–1094) — display logic
- [ ] `getExcerptBox(text)` (lines 1137–1142) — display logic
- [ ] `countAdvancedFilterData(filters)` (lines 1144–1162) — display logic
- [ ] `handleKeydown(event)` (lines 1264–1320) — keyboard handler (calls store actions internally)
- [ ] `getFilterLabel(key)` (lines 1553–1565) — uses `this.$t()`
- [ ] `getFilterDisplayValue(key, value)` (lines 1567–1623) — uses `this.appVars`
- [ ] `filterCascaderOptions()` (lines 1625–1706) — uses `this.$t()` and `this.appVars`
- [ ] `handleFilterCascaderChange()` (lines 1707–1748) — UI handler, writes to store + calls fetchTickets
- [ ] `getFilterIcon(filterKey)` (lines 1750–1761) — display logic
- [ ] `handleMoreAction(command)` (lines 1856–1864) — local UI toggle
- [ ] `handleBulkActionsToggle(value)` (lines 1848–1854) — localStorage + local UI
- [ ] `showFilterDialog()` and all `showXxxFilter()` methods (lines 1398–1536) — Element Plus MessageBox UI
- [ ] `removeFilter(filterKey)` (lines 1538–1551) — writes to store state (via writable) + calls fetchTickets + refs
- [ ] `openSearch()` (lines 1335–1342) — sets searchActive + DOM focus
- [ ] `closeSearch()` (lines 1344–1348) — resets searchActive, search, calls fetchTickets
- [ ] `changeOrderType()` (lines 989–996) — toggles order_type, calls fetchTickets
- [ ] `maybeChangeWaitingReply()` (lines 1331–1333) — calls fetchTickets
- [ ] `addConditionGroup()` (lines 893–895) — pushes to advanced_filters
- [ ] `maybeRemoveGroup(index)` (lines 897–901) — splices advanced_filters
- [ ] `saveFilters()` (lines 971–987) — calls `$saveData` (localStorage)
- [ ] `setFromSaveFilters()` (lines 930–968) — calls `$getData` and passes to store
- [ ] `saveFieldVisibility()` (lines 1866–1868) — calls `$saveData`
- [ ] `resetFieldVisibility()` (lines 1870–1886) — resets store state + calls saveFieldVisibility
- [ ] `setTicketLayout(layout)` (lines 1888–1890) — calls `$saveData`
- [ ] `removeData(key)` (lines 1763–1771) — localStorage cleanup
- [ ] `clearAllFilters()` override — calls store action + DOM ref cleanup + localStorage removal
- [ ] `handleProductFilterChange()` (lines 1805–1811) — ref update + fetchTickets
- [ ] `openSaveSearchModal()` (lines 1238–1241) — local UI
- [ ] `closeSavedSearchListModal()` (lines 822–824) — local UI
- [ ] `handleSelectionChange()` (lines 1035–1041) — el-table callback (may be unused)
- [ ] `toggleAdvancedFilter(value)` (lines 1355–1366) — writes to store state + calls fetchTickets

### 2.7 Update `mounted()` Hook

- [ ] **2.7.1** Rewrite `mounted()` (lines 1892–1941):
  ```javascript
  mounted() {
      const store = useTicketListStore();
      store.app_ready = true;
      this.setFromSaveFilters();

      if (this.$route.query.search) {
          store.resetWithOutFetch();
          store.filter_type = 'simple';
          store.advanced_filters = [[]];
      }

      if (this.$route.query.agent_id) {
          store.filters.agent_id = this.$route.query.agent_id;
          store.filters.watcher = this.$route.query.watcher;
      }
      if (this.$route.query.waiting_for_reply) {
          store.filters.waiting_for_reply = this.$route.query.waiting_for_reply;
      }
      if (this.$route.query.tags) {
          const tagIds = this.$route.query.tags;
          if (typeof tagIds === 'object') {
              store.filters.ticket_tags = tagIds.map(tagId => parseInt(tagId));
          } else {
              store.filters.ticket_tags = [parseInt(tagIds)];
          }
      }
      if (this.$route.query.search) {
          store.search = this.$route.query.search;
      }
      if (this.$route.query.filter_type) {
          store.filter_type = this.$route.query.filter_type;
          store.filters.status_type = this.$route.query.status_type;
      }

      this.$nextTick(() => {
          this.fetchTickets();
      });

      this.$setTitle(this.$t('All Tickets'));
      if (this.appVars.keyboard_shortcuts === 'yes') {
          window.addEventListener('keydown', this.handleKeydown);
      }
  },
  ```

### 2.8 Update Watchers

- [ ] **2.8.1** Keep `'$route.query.agent_id'` watcher (lines 796–802):
  ```javascript
  '$route.query.agent_id'(newAgentId, oldAgentId) {
      const store = useTicketListStore();
      if (store.app_ready && this.$route.name !== 'view_ticket') {
          if (newAgentId !== oldAgentId) {
              store.filters.agent_id = newAgentId;
              this.fetchTickets();
          }
      }
  },
  ```
- [ ] **2.8.2** Keep `'$route.query.watcher'` watcher (lines 804–811) — same pattern
- [ ] **2.8.3** Keep `filter_type` watcher (lines 812–818):
  ```javascript
  filter_type(newFilterType, oldFilterType) {
      const store = useTicketListStore();
      if (store.app_ready && this.$route.name !== 'view_ticket') {
          if (newFilterType !== oldFilterType) {
              this.fetchTickets();
          }
      }
  }
  ```

### 2.9 Update `setFromSaveFilters()` to Pass Data to Store

- [ ] **2.9.1** Rewrite to read from localStorage and push into store:
  ```javascript
  setFromSaveFilters() {
      const store = useTicketListStore();
      store.filter_type = this.$getData('tickets_filter_type', 'simple');
      store.ticket_statuses_group = this.appVars.ticket_statuses_group || {};

      const filters = this.$getData('tickets_filter', {});
      each(filters, (filter, filterKey) => {
          store.filters[filterKey] = filter;
      });

      const ticketPref = this.$getData('tickets_pref', false);
      if (ticketPref) {
          store.order_by = ticketPref.order_by;
          store.order_type = ticketPref.order_type;
          store.pagination.per_page = ticketPref.per_page;
          store.pagination.current_page = ticketPref.current_page;
          store.search = ticketPref.search;
      }

      const advancedFilters = this.$getData('tickets_advanced_filters', [[]]);
      if (advancedFilters) {
          store.advanced_filters = advancedFilters;
      }

      const savedBulkActionsState = this.$getData('tickets_show_bulk_actions', false);
      this.show_bulk_actions = savedBulkActionsState;

      const savedFieldVisibility = this.$getData('tickets_field_visibility', null);
      if (savedFieldVisibility) {
          store.fieldVisibility = { ...store.fieldVisibility, ...savedFieldVisibility };
      }

      const savedLayout = this.$getData('tickets_layout', 'default');
      store.ticketLayout = savedLayout;

      this.openTicketInNewTab = this.appVars.open_ticket_in_new_tab === 'yes';
      store.appReady = true;
  },
  ```

### 2.10 Update `saveFilters()` to Read from Store

- [ ] **2.10.1** Rewrite:
  ```javascript
  saveFilters() {
      const store = useTicketListStore();
      this.$saveData('tickets_pref', {
          order_by: store.order_by,
          order_type: store.order_type,
          per_page: store.pagination.per_page,
          search: store.search,
          current_page: store.pagination.current_page,
      });
      this.$saveData('tickets_filter_type', store.filter_type);
      if (store.filter_type === 'advanced') {
          this.$saveData('tickets_advanced_filters', store.advanced_filters);
      } else {
          this.$saveData('tickets_filter', store.filters);
      }
  },
  ```

### 2.11 Template — Zero Renames Required

Because store state uses **snake_case** matching existing template bindings, no template variable renames are required. All existing references (`filter_type`, `ticket_statuses_group`, `order_by`, `order_type`, `ticket_selections`, `first_time_loading`, `label_search_id`, `label_search_name`, `advanced_filters`, `app_ready`) resolve via `mapWritableState` or `mapState` and map to the same names.

- [ ] **2.11.1** Verify all template bindings still resolve after migration
- [ ] **2.11.2** Verify all `v-model` bindings still work (writable state)
- [ ] **2.11.3** Verify all method calls in template still resolve

### 2.12 Release 2 Verification

Before deploying:

- [ ] `npm run dev` succeeds
- [ ] `npm run production` succeeds
- [ ] No console errors on Tickets list page
- [ ] No console warnings about undefined properties
- [ ] Vue DevTools shows `ticketList` store with correct state after page load

### 2.13 Release 2 QA Checklist

#### Page Load
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Fresh page load | Hard refresh on `/tickets` | Tickets load, filters from localStorage applied |
| 2 | Page load with URL params | Navigate to `/#/tickets?agent_id=5` | Agent filter applied, correct tickets shown |
| 3 | Page load with search param | Navigate to `/#/tickets?search=test` | Search applied, results filtered |
| 4 | Page load with tag params | Navigate to `/#/tickets?tags=1,2` | Tag filter applied |
| 5 | Page load with filter_type | Navigate to `/#/tickets?filter_type=advanced&status_type=open` | Advanced filter mode active |

#### Filtering
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 6 | Status tabs | Click each status tab | List filters correctly per status |
| 7 | Waiting for Reply toggle | Toggle switch | Filter applies, list updates |
| 8 | Custom filter — Agent | Select agent from dropdown | Filter chip shown, list updates |
| 9 | Custom filter — Product | Select product | Filter chip shown, list updates |
| 10 | Custom filter — Priority | Select priority | Filter chip shown, list updates |
| 11 | Custom filter — Client Priority | Select client priority | Filter chip shown, list updates |
| 12 | Custom filter — Mailbox | Select mailbox | Filter chip shown, list updates |
| 13 | Custom filter — Tags | Select tag | Filter chip shown, list updates |
| 14 | Remove single filter | Click X on filter chip | Filter removed, list updates |
| 15 | Clear all filters | Click "Reset Filter" | All filters reset, list updates |
| 16 | Active filter chips | Apply multiple filters | All chips display correctly |
| 17 | Advanced filter toggle | Toggle advanced filter switch | Mode switches, list updates |
| 18 | Advanced filter — add group | Click "OR" button | New filter group added |
| 19 | Advanced filter — apply | Click "Apply Filter" | Results filtered |
| 20 | Advanced filter — reset | Click "Reset Filters" | Advanced filters cleared |

#### Search
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 21 | Open search | Click search icon | Search bar appears, input focused |
| 22 | Execute search | Type text, press Enter | Results filtered |
| 23 | Cancel search | Click Cancel | Search bar closes, search cleared, list refreshes |

#### Sorting
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 24 | Sort by column | Select sort option | List re-sorted |
| 25 | Sort direction | Toggle ASC/DESC | List order changes |

#### Pagination
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 26 | Change page | Click page number | New page loads |
| 27 | Change per page | Select different page size | Resets to page 1, new data loads |
| 28 | Page info | Check "Page X of Y" | Displays correctly |

#### Display Settings
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 29 | Toggle column visibility | Uncheck a column | Column hides in list |
| 30 | Reset display settings | Click reset icon | All columns visible |
| 31 | Layout toggle | Switch Default/Compact | Layout changes |

#### Selection & Bulk Actions
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 32 | Show bulk actions | Click "More Action" → "Show Bulk Action" | Checkboxes appear |
| 33 | Select all | Check "Select All" checkbox | All tickets selected |
| 34 | Select individual | Check individual ticket | Single ticket selected |
| 35 | Indeterminate state | Select some but not all | Checkbox shows indeterminate |
| 36 | Bulk close | Select tickets → Close | Tickets closed, list refreshes |
| 37 | Bulk delete | Select tickets → Delete | Tickets deleted, list refreshes |
| 38 | Hide bulk actions | "More Action" → "Hide Bulk Action" | Checkboxes disappear, selections cleared |

#### Saved Searches
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 39 | Open saved filters drawer | Click saved filters icon | Drawer opens with saved list |
| 40 | Apply saved search | Click a saved search | Filters applied, list updates |
| 41 | Edit saved search | Click edit on saved search | Filters populated in editor |
| 42 | Save search | Create filters → Save | Search saved successfully |
| 43 | Delete saved search | Click delete → Confirm | Search removed from list |

#### Keyboard Shortcuts
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 44 | New ticket | Cmd+Alt+N | Add ticket modal opens |
| 45 | Refresh | Cmd+Alt+Q | List refreshes |
| 46 | Toggle advanced | Cmd+Alt+F | Filter mode toggles |
| 47 | Reset filters | Cmd+Alt+R | Advanced filters reset |
| 48 | Toggle waiting | Cmd+Alt+W | Waiting for reply toggles |
| 49 | Status cycle | Cmd+Shift+Arrow | Status tab changes |

#### Navigation
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 50 | Click ticket row | Click a ticket | Navigates to ViewTicket |
| 51 | Ctrl+click ticket | Ctrl+click ticket link | Opens in new tab |

#### Persistence
| # | Test | Steps | Expected |
|---|------|-------|----------|
| 52 | Filters persist | Set filters → Navigate away → Return | Filters restored |
| 53 | Sort persists | Change sort → Navigate away → Return | Sort restored |
| 54 | Page size persists | Change per page → Navigate away → Return | Page size restored |
| 55 | Layout persists | Change layout → Navigate away → Return | Layout restored |
| 56 | Field visibility persists | Hide columns → Navigate away → Return | Visibility restored |

### 2.14 Release 2 Rollback

1. Revert all changes to `AllTickets.vue` (restore from git)
2. Store and service files can remain (unused code, no side effects)
3. Run `npm run production`
4. Deploy previous build artifacts

**Estimated rollback time:** Under 5 minutes.

**Critical:** Do NOT delete the store/service files during rollback — they have no effect when not imported by a component.

---

## Release 3 — Wire Child Components + Remove Globals

**Goal:** Connect child components to the store. Remove the `window.fsCurrentFilteredTickets` global. No new state or behavior introduced.

**Risk level:** Medium. Changes are isolated to child components and do not alter the store or AllTickets.vue.

### 3.1 Wire `_BulkActions.vue`

- [ ] **3.1.1** Add store import:
  ```javascript
  import { mapState } from 'pinia';
  import { useTicketListStore } from '@/admin/stores/ticketList.store';
  ```
- [ ] **3.1.2** Replace prop `ticket_selections` with store state:
  ```javascript
  computed: {
      ...mapState(useTicketListStore, ['ticket_selections']),
  }
  ```
- [ ] **3.1.3** Remove `props: ['ticket_selections']`
- [ ] **3.1.4** Replace `this.$emit('fetchTickets')` in internal `fetchTickets()` method (line 158) with a call to the parent's method or direct store access:
  ```javascript
  fetchTickets() {
      this.agent_id = '';
      this.tag_ids = [];
      this.workflow_id = '';
      this.addTagPop = false;
      this.assignAgentPop = false;
      this.add_response_modal = false;
      this.$emit('fetchTickets');  // Keep emit — parent still orchestrates fetch
  },
  ```
  **Decision:** Keep the `@fetchTickets` emit pattern for now. The parent `AllTickets.vue` calls its local `fetchTickets()` which orchestrates the store fetch + error handling + persistence. Converting to direct store access would skip notification/error handling.

- [ ] **3.1.5** In `AllTickets.vue`, remove the `:ticket_selections` prop binding from the `<ticket-bulk-actions>` tag (line 631):
  ```html
  <!-- Before -->
  <ticket-bulk-actions
      v-if="appReady && show_bulk_actions"
      @fetchTickets="fetchTickets()"
      :ticket_selections="ticket_selections"
  />
  <!-- After -->
  <ticket-bulk-actions
      v-if="appReady && show_bulk_actions"
      @fetchTickets="fetchTickets()"
  />
  ```

### 3.2 Wire `TicketsView.vue`

- [ ] **3.2.1** Replace `window.fsCurrentFilteredTickets` read (line 100) with store access:
  ```javascript
  import { mapState } from 'pinia';
  import { useTicketListStore } from '@/admin/stores/ticketList.store';
  ```
  ```javascript
  computed: {
      ...mapState(useTicketListStore, ['tickets']),
      currentTickets() {
          if (this.$route.name !== 'view_ticket') {
              return null;
          }
          return this.tickets && this.tickets.length > 0 ? this.tickets : null;
      },
      // ... existing computed unchanged
  },
  ```

### 3.3 Wire `_TicketsMenu.vue`

- [ ] **3.3.1** Replace `window.fsCurrentFilteredTickets` read (lines 135–139) with store access:
  ```javascript
  import { mapState } from 'pinia';
  import { useTicketListStore } from '@/admin/stores/ticketList.store';
  ```
  ```javascript
  computed: {
      ...mapState(useTicketListStore, { storeTickets: 'tickets' }),
      currentTickets() {
          if (this.$route.name !== 'view_ticket' || !(this.storeTickets && this.storeTickets.length)) {
              return null;
          }
          return this.storeTickets;
      },
      // ... existing computed unchanged
  },
  ```

### 3.4 Remove `window.fsCurrentFilteredTickets` Global

- [ ] **3.4.1** In the `ticketList.store.js` `fetchTickets` action, **remove** the line that sets `window.fsCurrentFilteredTickets`. The store is now the single source of truth.
- [ ] **3.4.2** Verify no other files reference `window.fsCurrentFilteredTickets`:
  - Search entire codebase for `fsCurrentFilteredTickets`
  - Confirm only `AllTickets.vue` (write), `TicketsView.vue` (read), and `_TicketsMenu.vue` (read) referenced it
  - All three are now on the store

### 3.5 No Changes to These Components

These child components require no changes in this release:

- **`parts/_TicketFilters.vue`** — Communicates via `@apply-filter` emit to parent. Parent handles store writes. No direct store dependency needed.
- **`parts/_LabelSearchDrawer.vue`** — Receives `labelSearchList` as prop from parent. Communicates via emits. No direct store dependency needed.
- **`Pagination.vue`** — Receives `pagination` as prop, emits `fetch`. The `pagination` object from the store is reactive and passed down. Pagination mutates `pagination.current_page` and `pagination.per_page` directly via the prop reference, which works because `mapWritableState` provides a reactive reference. No changes required.
- **`parts/RichFilters/RichFilter.vue`** — Receives filter items as prop. No store interaction needed.

### 3.6 Release 3 Verification

- [ ] `npm run dev` succeeds
- [ ] `npm run production` succeeds
- [ ] No console errors on any page
- [ ] `window.fsCurrentFilteredTickets` is no longer set
- [ ] Sidebar mini-ticket-list still works in ViewTicket context

### 3.7 Release 3 QA Checklist

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Bulk assign agent | Select tickets → Assign agent | Agent assigned, list refreshes |
| 2 | Bulk assign tags | Select tickets → Add tags | Tags assigned, list refreshes |
| 3 | Bulk close via bar | Select tickets → Close from bulk bar | Tickets closed |
| 4 | Bulk delete via bar | Select tickets → Delete from bulk bar | Tickets deleted |
| 5 | Bulk reply | Select tickets → Reply | Reply modal opens, response sent |
| 6 | Bulk workflow | Select tickets → Run workflow | Workflow executed |
| 7 | Sidebar mini-list | Navigate to ViewTicket | Filtered tickets appear in sidebar |
| 8 | Sidebar collapse | Toggle sidebar collapse | Sidebar collapses/expands |
| 9 | Sidebar navigation | Click ticket in sidebar | Navigates to that ticket |
| 10 | Breadcrumbs | View ticket | Breadcrumbs show "All Tickets > #ID" |
| 11 | All Release 2 tests | Re-run full QA checklist | All pass |

### 3.8 Release 3 Rollback

1. Revert `_BulkActions.vue` — restore `props: ['ticket_selections']`
2. Revert `TicketsView.vue` — restore `window.fsCurrentFilteredTickets` read
3. Revert `_TicketsMenu.vue` — restore `window.fsCurrentFilteredTickets` read
4. Restore `window.fsCurrentFilteredTickets = response.tickets.data` in store's fetchTickets action
5. Restore `:ticket_selections="ticket_selections"` prop in AllTickets.vue template
6. Run `npm run production`

**Estimated rollback time:** Under 10 minutes.

---

## Release 4 — Cache Layer + Final Cleanup

**Goal:** Implement cache-aware navigation so returning to the ticket list from a ticket detail view is instant when data is fresh. Remove residual globals.

**Risk level:** Low. Additive behavior only — worst case falls back to full fetch.

### 4.1 Implement Cache-Aware Fetch in `AllTickets.vue`

- [ ] **4.1.1** Update `mounted()` to check cache before fetching:
  ```javascript
  this.$nextTick(() => {
      const store = useTicketListStore();
      if (store.tickets.length > 0 && !store.hasStaleCache) {
          // Data is fresh, skip fetch — show cached data instantly
          return;
      }
      this.fetchTickets();
  });
  ```

- [ ] **4.1.2** In `ticketList.store.js` `fetchTickets` action, set `_lastFetchTime`:
  ```javascript
  // On successful fetch:
  this._lastFetchTime = Date.now();
  ```

### 4.2 Remove `window.fs_sub_params` Global

- [ ] **4.2.1** Search for `window.fs_sub_params` usage across codebase
- [ ] **4.2.2** If used elsewhere, store the value in the Pinia store instead
- [ ] **4.2.3** If unused elsewhere, remove the assignment from `fetchTickets()` (line 859)

### 4.3 Release 4 Verification

- [ ] Cache hit works: navigate AllTickets → ViewTicket → back → instant display
- [ ] Cache miss works: wait 5+ minutes → navigate back → full reload
- [ ] No console errors

### 4.4 Release 4 QA Checklist

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Cache hit | View list → Click ticket → Back button | List shows instantly, no loading spinner |
| 2 | Cache stale | View list → Wait 6 min → Navigate away → Return | List fetches fresh data |
| 3 | Cache invalidation | View list → (another tab closes a ticket) → Return | List shows stale until manual refresh |
| 4 | Fresh load | Hard refresh | Full fetch, loading indicator shown |
| 5 | All previous tests | Re-run Release 2 + 3 QA | All pass |

### 4.5 Release 4 Rollback

1. Remove cache check from `mounted()` — restore unconditional `this.fetchTickets()`
2. Run `npm run production`

**Estimated rollback time:** Under 3 minutes.

---

## What NOT to Do During Migration

- **Do NOT rename properties from snake_case to camelCase.** This creates unnecessary template churn and regression risk.
- **Do NOT move `$notify` or `$handleError` calls into the store.** Stores return data; components handle UI feedback.
- **Do NOT access `this.$router` from inside the store.** Navigation is a component concern.
- **Do NOT access `this.$t()` from inside the store.** Translations are a component concern.
- **Do NOT access `window.fluentSupportAdmin` from inside the store.** Component passes needed values as action parameters.
- **Do NOT convert jQuery Deferred returns to native Promises.** Existing code chains `.always()` which is jQuery-specific. Conversion is a separate task.
- **Do NOT migrate `_AddTicket.vue` in this phase.** It is a self-contained modal and belongs to a later migration phase.
- **Do NOT add TypeScript or type annotations.** This is a state management migration, not a language migration.
- **Do NOT refactor the `Pagination.vue` component.** It works correctly with prop mutation and does not need store access.
- **Do NOT introduce new features** (e.g., optimistic updates, real-time sync) during migration.
- **Do NOT modify the REST API service (`Rest.js`).** The new `ticketListService.js` wraps it without changes.
- **Do NOT change the `resources/admin/routes.js` file.** Route structure is unchanged.
- **Do NOT delete `window.fsCurrentFilteredTickets` until Release 3** when all consumers are migrated.
- **Do NOT combine Release 2 and Release 3 into a single deploy.** Release 2 is the highest-risk change and must be validated independently.

---

## Monitoring Guidance

### After Each Release

1. **Browser Console:** Check for `undefined` property warnings, Vue reactivity warnings, or Pinia state errors on the Tickets page.
2. **Network Tab:** Verify the same REST API calls are made with the same parameters. No new endpoints, no missing calls, no duplicate calls.
3. **Vue DevTools → Pinia:** Confirm the `ticketList` store state matches expectations after page load and after filter interactions.
4. **Functional Smoke Test:** Load tickets → Apply filter → Change page → Search → Bulk select → Navigate to ticket → Back. Each step should behave identically to pre-migration.

### Red Flags That Require Immediate Rollback

- Blank ticket list on page load (store state not hydrated)
- Filters not persisting after page reload (localStorage sync broken)
- Pagination not working (store reactivity issue)
- Bulk actions selecting wrong tickets (ticket_selections state mismatch)
- URL query params ignored on load (mounted logic broken)
- Infinite fetch loop (watcher triggering fetch which triggers watcher)
- `Cannot read property of undefined` errors in template (state mapping wrong)

---

## Definition of Done

The migration is complete when ALL of the following are true:

- [ ] `AllTickets.vue` reads all ticket list data from `useTicketListStore()`
- [ ] `AllTickets.vue` `data()` contains only local UI state (modal flags, popovers, image errors)
- [ ] `_BulkActions.vue` reads `ticket_selections` from store, not from props
- [ ] `TicketsView.vue` reads filtered tickets from store, not from `window.fsCurrentFilteredTickets`
- [ ] `_TicketsMenu.vue` reads filtered tickets from store, not from `window.fsCurrentFilteredTickets`
- [ ] `window.fsCurrentFilteredTickets` is no longer set anywhere
- [ ] `window.fs_sub_params` is either removed or moved to store
- [ ] All 56 QA test cases from Release 2 pass
- [ ] All 11 QA test cases from Release 3 pass
- [ ] All 5 QA test cases from Release 4 pass
- [ ] `npm run production` succeeds with zero errors
- [ ] Zero console errors or warnings on the Tickets list page
- [ ] Vue DevTools shows `ticketList` store with correct reactive state
- [ ] Back-navigation from ViewTicket to AllTickets shows cached data (no loading spinner)
- [ ] Filters, sort, page size, layout, and field visibility persist across page reloads
- [ ] Bundle size increase is under 10KB gzipped compared to pre-migration

---

## Mandatory Regression Test List

Run this complete list after the final release to confirm zero regression.

| # | Area | Test | Pass |
|---|------|------|------|
| 1 | Load | Tickets list loads on fresh page visit | [ ] |
| 2 | Load | Filters restored from localStorage | [ ] |
| 3 | Load | URL query params override localStorage | [ ] |
| 4 | Filter | Status tab switching | [ ] |
| 5 | Filter | Waiting for Reply toggle | [ ] |
| 6 | Filter | Custom filter — each type (6 types) | [ ] |
| 7 | Filter | Active filter chips display | [ ] |
| 8 | Filter | Remove individual filter chip | [ ] |
| 9 | Filter | Clear all filters | [ ] |
| 10 | Filter | Advanced filter mode | [ ] |
| 11 | Filter | Advanced filter apply | [ ] |
| 12 | Filter | Advanced filter reset | [ ] |
| 13 | Search | Open search bar | [ ] |
| 14 | Search | Execute search | [ ] |
| 15 | Search | Cancel search | [ ] |
| 16 | Sort | Change sort column | [ ] |
| 17 | Sort | Change sort direction | [ ] |
| 18 | Page | Change page | [ ] |
| 19 | Page | Change page size | [ ] |
| 20 | Display | Toggle column visibility | [ ] |
| 21 | Display | Reset column visibility | [ ] |
| 22 | Display | Switch layout (default/compact) | [ ] |
| 23 | Select | Select all checkbox | [ ] |
| 24 | Select | Individual ticket checkbox | [ ] |
| 25 | Select | Indeterminate state | [ ] |
| 26 | Bulk | Bulk close | [ ] |
| 27 | Bulk | Bulk delete | [ ] |
| 28 | Bulk | Bulk assign agent | [ ] |
| 29 | Bulk | Bulk assign tags | [ ] |
| 30 | Bulk | Bulk reply | [ ] |
| 31 | Bulk | Show/hide bulk actions | [ ] |
| 32 | Save | Save search | [ ] |
| 33 | Save | Load saved search | [ ] |
| 34 | Save | Edit saved search | [ ] |
| 35 | Save | Delete saved search | [ ] |
| 36 | Nav | Click ticket → ViewTicket | [ ] |
| 37 | Nav | Ctrl+click → new tab | [ ] |
| 38 | Nav | Back from ViewTicket → cached list | [ ] |
| 39 | Nav | Sidebar mini-ticket-list | [ ] |
| 40 | Nav | Sidebar collapse/expand | [ ] |
| 41 | Key | Cmd+Alt+N (new ticket) | [ ] |
| 42 | Key | Cmd+Alt+Q (refresh) | [ ] |
| 43 | Key | Cmd+Alt+F (advanced toggle) | [ ] |
| 44 | Key | Cmd+Alt+R (reset) | [ ] |
| 45 | Key | Cmd+Alt+W (waiting toggle) | [ ] |
| 46 | Key | Cmd+Shift+Arrow (status cycle) | [ ] |
| 47 | Persist | Filters survive page reload | [ ] |
| 48 | Persist | Sort survives page reload | [ ] |
| 49 | Persist | Page size survives page reload | [ ] |
| 50 | Persist | Layout survives page reload | [ ] |
| 51 | Persist | Field visibility survives page reload | [ ] |
| 52 | Other | Add ticket modal opens and creates ticket | [ ] |
| 53 | Other | No console errors throughout all tests | [ ] |
