# STATE_TICKETS_VIEW_TASK_LIST.md — Pinia Migration Plan

## Context

The Ticket View page (`ViewTicket.vue` — 1661 lines) manages ALL state via local `data()` and makes ~16 direct REST calls via `this.$get/post/put/del`. This creates:
- Props drilling: `ticket`, `watchers`, `fluentcrm_profile` passed to 4+ child components
- No shared state: TicketSidebar makes its own API calls and emits `@refresh` to re-fetch the parent
- Tight coupling: child components can't read ticket data without receiving it as props

**Goal:** Migrate to Pinia following the established `ticketList.store.js` + `ticketListService.js` pattern. Incremental — not a rewrite.

---

## Files to Create

| File | Purpose |
|------|---------|
| `resources/admin/services/ticketViewService.js` | Thin REST wrapper (all 16 API methods) |
| `resources/admin/stores/ticketView.store.js` | Pinia store (shared ticket state + actions) |

## Files to Modify

| File | Scope |
|------|-------|
| `resources/admin/Modules/Tickets/ViewTicket.vue` | Replace `data()` → store for shared state, delegate API calls to store/service |
| `resources/admin/Modules/Tickets/_TicketSidebar.vue` | Read from store instead of props (Phase 4) |

## Files Unchanged

`_CreateResponse.vue`, `_EditResponse.vue`, `_SplitTicket.vue`, `_active_agents.vue`, `_FluentBoardsIntegration.vue`, `_AttachmentForm.vue`, all `parts/*` components

---

## Decision: Store vs Local State

**In the store** (shared across components):

| Property | Why |
|----------|-----|
| `ticket` | Read by ViewTicket, TicketSidebar, CreateResponse, ActiveAgents |
| `conversations` | Mutated on response create/edit/delete, displayed in ViewTicket |
| `loading`, `updating` | Controls action button states across teleported elements |
| `watchers`, `watcherIds` | Shared between top bar bookmark button and TicketSidebar |
| `fluentcrm_profile` | Passed from ViewTicket → TicketSidebar → FluentCrmProfile |
| `draftData`, `draftResponse`, `showDraft` | Shared between ViewTicket draft card and CreateResponse |
| `ticketNotFound` | Set by fetch error, displayed in template |

**Stays local in ViewTicket** (UI-only, single-component):

| Property | Why |
|----------|-----|
| `show_response_box`, `edit_response_modal`, `editing_response` | Pure UI toggle flags |
| `show_merge_modal`, `show_watcher_modal`, `split_ticket_modal`, `show_fbs_add_task_modal` | Modal open/close flags |
| `customer_tickets`, `tickets_to_merge`, `filteredMergeSelectedTickets`, `pagination` | Merge modal transient data |
| `split_ticket`, `close_ticket_silently`, `conversationRefs` | Feature-specific transient data |
| `ticketSummary`, `customerSentiment`, `ResponseLoader` | AI feature display data |
| `draftReplyPermission`, `draftReplyApprovePermission`, `deleteTicketPermission` | Permission booleans computed once per fetch |
| `products`, `agents`, `admin_priorities`, `client_priorities`, `mailboxes`, `ticket_statuses` | Static reference data from `appVars` — **must NOT go in Pinia getters** (non-reactive `window.*` causes caching bug) |

---

## Phase 1: Service Layer + Store Shell

### Task 1.1 — Create `ticketViewService.js`

```
resources/admin/services/ticketViewService.js
```

All 16 REST methods extracted from ViewTicket.vue:

