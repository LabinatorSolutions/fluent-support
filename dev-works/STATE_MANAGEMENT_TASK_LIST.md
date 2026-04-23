# State Management Task List

## Fluent Support — Pinia Migration Implementation Checklist

---

## Phase 1 — Foundation

### 1.1 Install & Configure Pinia

- [ ] Install Pinia: `npm install pinia`
- [ ] Create stores directory: `resources/admin/stores/`
- [ ] Create Pinia instance file: `resources/admin/stores/index.js`
  ```javascript
  import { createPinia } from 'pinia';
  export const pinia = createPinia();
  ```
- [ ] Register Pinia in `resources/admin/start.js` (before `.mount()`)
  ```javascript
  import { pinia } from './stores';
  framerwork.app.use(pinia).use(router).mount('#alpha_app');
  ```
- [ ] Verify existing app still mounts and works after Pinia registration
- [ ] Add path alias `@stores` in `webpack.mix.js` if desired (optional — `@/admin/stores/` already works via existing `@` alias)

### 1.2 Create Service Layer Directory

- [ ] Create services directory: `resources/admin/services/`
- [ ] Create base service helper (optional): `resources/admin/services/_base.js` — wraps Rest.js with consistent error formatting

### 1.3 Verify Build

- [ ] Run `npm run dev` — confirm no errors
- [ ] Run `npm run production` — confirm production build succeeds
- [ ] Load admin panel in browser — confirm existing functionality is unaffected

---

## Phase 2 — Shared / Global Stores

### 2.1 App Store

**File**: `resources/admin/stores/app.store.js`

- [ ] Create `useAppStore` with state: `config`, `hasPro`, `isFrontend`, `assetUrl`, `serverTime`, `appReady`, `isMobile`
- [ ] Create `initialize(appVars)` action to hydrate from `window.fluentSupportAdmin`
- [ ] Call `appStore.initialize()` in `resources/admin/start.js` after Pinia registration
- [ ] Expose `$t(string)` translation getter (reads from `i18n` on `window.fluentSupportAdmin`)
- [ ] Test: App loads, `has_pro` flag accessible from store

### 2.2 Permission Store

**File**: `resources/admin/stores/permission.store.js`

- [ ] Create `usePermissionStore` with state: `permissions[]`, `userId`, `role`
- [ ] Create getter: `can(permission)` — returns boolean
- [ ] Create getter: `canAny(permissionList)` — any match
- [ ] Create getter: `canAll(permissionList)` — all match
- [ ] Create `initialize(appVars)` action — hydrate from `appVars.me.permissions`
- [ ] Call `permissionStore.initialize()` in `resources/admin/start.js`
- [ ] Add route guard in `resources/admin/start.js` using `router.beforeEach`
- [ ] Add `meta.permission` to sensitive routes in `resources/admin/routes.js`:
  - Settings routes: `fst_manage_settings`
  - Reports sensitive tab: `fst_sensitive_data`
- [ ] Test: Unauthorized routes redirect to dashboard
- [ ] Test: `can()` matches existing `.includes()` checks

### 2.3 Shared Options Store

**File**: `resources/admin/stores/sharedOptions.store.js`

- [ ] Create `useSharedOptionsStore` with state for all shared dropdown data:
  - `agents: []`
  - `products: []`
  - `tags: []`
  - `mailboxes: []`
  - `ticketStatuses: {}`
  - `adminPriorities: {}`
  - `clientPriorities: {}`
  - `changeableStatuses: {}`
  - `ticketStatusGroups: {}`
- [ ] Create `initialize(appVars)` action — hydrate all from `window.fluentSupportAdmin`
- [ ] Create `refreshAgents()` action — calls `GET /agents` and updates state
- [ ] Create `refreshProducts()` action — calls `GET /products` and updates state
- [ ] Create `refreshTags()` action — calls `GET /ticket-tags` and updates state
- [ ] Call `sharedOptionsStore.initialize()` in `resources/admin/start.js`
- [ ] Test: All dropdowns in AllTickets.vue, ViewTicket.vue, _TicketSidebar.vue still populate correctly

---

## Phase 3 — Module Stores (Per Module)

### 3.1 Tickets Module