```js
import Rest from '@/admin/Bits/Rest';

export default {
    // Core
    getTicket(ticketId, params = {}) {
        return Rest.get(`tickets/${ticketId}`, params);
    },

    // Property update
    updateProperty(ticketId, propName, propValue) {
        return Rest.put(`tickets/${ticketId}/property`, {
            prop_name: propName, prop_value: propValue,
        });
    },

    // Lifecycle
    closeTicket(ticketId, closeSilently = 'no') {
        return Rest.post(`tickets/${ticketId}/close`, { close_ticket_silently: closeSilently });
    },
    reopenTicket(ticketId) {
        return Rest.post(`tickets/${ticketId}/re-open`);
    },
    deleteTicket(ticketId) {
        return Rest.delete(`tickets/${ticketId}/delete`);
    },

    // Responses
    deleteResponse(ticketId, conversationId) {
        return Rest.delete(`tickets/${ticketId}/responses/${conversationId}`);
    },
    approveDraftResponse(ticketId, responseId, content) {
        return Rest.put(`tickets/${ticketId}/approve_draft_response/${responseId}`, { content });
    },

    // Drafts
    getDraft(ticketId) {
        return Rest.get(`tickets/${ticketId}/draft`);
    },
    deleteDraft(draftId) {
        return Rest.delete(`tickets/${draftId}/draft`);
    },

    // Merge/Split (Pro)
    getCustomerTickets(customerId, excludeTicketId, page, perPage) {
        return Rest.get(`tickets/customer_tickets/${customerId}`, {
            exclude_ticket_id: excludeTicketId, page, per_page: perPage,
        });
    },
    mergeTickets(ticketId, ticketIdsToMerge) {
        return Rest.post(`tickets/${ticketId}/merge_tickets`, { ticket_to_merge: ticketIdsToMerge });
    },
    splitTicket(ticketId, splitData) {
        return Rest.post(`tickets/${ticketId}/split_ticket`, { split_ticket: splitData });
    },

    // Watchers
    addWatchers(ticketId, watcherIds) {
        return Rest.post(`tickets/${ticketId}/add_watchers`, { watchers: watcherIds });
    },

    // Mailbox
    changeMailbox(currentMailboxId, newMailboxId, ticketIds) {
        return Rest.put(`mailboxes/${currentMailboxId}/move_tickets`, {
            new_box_id: newMailboxId, ticket_ids: ticketIds, move_type: 'Custom',
        });
    },

    // AI
    getTicketSummary(ticketId) {
        return Rest.post(`openai/${ticketId}/get-ticket-summary`, { type: 'ticketSummary' });
    },
    getCustomerSentiment(ticketId) {
        return Rest.post(`openai/${ticketId}/get-ticket-tone`, { type: 'ticketTone' });
    },
};
```

### Task 1.2 — Create `ticketView.store.js`

```
resources/admin/stores/ticketView.store.js
```

```js
import { defineStore } from 'pinia';
import ticketViewService from '@/admin/services/ticketViewService';

export const useTicketViewStore = defineStore('ticketView', {
    state: () => ({
        ticket: {},
        conversations: [],
        loading: false,
        updating: false,
        watchers: [],
        watcherIds: [],
        fluentcrm_profile: false,
        draftData: null,
        draftResponse: null,
        showDraft: false,
        ticketNotFound: '',
    }),

    getters: {
        ticketId:    (state) => state.ticket?.id || null,
        customerId:  (state) => state.ticket?.customer_id || null,
        isClosed:    (state) => state.ticket?.status === 'closed',
        hasTicket:   (state) => !!(state.ticket && state.ticket.id),
    },

    actions: {
        // --- Core ---
        fetchTicket(ticketId) { ... },       // calls ticketViewService.getTicket
        fetchDraft(ticketId) { ... },        // calls ticketViewService.getDraft
        discardDraft(draftId) { ... },       // calls ticketViewService.deleteDraft

        // --- Ticket mutations ---
        updateTicketProperty(ticketId, propName, propValue) { ... },
        closeTicket(ticketId, closeSilently) { ... },
        reopenTicket(ticketId) { ... },
        deleteTicket(ticketId) { ... },

        // --- Conversations ---
        addConversation(conversation) { this.conversations.unshift(conversation); },
        removeConversation(conversationId) {
            this.conversations = this.conversations.filter(c => c.id !== conversationId);
        },

        // --- Watchers ---
        setWatchers(watchers) { ... },

        // --- Reset ---
        $reset() { /* reset all state to initial values */ },
    },
});
```

**Key design rules:**
- Actions return jQuery deferreds (from service) so the component can chain `.then()` for UI side effects
- Store does NOT call `$notify` / `$handleError` — those stay in the component (they rely on Vue mixins)
- `window.fluentSupportAdmin` is accessed only inside actions, NEVER in getters (reactivity bug)
- `$reset()` is called on route change + `beforeUnmount`

**Verification:** `npm run dev` builds without errors.

---

## Phase 2: Wire Store into ViewTicket — Core Data Path

### Task 2.1 — Import store and map state/actions in ViewTicket.vue