**Affected files**:
- `resources/admin/Modules/Tickets/AllTickets.vue` (1,948 lines)
- `resources/admin/Modules/Tickets/ViewTicket.vue` (1,661 lines)
- `resources/admin/Modules/Tickets/TicketsView.vue`
- `resources/admin/Modules/Tickets/_AddTicket.vue`
- `resources/admin/Modules/Tickets/_CreateResponse.vue`
- `resources/admin/Modules/Tickets/_EditResponse.vue`
- `resources/admin/Modules/Tickets/_TicketSidebar.vue` (980 lines)
- `resources/admin/Modules/Tickets/_BulkActions.vue`
- `resources/admin/Modules/Tickets/_templateInserter.vue`
- `resources/admin/Modules/Tickets/_SplitTicket.vue`
- `resources/admin/Modules/Tickets/_AIResponseGenerator.vue`
- `resources/admin/Modules/Tickets/_FluentBotAIResponseGenerator.vue`
- `resources/admin/Modules/Tickets/_FluentBoardsIntegration.vue`
- `resources/admin/Modules/Tickets/_active_agents.vue`
- `resources/admin/Modules/Tickets/parts/_TicketFilters.vue`
- `resources/admin/Modules/Tickets/parts/_Tags.vue`
- `resources/admin/Modules/Tickets/parts/_TaskTimer.vue`
- `resources/admin/Modules/Tickets/parts/_CollapsibleWidget.vue`
- `resources/admin/Modules/Tickets/parts/_CrmProfile.vue`
- `resources/admin/Modules/Tickets/parts/_CustomFieldForm.vue`
- `resources/admin/Modules/Tickets/parts/_LabelSearchDrawer.vue`
- `resources/admin/Modules/Tickets/parts/_WorkFlowSelector.vue`
- `resources/admin/Modules/Tickets/parts/RichFilters/RichFilter.vue`
- `resources/admin/Modules/Tickets/parts/RichFilters/_RichFilterItem.vue`

**Service**: `resources/admin/services/ticketService.js`

- [ ] Create `ticketService.js` with methods:
  - `fetchList(params)` — `GET /tickets`
  - `fetchById(id, params)` — `GET /tickets/{id}`
  - `create(data)` — `POST /tickets`
  - `updateProperty(id, propName, propValue)` — `PUT /tickets/{id}/property`
  - `close(id)` — `POST /tickets/{id}/close`
  - `reopen(id)` — `POST /tickets/{id}/re-open`
  - `merge(id, data)` — `POST /tickets/{id}/merge_tickets`
  - `split(id, data)` — `POST /tickets/{id}/split_ticket`
  - `fetchDraft(id)` — `GET /tickets/{id}/draft`
  - `deleteDraft(id)` — `DELETE /tickets/{id}/draft`
  - `addResponse(id, data)` — `POST /tickets/{id}/responses`
  - `bulkAction(data)` — `POST /tickets/bulk-actions`
  - `bulkDelete(data)` — `DELETE /tickets/bulk`
  - `searchContact(query)` — `GET /tickets/search-contact`
  - `fetchCustomerTickets(customerId)` — `GET /tickets/customer_tickets/{id}`
  - `saveLabelSearch(data)` — `POST /tickets/label-search`
  - `fetchLabelSearches()` — `GET /tickets/label-search`
  - `deleteLabelSearch(id)` — `DELETE /tickets/{id}/label-search`
  - `fetchCustomData(id)` — `GET /tickets/{id}/custom-data`
  - `fetchMyStats(params)` — `GET /tickets/my_stats`
  - `ping()` — `GET /tickets/ping`

**Store**: `resources/admin/stores/tickets.store.js`

- [ ] Create `useTicketsStore` with state:
  - `ticketsById: {}` (ID-indexed map)
  - `ticketIds: []` (current page IDs)
  - `pagination: { per_page, current_page, total }`
  - `filters: { status_type, agent_id, product_id, priority, client_priority, mailbox_id, waiting_for_reply, ticket_tags }`
  - `search: ''`
  - `filterType: 'simple'`
  - `advancedFilters: []`
  - `orderBy: 'id'`, `orderType: 'DESC'`
  - `ticketSelections: []`
  - `activeTicketId: null`
  - `conversations: []`
  - `loading: false`, `firstLoad: true`
  - `_lastFetchTime: null`
- [ ] Create getters: `tickets`, `activeTicket`, `hasStaleCache`, `activeFilters`
- [ ] Create actions:
  - `fetchTickets({ silent, force })`
  - `fetchTicketById(id)`
  - `setFilters(filters, router)`
  - `setPage(page)`, `setPerPage(perPage)`
  - `setSearch(search)`
  - `createTicket(data)`
  - `updateTicketProperty(id, propName, propValue)`
  - `closeTicket(id)`
  - `bulkAction(action, ticketIds, extraData)`
  - `invalidateCache()`
  - `loadFiltersFromStorage()`
  - `saveFiltersToStorage()`
  - `syncFiltersFromUrl(route)`
  - `syncFiltersToUrl(router)`
- [ ] Move list state from `AllTickets.vue` `data()` → store `state`
- [ ] Map store state in `AllTickets.vue` via `mapState(useTicketsStore, [...])`
- [ ] Map store actions in `AllTickets.vue` via `mapActions(useTicketsStore, [...])`
- [ ] Move active ticket state from `ViewTicket.vue` `data()` → store `state`
- [ ] Replace `this.$get('tickets', ...)` calls with store actions
- [ ] Replace `this.$put('tickets/...')` calls with store actions
- [ ] Implement filter persistence: load from localStorage on mount, save on change
- [ ] Implement URL param sync: read on mount, update on filter change
- [ ] Add cache invalidation after create/update/delete mutations
- [ ] Test: Ticket list pagination (page change, per_page change)
- [ ] Test: All filters (status, agent, product, priority, search, advanced)
- [ ] Test: URL query params reflect filter state
- [ ] Test: Browser back/forward navigation
- [ ] Test: SPA navigation from ticket list → ticket detail → back to list (cache hit)
- [ ] Test: Create ticket → list refreshes
- [ ] Test: Update ticket property → data updates
- [ ] Test: Delete/close ticket → list updates
- [ ] Test: Bulk actions work
- [ ] Test: Label search save/load/delete works
- [ ] Test: Draft save/load works

### 3.2 Customers Module

**Affected files**:
- `resources/admin/Modules/Customers/Customers.vue` (515 lines)
- `resources/admin/Modules/Customers/CustomerPage.vue`
- `resources/admin/Modules/Customers/_CustomerForm.vue`

**Service**: `resources/admin/services/customerService.js`

- [ ] Create `customerService.js` with methods:
  - `fetchList(params)` — `GET /customers`
  - `create(data)` — `POST /customers`
  - `update(id, data)` — `PUT /customers/{id}`
  - `delete(id)` — `DELETE /customers/{id}`
  - `bulkDelete(ids)` — `DELETE /customers/bulk-delete`
  - `fetchCustomerFields(id)` — `GET /customers/customerField/{id}`

**Store**: `resources/admin/stores/customers.store.js`

- [ ] Create `useCustomersStore` with state:
  - `customers: []`
  - `pagination: { per_page, current_page, total }`
  - `search: ''`
  - `status: ''`
  - `activeCustomer: null`
  - `loading: false`, `firstLoad: true`
  - `_lastFetchTime: null`
- [ ] Create actions: `fetchCustomers`, `createCustomer`, `updateCustomer`, `deleteCustomer`, `bulkDelete`, `invalidateCache`
- [ ] Move local state from `Customers.vue` → store
- [ ] Map store state via `mapState`/`mapActions` in `Customers.vue`
- [ ] Replace direct `this.$get`/`this.$del` calls with store actions
- [ ] Implement in-memory cache with stale check
- [ ] Test: Customer list pagination
- [ ] Test: Customer search
- [ ] Test: Create customer → list refreshes
- [ ] Test: Edit customer → data updates
- [ ] Test: Delete customer → removed from list
- [ ] Test: Bulk delete works
- [ ] Test: SPA navigation cache (back to list shows cached data)

### 3.3 Saved Replies Module

**Affected files**:
- `resources/admin/Modules/SavedReplies/Replies.vue`
- `resources/admin/Modules/Tickets/_templateInserter.vue`

**Service**: `resources/admin/services/savedReplyService.js`

- [ ] Create `savedReplyService.js` with methods:
  - `fetchList(params)` — `GET /saved-replies`
  - `create(data)` — `POST /saved-replies`
  - `update(id, data)` — `PUT /saved-replies/{id}`
  - `delete(id)` — `DELETE /saved-replies/{id}`

**Store**: `resources/admin/stores/savedReplies.store.js`

- [ ] Create `useSavedRepliesStore` with state:
  - `replies: []`
  - `pagination: { per_page, current_page, total }`
  - `search: ''`
  - `selectedProduct: ''`
  - `editingReply: null`
  - `loading: false`
  - `_lastFetchTime: null`