Add to computed:
```js
...mapState(useTicketViewStore, [
    'ticket', 'conversations', 'loading', 'updating',
    'watchers', 'watcherIds', 'fluentcrm_profile',
    'draftData', 'draftResponse', 'showDraft', 'ticketNotFound',
    'hasTicket', 'isClosed',
]),
```

Add to methods:
```js
...mapActions(useTicketViewStore, {
    storeFetchTicket: 'fetchTicket',
    storeFetchDraft: 'fetchDraft',
    storeDiscardDraft: 'discardDraft',
    storeUpdateProperty: 'updateTicketProperty',
    storeCloseTicket: 'closeTicket',
    storeReopenTicket: 'reopenTicket',
    storeDeleteTicket: 'deleteTicket',
    storeAddConversation: 'addConversation',
    storeReset: '$reset',
}),
```

### Task 2.2 — Remove from `data()` the properties now in store

Remove: `ticket`, `conversations`, `loading`, `updating`, `watchers`, `filteredWatchersIds`, `fluentcrm_profile`, `draftData`, `draftResponse`, `showDraft`, `ticketNotFound`

### Task 2.3 — Rewrite `fetchTicket()` to delegate to store

```js
async fetchTicket() {
    const store = useTicketViewStore();
    try {
        const response = await store.fetchTicket(this.ticket_id);
        this.$setTitle(response.ticket.title);
        this.draftReplyPermission = this.appVars.me.permissions.includes('fst_draft_reply');
        this.draftReplyApprovePermission = this.appVars.me.permissions.includes('fst_approve_draft_reply');
        this.deleteTicketPermission = this.appVars.me.permissions.includes('fst_delete_tickets');
        if (this.appVars.enable_draft_mode === 'yes') {
            store.fetchDraft(this.ticket_id);
        }
    } catch (error) {
        // ticketNotFound is set by the store
    }
},
```

### Task 2.4 — Rewrite mutation methods to delegate to store

Each method keeps its UI side effects (notifications, modal closes, navigation) but delegates the API call + state mutation to the store:

| Component Method | Store Action | UI Side Effects (stay in component) |
|---|---|---|
| `updateTicketAttr()` | `storeUpdateProperty()` | `$notify`, `fetchTicket()` |
| `closeTicket()` | `storeCloseTicket()` | `$notify`, `$router.go(-1)` |
| `reOpen()` | `storeReopenTicket()` | (none extra) |
| `deleteTicket()` | `storeDeleteTicket()` | `$confirm`, `$notify`, `$router.push` |
| `handleResponseActionCommand('delete')` | service call | `$confirm`, `$notify`, `fetchTicket()` |
| `handleResponseActionCommand('discard')` | `storeDiscardDraft()` | `$confirm`, `$notify`, `fetchTicket()` |
| `approveDraftResponse()` | store action or service | `$notify`, `fetchTicket()` |
| `addWatchers()` | service call | `$notify`, close modal, `fetchTicket()` |
| `recordNewResponse()` | `storeAddConversation()` + patch `store.ticket` | clear response box |

### Task 2.5 — Route watcher + beforeUnmount reset

```js
// watch
'$route.params.ticket_id'(ticketId) {
    if (ticketId) {
        this.doAction('ticket_view_exit', this.ticket.id);
        const store = useTicketViewStore();
        store.$reset();
        this.$nextTick(() => {
            this.doAction('ticket_view_entered', ticketId);
            this.fetchTicket();
        });
    }
},

// beforeUnmount
beforeUnmount() {
    const store = useTicketViewStore();
    this.doAction('ticket_view_exit', this.ticket.id);
    store.$reset();
    // ... existing cleanup
}
```

**Verification:** Load a ticket, verify all data renders. Close/reopen/assign agent/change priority/add response/delete response all work. Navigate between tickets — store resets. Go back to ticket list — store is clean.

---

## Phase 3: Migrate Remaining API Calls to Service

Replace all remaining `this.$get/post/put/del` calls with `ticketViewService.*`. These stay in the component (not store) because they only touch local state.