- [ ] Create actions: `fetchReplies`, `createOrUpdate`, `deleteReply`, `invalidateCache`
- [ ] Move local state from `Replies.vue` → store
- [ ] Replace `window.fst_last_replies` cache in `_templateInserter.vue` with store cache
- [ ] Map store state via `mapState`/`mapActions`
- [ ] Test: Reply list pagination
- [ ] Test: Search and product filter
- [ ] Test: Create/edit/delete replies
- [ ] Test: Template inserter in ViewTicket reads from store cache
- [ ] Test: SPA navigation cache

### 3.4 Dashboard Module

**Affected files**:
- `resources/admin/Modules/Dashboard/Dashboard.vue` (411 lines)
- `resources/admin/Modules/Dashboard/DashboardStatistics.vue`
- `resources/admin/Modules/Dashboard/DashboardDraggableContent.vue`
- `resources/admin/Modules/Dashboard/DashboardSettingsDrawer.vue`
- `resources/admin/Modules/Dashboard/AgentPerformance.vue`
- `resources/admin/Modules/Dashboard/MentionedTicket.vue`
- `resources/admin/Modules/Dashboard/SuggestedTicket.vue`
- `resources/admin/Modules/Dashboard/TicketsByProduct.vue`
- `resources/admin/Modules/Dashboard/TicketStatistics.vue`

**Service**: `resources/admin/services/dashboardService.js`

- [ ] Create `dashboardService.js` with methods:
  - `fetchMyStats(params)` — `GET /tickets/my_stats`
  - `fetchAgentPerformance(params)` — `GET /tickets/agent_performance`

**Store**: `resources/admin/stores/dashboard.store.js`

- [ ] Create `useDashboardStore` with state:
  - `totalData: {}` (dashboard widget data)
  - `dashboardNotice: null`
  - `dashboardParam: {}` (widget ordering/visibility)
  - `loading: false`, `appReady: false`
  - `_lastFetchTime: null`
- [ ] Create actions: `fetchDashboardData`, `invalidateCache`
- [ ] Move `total_data`, `dashboard_notice`, `dashboard_param` from `Dashboard.vue` → store
- [ ] Map store state in Dashboard and child components via `mapState`
- [ ] Eliminate prop drilling to child dashboard widgets
- [ ] Test: Dashboard loads stats correctly
- [ ] Test: Widget ordering/visibility
- [ ] Test: SPA navigation back to dashboard uses cache

### 3.5 Reports Module

**Affected files**:
- `resources/admin/Modules/Reports/Report.vue`
- `resources/admin/Modules/Reports/Overview.vue` (1,013 lines)
- `resources/admin/Modules/Reports/AgentReports.vue`
- `resources/admin/Modules/Reports/PersonalReports.vue`
- `resources/admin/Modules/Reports/MyPerformanceReport.vue`
- `resources/admin/Modules/Reports/ProductReports.vue`
- `resources/admin/Modules/Reports/ProductReportSumary.vue`
- `resources/admin/Modules/Reports/BusinessBoxReports.vue`
- `resources/admin/Modules/Reports/BusinessBoxReportSummary.vue`
- `resources/admin/Modules/Reports/ActivityByTimeOfDay.vue`
- `resources/admin/Modules/Reports/TimeSheet/TimeSheet.vue`
- `resources/admin/Modules/Reports/TimeSheet/ByAgents.vue`
- `resources/admin/Modules/Reports/TimeSheet/ByCustomers.vue`
- `resources/admin/Modules/Reports/TimeSheet/ByTickets.vue`
- `resources/admin/Modules/Reports/Charts/BarChartBase.vue`
- `resources/admin/Modules/Reports/Charts/ResolveGrowth.vue`
- `resources/admin/Modules/Reports/Charts/ResponseGrowth.vue`
- `resources/admin/Modules/Reports/Charts/TicketsGrowth.vue`
- `resources/admin/Modules/Reports/Parts/_SideBar.vue`

**Service**: `resources/admin/services/reportService.js`

- [ ] Create `reportService.js` with methods:
  - `fetchStats(params)` — `GET /reports/stats`
  - `fetchOverview(params)` — `GET /reports/overview`
  - `fetchDayTimeStats(params)` — `GET /reports/day-time-stats`
  - `fetchTimesheetByTickets(params)` — `GET /reports/timesheet/by-tickets`
  - `fetchTimesheetByAgents(params)` — `GET /reports/timesheet/by-agents`
  - `fetchTimesheetByCustomers(params)` — `GET /reports/timesheet/by-customers`

**Store**: `resources/admin/stores/reports.store.js`

- [ ] Create `useReportsStore` with state:
  - `stats: {}`
  - `dateRange: []`
  - `selectedAgents: []`
  - `selectedProducts: []`
  - `loading: false`
  - `activeTab: 'overview'`
- [ ] Create actions: `fetchOverview`, `fetchStats`, `setDateRange`, `setActiveTab`
- [ ] Move shared report data from individual components → store
- [ ] Map store state in Report.vue and sub-tab components
- [ ] Test: Date range selection updates all tabs
- [ ] Test: Agent/product filters work
- [ ] Test: TimeSheet tabs load correctly
- [ ] Test: Charts render with store data

### 3.6 Workflows Module

**Affected files**:
- `resources/admin/Modules/Workflows/AllWorkflows.vue` (534 lines)
- `resources/admin/Modules/Workflows/EditWorkFlow.vue`
- `resources/admin/Modules/Workflows/_ActionAdder.vue`
- `resources/admin/Modules/Workflows/_ActionMap.vue`
- `resources/admin/Modules/Workflows/_ActionMappers.vue`
- `resources/admin/Modules/Workflows/_TiggerMappers.vue`
- `resources/admin/Modules/Workflows/_TriggerConditionalGroup.vue`

**Service**: `resources/admin/services/workflowService.js`

- [ ] Create `workflowService.js` with methods:
  - `fetchActions(workflowId)` — `GET /workflows/{id}/actions`
  - `runWorkflow(workflowId)` — `POST /workflows/{id}/run`
  - (Additional endpoints as discovered in AllWorkflows.vue)

**Store**: `resources/admin/stores/workflows.store.js`

- [ ] Create `useWorkflowsStore` with state:
  - `workflows: []`
  - `pagination: { per_page, current_page, total }`
  - `search: ''`
  - `activeWorkflow: null`
  - `loading: false`
  - `_lastFetchTime: null`
- [ ] Create actions: `fetchWorkflows`, `fetchWorkflowById`, `invalidateCache`
- [ ] Move local state from `AllWorkflows.vue` → store
- [ ] Map store state via `mapState`/`mapActions`
- [ ] Test: Workflow list pagination
- [ ] Test: Workflow search
- [ ] Test: Edit workflow → data persists
- [ ] Test: SPA navigation cache

### 3.7 MailBoxes Module

**Affected files**:
- `resources/admin/Modules/MailBoxes/ChooseMailBox.vue`
- `resources/admin/Modules/MailBoxes/MailBoxRoot.vue`
- `resources/admin/Modules/MailBoxes/MailBoxSettings.vue`
- `resources/admin/Modules/MailBoxes/BoxEmailSettings.vue`
- `resources/admin/Modules/MailBoxes/MailBoxEmailPiping.vue`
- `resources/admin/Modules/MailBoxes/MoveTicket.vue`

**Service**: `resources/admin/services/mailboxService.js`

- [ ] Create `mailboxService.js` with methods:
  - `fetchList()` — `GET /mailboxes`
  - `create(data)` — `POST /mailboxes`
  - `fetchEmailStatus(boxId)` — `GET /email-box/{id}/status`
  - `issueTestEmail(boxId, data)` — `POST /email-box/{id}/issue-email`

**Store**: `resources/admin/stores/mailboxes.store.js`

- [ ] Create `useMailboxesStore` with state:
  - `mailboxes: []`
  - `activeMailbox: null`
  - `loading: false`
  - `_lastFetchTime: null`
- [ ] Create actions: `fetchMailboxes`, `createMailbox`, `setActiveMailbox`, `invalidateCache`
- [ ] Move local state from `ChooseMailBox.vue` → store
- [ ] Map store state in MailBox components
- [ ] Test: Mailbox list loads
- [ ] Test: Create mailbox → list updates
- [ ] Test: Mailbox settings save correctly
- [ ] Test: Email piping configuration works

### 3.8 Settings Module