| Method | Before | After |
|---|---|---|
| `customerTickets()` | `this.$get('tickets/customer_tickets/...')` | `ticketViewService.getCustomerTickets(...)` |
| `mergeTickets()` | `this.$post('tickets/.../merge_tickets')` | `ticketViewService.mergeTickets(...)` |
| `splitToNewTicket()` | `this.$post('tickets/.../split_ticket')` | `ticketViewService.splitTicket(...)` |
| `changeMailbox()` | `this.$put('mailboxes/.../move_tickets')` | `ticketViewService.changeMailbox(...)` |
| `getTicketSummary()` | `this.$post('openai/.../get-ticket-summary')` | `ticketViewService.getTicketSummary(...)` |
| `getCustomerSentiment()` | `this.$post('openai/.../get-ticket-tone')` | `ticketViewService.getCustomerSentiment(...)` |

**Verification:** After this phase, ViewTicket.vue has ZERO direct `this.$get/post/put/del` calls. All REST communication goes through `ticketViewService` (for local-only operations) or the store actions (for shared state operations).

---

## Phase 4: Child Component — TicketSidebar

### Task 4.1 — TicketSidebar reads from store instead of props

Currently receives: `ticket`, `fluentcrm_profile`, `watchers`, `watcher_ids`, `fetch_other_tickets`, `ticket_id`

After migration:
```js
import { mapState } from 'pinia';
import { useTicketViewStore } from '@/admin/stores/ticketView.store';

computed: {
    ...mapState(useTicketViewStore, [
        'ticket', 'fluentcrm_profile', 'watchers', 'watcherIds',
    ]),
},
```

### Task 4.2 — Remove `@refresh` emit pattern

Currently: sidebar emits `@refresh` → ViewTicket calls `fetchTicket()`

After: sidebar calls `store.fetchTicket(ticketId)` directly or `store.updateTicketProperty()` for priority/tag changes. No emit needed.

### Task 4.3 — Update ViewTicket template

Remove props from `<TicketSidebar>`:
```html
<!-- Before -->
<TicketSidebar :ticket="ticket" :ticket_id="ticket.id" :watchers="watchers"
    :watcher_ids="filteredWatchersIds" :fluentcrm_profile="fluentcrm_profile"
    :fetch_other_tickets="fetch_other_tickets" @refresh="fetchTicket" />

<!-- After -->
<TicketSidebar :ticket_id="ticket.id" :fetch_other_tickets="fetch_other_tickets" />
```

Keep `fetch_other_tickets` as a prop signal for triggering sidebar widget refresh after merge/split.

**Verification:** Sidebar loads widgets, displays customer info, priority changes work, tags work, watchers display correctly.

---

## Component Hierarchy After Migration

```
ViewTicket.vue
  |-- reads store: ticket, conversations, loading, updating, watchers, ...
  |-- calls store actions: fetchTicket, closeTicket, reopenTicket, ...
  |-- calls service directly: merge, split, AI, mailbox (local-only state)
  |
  |-- CreateResponse
  |     |-- receives ticket as prop (from store via mapState in parent)
  |     |-- emits @created -> parent calls store.addConversation()
  |     |-- makes own API calls: response creation, draft saving
  |
  |-- TicketSidebar (Phase 4)
  |     |-- reads store directly: ticket, watchers, fluentcrm_profile
  |     |-- calls store.updateTicketProperty() for priority changes
  |     |-- makes own API calls: widgets, customer change
  |
  |-- EditResponse (unchanged -- props + emit)
  |-- ActiveAgents (unchanged -- props + own polling)
  |-- SplitTicket (unchanged -- props + emit)
  |-- FluentBoardsIntegration (unchanged -- props + emit)
```

---

## Known Gotchas

1. **jQuery Deferred, not Promise** — `Rest.js` returns jQuery jqXHR. Store actions use `.then()/.always()` (same as ticketList store). Works with `await` because jqXHR is thenable.

2. **`window.fluentSupportAdmin` in getters = BUG** — Pinia getters are computed properties. Non-reactive window globals cause stale cached values. Only access `window.*` inside actions or component-level code.

3. **Store reset on route change** — ViewTicket uses `$route.params.ticket_id` watcher. Must call `store.$reset()` before fetching new ticket, or stale data from previous ticket will flash.

4. **`filteredWatchersIds` naming** — ViewTicket currently uses `filteredWatchersIds` (line 884) but TicketSidebar receives as `watcher_ids` prop. Normalize to `watcherIds` in the store.

5. **`recordNewResponse` patches ticket** — When a response is created, the server returns `update_data` with multiple ticket fields to patch. The component should iterate `update_data` and set `store.ticket[key]` directly (Pinia state is reactive, direct mutation is fine inside actions or component code).