**Affected files**:
- `resources/admin/Modules/Settings/SettingsView.vue`
- `resources/admin/Modules/Settings/BusinessSettings.vue`
- `resources/admin/Modules/Settings/Products.vue`
- `resources/admin/Modules/Settings/TicketTags.vue`
- `resources/admin/Modules/Settings/SupportStaffs.vue` (632 lines)
- `resources/admin/Modules/Settings/IntegrationView.vue`
- `resources/admin/Modules/Settings/RecaptchaView.vue`
- `resources/admin/Modules/Settings/TicketFormConfig.vue`
- `resources/admin/Modules/Settings/CustomFields.vue`
- `resources/admin/Modules/Settings/LicenseManagement.vue`
- `resources/admin/Modules/Settings/FluentCRMIntegration.vue`
- `resources/admin/Modules/Settings/AutoCloseSettings.vue`
- `resources/admin/Modules/Settings/IncomingWebhook.vue`
- `resources/admin/Modules/Settings/OpenAIIntegration.vue`
- `resources/admin/Modules/Settings/FluentBotIntegration.vue`
- `resources/admin/Modules/Settings/IntegrationStatuses.vue`
- `resources/admin/Modules/Settings/EmailNotifications.vue`
- `resources/admin/Modules/Settings/DiscordIntegration.vue`
- `resources/admin/Modules/Settings/TwilioIntegration.vue`
- `resources/admin/Modules/Settings/CustomFields/CustomFields.vue`
- `resources/admin/Modules/Settings/_CustomFieldForm.vue`
- `resources/admin/Modules/Settings/EmailParts/*.vue` (7 email template components)
- `resources/admin/Modules/Settings/FileUploadSettings/*.vue` (3 components)
- `resources/admin/Modules/Settings/TicketImporter/*.vue` (5 components)

**Service**: `resources/admin/services/settingsService.js`

- [ ] Create `settingsService.js` with methods:
  - `fetchSettings()` — `GET /settings`
  - `saveSettings(data)` — `POST /settings`
  - `fetchSettingsMenu()` — `GET /settings/settings-menu`
  - `fetchIntegration()` — `GET /settings/integration`
  - `saveIntegration(data)` — `POST /settings/integration`
  - Additional methods for each settings sub-page

**Service**: `resources/admin/services/productService.js`

- [ ] Create `productService.js` with methods:
  - `fetchList(params)` — `GET /products`
  - `create(data)` — `POST /products`
  - `update(id, data)` — `PUT /products/{id}`
  - `delete(id)` — `DELETE /products/{id}`

**Store**: `resources/admin/stores/settings.store.js`

- [ ] Create `useSettingsStore` with state:
  - `businessSettings: {}`
  - `settingsMenu: []`
  - `loading: false`, `saving: false`
- [ ] Create actions: `fetchSettings`, `saveSettings`, `fetchSettingsMenu`
- [ ] Move settings data from individual settings pages → store (shared data only)
- [ ] Note: Many settings sub-pages are self-contained and may not benefit from store migration. Prioritize shared state (menu, business settings) and leave isolated sub-pages as-is.
- [ ] Test: Settings menu loads
- [ ] Test: Business settings save and persist
- [ ] Test: Product CRUD works (after refreshOptions integration with sharedOptionsStore)
- [ ] Test: Tag CRUD works

### 3.9 Activity Logger Module

**Affected files**:
- `resources/admin/Modules/ActivityLogger/Activity.vue`
- `resources/admin/Modules/ActivityLogger/ActivityLogger.vue`
- `resources/admin/Modules/ActivityLogger/AIActivityLogger.vue`
- `resources/admin/Modules/ActivityLogger/_ActivitySettings.vue`
- `resources/admin/Modules/ActivityLogger/_AIActivitySettings.vue`

**Service**: `resources/admin/services/activityLogService.js`

- [ ] Create `activityLogService.js` with methods:
  - `fetchActivities(params)` — `GET /activity-logger`
  - `fetchSettings()` — `GET /activity-logger/settings`
  - `saveSettings(data)` — `POST /activity-logger/settings`
  - `fetchAIActivities(params)` — `GET /ai-activity-logger`
  - `fetchAISettings()` — `GET /ai-activity-logger/settings`
  - `saveAISettings(data)` — `POST /ai-activity-logger/settings`

**Store**: `resources/admin/stores/activityLog.store.js`

- [ ] Create `useActivityLogStore` with state:
  - `activities: []`
  - `pagination: { per_page, current_page, total }`
  - `filters: {}`
  - `dateRange: null`
  - `settings: {}`
  - `loading: false`
- [ ] Create actions: `fetchActivities`, `fetchSettings`, `saveSettings`
- [ ] Move local state from `ActivityLogger.vue` → store
- [ ] Test: Activity list loads with pagination
- [ ] Test: Date range filter works
- [ ] Test: Settings save correctly

---

## Phase 4 — Cleanup & Integration

### 4.1 Remove Duplicate Local State

- [ ] Audit all components that copy `this.appVars.support_agents` to local `data()` — replace with `mapState(useSharedOptionsStore, ['agents'])`
  - `resources/admin/Modules/Tickets/ViewTicket.vue`
  - `resources/admin/Modules/Tickets/AllTickets.vue`
  - `resources/admin/Modules/Tickets/_TicketSidebar.vue`
  - `resources/admin/Modules/Tickets/_AddTicket.vue`
  - `resources/admin/Modules/Reports/Overview.vue`
  - `resources/admin/Modules/Reports/TimeSheet/TimeSheet.vue`
  - (10+ additional components)
- [ ] Audit all components that copy `this.appVars.support_products` to local `data()` — replace with store getter
  - `resources/admin/Modules/Tickets/ViewTicket.vue`
  - `resources/admin/Modules/SavedReplies/Replies.vue`
  - `resources/admin/Modules/Tickets/_FluentBotAIResponseGenerator.vue`
  - (8+ additional components)
- [ ] Audit all components that copy `this.appVars.admin_priorities` / `this.appVars.client_priorities` — replace with store
- [ ] Audit all components that copy `this.appVars.mailboxes` — replace with store

### 4.2 Reduce Props Drilling

- [ ] `ViewTicket.vue` → `_CreateResponse.vue`: Replace `ticket` prop with store access where appropriate
- [ ] `ViewTicket.vue` → `_TicketSidebar.vue`: Replace `ticket`, `agents` props with store access
- [ ] `AllTickets.vue` → `_BulkActions.vue`: Replace `ticket_selections`, `filters` props with store access
- [ ] `AllTickets.vue` → `_TicketFilters.vue`: Replace `filters` props with store access
- [ ] `Dashboard.vue` → child widgets: Replace `total_data`, `dashboard_param` props with store access

### 4.3 Replace $emit-Based Refresh Patterns

- [ ] Replace `@created` emit in `_AddTicket.vue` with `ticketsStore.invalidateCache()` + `ticketsStore.fetchTickets()`
- [ ] Replace `@created` emit in `_CreateResponse.vue` with store action for adding conversation
- [ ] Replace `@fetch` emit from `Pagination.vue` with direct store action call
- [ ] Replace filter change emits in `_TicketFilters.vue` with store `setFilters()` action
- [ ] Replace `@close` modal emits with store state flags where applicable

### 4.4 Standardize Permission Checks

- [ ] Replace all `.indexOf('permission') != -1` patterns with `can('permission')`:
  - `resources/admin/Modules/Reports/Report.vue` (10 occurrences)
  - `resources/admin/Modules/ActivityLogger/ActivityLogger.vue`
  - (Additional files as found)
- [ ] Replace all `this.appVars.me.permissions.includes(...)` with `can(...)`:
  - `resources/admin/Modules/Tickets/ViewTicket.vue` (3 occurrences)
  - `resources/admin/Modules/Dashboard/Dashboard.vue`
  - `resources/admin/Modules/Tickets/_TicketSidebar.vue`
  - `resources/admin/Modules/Settings/SupportStaffs.vue`

### 4.5 Remove Window Global Dependencies

- [ ] Replace `window.fluentSupportAdmin.has_pro` reads with `appStore.hasPro`
- [ ] Replace `window.fluentSupportAdmin.asset_url` reads with `appStore.assetUrl`
- [ ] Replace `window.fluentSupportAdmin.is_frontend` reads with `appStore.isFrontend`
- [ ] Replace `window.fst_last_replies` cache in `_templateInserter.vue` with `savedRepliesStore` cache
- [ ] Note: Keep `window.fluentSupportAdmin` intact for backward compatibility with pro plugin and WordPress hooks. Do not remove it — just stop reading it directly in components.

### 4.6 Remove Redundant localStorage Logic

- [ ] Consolidate filter persistence into store actions (stores handle their own localStorage read/write)
- [ ] Remove manual `this.$saveData('tickets_filter', ...)` calls from `AllTickets.vue` — handled by store
- [ ] Remove manual `this.$getData('tickets_filter', ...)` calls from `AllTickets.vue` — handled by store
- [ ] Keep `$saveData`/`$getData` in FluentFramework.js for non-migrated components and pro plugin compatibility

---

## Phase 5 — Optimization

### 5.1 Request Deduplication

- [ ] Add AbortController support to `resources/admin/Bits/Rest.js`:
  - Wrap jQuery AJAX with AbortController signal
  - Expose `abort()` method on request promises
- [ ] Add request deduplication to service layer:
  - Track in-flight requests by URL+params hash
  - Return existing promise if duplicate request is detected
- [ ] Implement in ticket list (rapid filter changes cause overlapping requests)
- [ ] Implement in search (rapid typing causes overlapping requests)

### 5.2 AbortController Integration

- [ ] Add `abortController` to stores that manage list fetches:
  - `tickets.store.js` — abort previous fetch on new filter change
  - `customers.store.js` — abort previous fetch on search change
  - `activityLog.store.js` — abort on filter change
- [ ] Cancel pending requests on route navigation:
  - Add `router.beforeEach` hook that calls `store.abortPending()` for the departing module

### 5.3 Background Refresh

- [ ] Implement debounced silent refresh on SPA navigation:
  - When user navigates back to tickets list, check `_lastFetchTime`
  - If stale (>5 min), show cached data immediately and fetch fresh data in background
  - On response, merge new data into store (IDs that still exist update, new IDs added, removed IDs pruned)
- [ ] Add `refreshInBackground()` action to list stores
- [ ] Ensure loading spinner does NOT show during background refresh (use `silent` flag)

### 5.4 Centralized Error Handling

- [ ] Create error handling middleware in Pinia:
  - Option A: Pinia plugin that wraps all actions with try/catch
  - Option B: Service layer catches and normalizes errors before store receives them
- [ ] Standardize error notification format across all modules
- [ ] Add retry logic for transient errors (network timeout, 5xx)

### 5.5 Production Logging

- [ ] Add `import.meta.env.MODE` check (or `process.env.NODE_ENV`) to suppress console logs in production
- [ ] Add optional Pinia plugin for logging state changes in development:
  ```javascript
  if (process.env.NODE_ENV === 'development') {
      pinia.use(({ store }) => {
          store.$onAction(({ name, args }) => {
              console.log(`[${store.$id}] ${name}`, args);
          });
      });
  }
  ```
- [ ] Remove any existing `console.log` debug statements found in production code

---

## Phase 6 — Customer Portal (Optional / Separate Track)

The customer portal is a separate Vue app with its own entry point and REST client. Migration follows the same pattern but is simpler due to fewer components.

**Affected files**:
- `resources/customer_portal/portal.js`
- `resources/customer_portal/Application.vue`
- `resources/customer_portal/components/Dashboard.vue`
- `resources/customer_portal/components/Tickets.vue`
- `resources/customer_portal/components/Ticket.vue`
- `resources/customer_portal/components/CreateTicket.vue`
- `resources/customer_portal/components/InlineReply.vue`
- `resources/customer_portal/components/_AttachmentForm.vue`
- `resources/customer_portal/components/_CustomFieldForm.vue`
- `resources/customer_portal/components/_TicketFilter.vue`
- `resources/customer_portal/components/pieces/Pagination.vue`
- `resources/customer_portal/Rest.js`
- `resources/customer_portal/routes.js`

- [ ] Install Pinia in portal entry (`portal.js`)
- [ ] Create `resources/customer_portal/stores/` directory
- [ ] Create `portalTickets.store.js` — ticket list, filters, pagination
- [ ] Create `portalApp.store.js` — customer status, portal config
- [ ] Create `resources/customer_portal/services/portalTicketService.js`
- [ ] Migrate `Tickets.vue` list state to store
- [ ] Migrate `Ticket.vue` active ticket state to store
- [ ] Test: Portal ticket list with filters and pagination
- [ ] Test: Create ticket flow
- [ ] Test: View ticket and add reply
- [ ] Test: Logout functionality

---

## Verification Checklist (Run After Each Phase)

- [ ] `npm run dev` — no build errors
- [ ] `npm run production` — production build succeeds
- [ ] Admin panel loads without console errors
- [ ] Customer portal loads without console errors
- [ ] All existing features continue to work (no regression)
- [ ] Browser DevTools → Vue DevTools → Pinia stores visible and correct
- [ ] No duplicate API calls visible in Network tab
- [ ] localStorage still works for filter persistence
- [ ] Pro plugin features still work (WordPress hooks intact)
