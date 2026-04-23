# Fluent Support - Comprehensive Audit Report

> Generated: 2026-03-23 | Covers: Security, Optimization, Full-Stack Traceability

---

## TABLE OF CONTENTS

1. [Security Issues](#1-security-issues)
2. [Optimization Issues](#2-optimization-issues)
3. [Traceability Issues](#3-traceability-issues)
   - [Frontend UI → REST API](#31-frontend-ui--rest-api)
   - [Routes → Controllers](#32-routes--controllers)
   - [Controllers → Services](#33-controllers--services)
   - [Services → Models](#34-services--models)
   - [Models → Database](#35-models--database)

---

## 1. SECURITY ISSUES

### CRITICAL

- [x] **SEC-C1: SQL Injection in Reporting Module** *(Resolved — uses `$wpdb->prepare()` with placeholders)*
  - **Files**: `app/Modules/Reporting/Reporting.php:716-796`
  - **Problem**: Raw SQL built with `esc_sql()` concatenation instead of `$wpdb->prepare()`. Dynamic values (`$escapedStartDate`, `$escapedEndDate`, `$escapedReportType`) concatenated into query strings passed to `$wpdb->get_results()`.
  - **Fix**: Replace all `esc_sql()` concatenation with `$wpdb->prepare()` using `%s` / `%d` placeholders.

- [x] **SEC-C2: SQL Injection in Importer TRUNCATE Statements** *(Resolved — whitelist validation before TRUNCATE)*
  - **Files**: `app/Services/Tickets/Importer/JSHelpdeskTickets.php:238`, `app/Services/Tickets/Importer/SupportCandyTickets.php:303`
  - **Problem**: `$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table}")` — table name concatenated directly into SQL.
  - **Fix**: Validate `$table` against a strict whitelist before concatenation; use `$wpdb->prepare()` where possible.

- [x] **SEC-C3: Weak MD5 Hashing for Attachment Signatures** *(Resolved — uses `hash_hmac('sha256', ...)` with `wp_salt()`)*
  - **File**: `app/Hooks/Handlers/ExternalPages.php:150`
  - **Problem**: `$sign = md5($attachment->id . gmdate('YmdH'))` — MD5 is cryptographically broken, allows signature forgery for unauthorized attachment access.
  - **Fix**: Use `hash_hmac('sha256', $attachment->id . gmdate('YmdH'), SECURE_AUTH_KEY)`.

- [x] **SEC-C4: Weak Ticket Hash Generation** *(Resolved — uses `bin2hex(random_bytes(16))`)*
  - **File**: `app/Models/Ticket.php:316`
  - **Problem**: `substr(md5(time() . wp_generate_uuid4()), 0, 8) . wp_rand(1, 99)` — MD5 + short substring + predictable random. Enables ticket enumeration and IDOR via brute-force.
  - **Fix**: Use `bin2hex(random_bytes(16))` for cryptographically secure 32-char hashes.

### HIGH

- [x] **SEC-H1: File Upload Extension-Only Validation** *(Resolved — validates both extension and MIME type via `finfo`, returns error properly)*
  - **File**: `app/Http/Controllers/UploaderController.php:184-193`
  - **Problem**: `isValidImageType()` only checks `getClientOriginalExtension()` — attacker can rename `.php` to `.png` and upload malicious code.
  - **Fix**: Add MIME type checking via `finfo_open(FILEINFO_MIME_TYPE)` + WordPress `wp_check_filetype_and_ext()`.

- [x] **SEC-H2: IDOR via Weak Public Signed Ticket Access** *(Resolved — fixed via SEC-C4 stronger hashes)*
  - **File**: `app/Http/Policies/PortalPolicy.php:90-100`
  - **Problem**: Ticket access validated by weak MD5 hash + ID combo. Hash entropy too low for brute-force resistance.
  - **Fix**: Generate cryptographically strong tokens with `bin2hex(random_bytes(32))`.

- [x] **SEC-H3: No Rate Limiting on Login Endpoint** *(Resolved — transient-based per-IP and per-account rate limiting)*
  - **File**: `app/Http/Controllers/AuthController.php:178-271`
  - **Problem**: `handleLogin()` has no built-in rate limiting. Brute-force credential attacks possible.
  - **Fix**: Add transient-based rate limiting (e.g., 5 attempts per IP per 15 minutes).

- [x] **SEC-H4: Exception Messages Exposed to API Clients** *(Resolved — uses `Helper::getSafeErrorMessage()`, generic in production)*
  - **Files**: All controllers (TicketController, CustomerController, etc.)
  - **Problem**: `catch (\Exception $e) { return $this->sendError(['message' => $e->getMessage()]); }` — leaks internal errors, paths, DB details.
  - **Fix**: Log exceptions server-side, return generic user-facing messages.

### MEDIUM

- [x] **SEC-M1: Insecure Unserialize Usage** *(False positive — no fix needed)*
  - **File**: `app/Services/Helper.php:1552-1559`
  - **Problem**: `@unserialize(trim($data), ['allowed_classes' => false])` — while `allowed_classes => false` mitigates object injection, `unserialize` is inherently risky.
  - **Analysis**: `allowed_classes => false` fully blocks PHP object injection (the only real exploit vector). Data is guarded by `is_serialized()` first. All writes use WordPress's `maybe_serialize()` — data is not user-controlled at the point of deserialization. Migration to JSON would require DB migration scripts across 5+ columns and 15+ files with no meaningful security gain.
  - ~~**Fix**: Migrate stored data to JSON; use `json_decode()` for new data.~~

- [ ] **SEC-M2: Public Webhook Endpoints Without Signature Verification** *Resolved*
  - **File**: `app/Http/Routes/api.php` (public prefix routes)
  - **Problem**: Telegram/Slack webhook endpoints rely only on URL token for auth, no cryptographic signature verification.
  - **Fix**: Implement HMAC signature verification for incoming webhooks.

- [x] **SEC-M3: MD5 for Rate Limit Transient Key** *(Resolved — uses `wp_hash($ip)`)*
  - **File**: `app/Hooks/Handlers/ExternalPages.php:294`
  - **Problem**: `$transient_key = 'fs_rate_limit_' . md5($ip)` — MD5 collision possible.
  - **Fix**: Use `hash('sha256', $ip)`.

### LOW

- [x] **SEC-L1: Weak Auto-Generated Password** *(Resolved — now `wp_generate_password(16, true, true)`)*
  - **File**: `app/Http/Controllers/AuthController.php:565`
  - **Problem**: `wp_generate_password(8)` — 8 chars is too short for modern security standards.
  - **Fix**: Increase to 16+ characters.

- [ ] **SEC-L2: Missing Security Headers**  *(False positive)*
  - **Problem**: No explicit `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy` headers.
  - **Fix**: Add security headers via WordPress hooks.

- [ ] **SEC-L3: Loose Type Comparisons**  *(Resolved)*
  - **Files**: Various controllers use `==` instead of `===`
  - **Fix**: Use strict comparisons throughout.

---

## 2. OPTIMIZATION ISSUES

### HIGH IMPACT

- [x] **OPT-H1: N+1 Query — Meta Queries in Response Loop** *(Resolved — batch loads meta with `whereIn($responseIds)`, then in-memory lookup)*
  - **File**: `app/Http/Controllers/TicketController.php:296-305`
  - **Problem**: Inside `foreach ($ticket->responses)`, individual `Meta::where()` queries fire for each response to fetch `agent_feedback_ratings`.
  - **Fix**: Batch-load all meta for response IDs in one query, or add a relationship to Conversation model.

- [x] **OPT-H2: N+1 Query — Live Activity in Ticket List** *(Resolved — uses `loadBatchLiveActivities()` with 2 batch queries)*
  - **File**: `app/Http/Controllers/TicketController.php:139-147`
  - **Problem**: `TicketHelper::getActivity($ticket->id)` called in loop for each ticket when `$perPage < 15`.
  - **Fix**: Batch-load activities for all ticket IDs in one query.

- [x] **OPT-H3: N+1 Query — CC Info Not Eager Loaded***(Resolved)*
  - **File**: `app/Http/Controllers/TicketController.php:311-320`
  - **Problem**: `$response->ccinfo` triggers lazy-load relationship per response in loop.
  - **Fix**: Include `ccinfo` in the `with()` clause when loading responses.

- [x] **OPT-H4: N+1 Query — User Meta in Loop***(Resolved)*
  - **File**: `app/Http/Controllers/TicketController.php:276-283`
  - **Problem**: `get_user_meta()` called per custom field key in loop.
  - **Note**: WP object cache means only 1 real DB hit regardless, but original relies on implicit caching behavior.
  - **Fix**: Call `get_user_meta($user_id)` once without key to get all meta, then filter in PHP — makes intent explicit.

- [x] **OPT-H5: Bulk Attachment Insert Uses Individual Saves** *(Resolved — uses `Attachment::insert($attachmentRecords)` batch insert)*
  - **File**: `app/Http/Controllers/TicketController.php:955-962`
  - **Problem**: `$attachment->replicate()->save()` called per attachment per ticket in loop.
  - **Fix**: Build array of records and use `Model::insert()` for batch insert.

### MEDIUM IMPACT

- [x] **OPT-M1: Conditional `load()` Instead of Eager Loading** *(False positive — pattern is correct)*
  - **File**: `app/Http/Controllers/TicketController.php:291, 341, 645, 648`
  - **Problem**: Extra queries via `$ticket->load('closed_by_person')`, `$ticket->load('product')` after initial fetch.
  - **Analysis**: The conditional loads in `getTicket()` (`closed_by_person` only when `status == 'closed'`, `created_by_person` only when `created_by` is set) are intentionally guarded — moving them to `with()` would add unnecessary queries for every ticket. The post-save loads in `updateTicketProperty()` (`product`, `agent`) must fire after `$ticket->save()` to return the updated relationship; eager-loading them at fetch time would return stale pre-update data.
  - ~~**Fix**: Include conditional relationships in the initial `with()` call.~~

- [ ] **OPT-M2: No Frontend Route Code Splitting**
  - **File**: `resources/admin/routes.js`
  - **Problem**: All routes compiled into single bundle — no lazy loading.
  - **Fix**: Use dynamic imports: `const Dashboard = () => import('./Modules/Dashboard/Dashboard.vue')`.

- [ ] **OPT-M3: Missing Composite Database Indexes**
  - **File**: `database/Migrations/TicketsMigrator.php`
  - **Problem**: No composite indexes for common filter combos (`status + agent_id`, `mailbox_id + status`, `customer_id + created_at`).
  - **Fix**: Add composite indexes in migration `alterTable()` method.

- [x] **OPT-M4: Repeated Settings Fetches Without Caching** *(Resolved — `getBusinessSettings()` uses `static $settings;` to cache within the request)*
  - **File**: `app/Http/Controllers/TicketController.php:273, 329`
  - **Problem**: `Helper::getBusinessSettings()` called multiple times per request without in-memory cache.
  - **Fix**: Add static property cache in Helper class.

- [ ] **OPT-M5: Large Vue Components Need Splitting**
  - **Files**: `resources/admin/Modules/Tickets/AllTickets.vue` (1,774 lines), `ViewTicket.vue` (1,721 lines)
  - **Problem**: Monolithic components — hard to maintain, can't tree-shake.
  - **Fix**: Extract into `TicketFilters.vue`, `TicketTable.vue`, `TicketBulkActions.vue`, etc.

- [ ] **OPT-M6: TicketController Too Large (37+ methods)**
  - **File**: `app/Http/Controllers/TicketController.php` (1,446 lines)
  - **Problem**: Single responsibility violation — handles listing, CRUD, bulk, drafts, responses, boards integration.
  - **Fix**: Split into `TicketListController`, `TicketDetailController`, `TicketBulkController`.

- [x] **OPT-M7: Deprecated Methods Still Present** *(Resolved — all deprecated methods properly marked with `@deprecated` and `_deprecated_function()` calls)*
  - **Files**: `app/Models/Ticket.php:973` (`createTicket()`), `app/Modules/PermissionManager.php:525-549` (3 deprecated methods)
  - **Fix**: Audit for callers, then remove deprecated methods.

### LOW IMPACT

- [ ] **OPT-L1: Array Merge in Loops**
  - **Files**: `app/Services/Helper.php:143`, `app/Models/Ticket.php:275-279`
  - **Fix**: Use `array_push(...$items)` or `array_merge(...$arrays)` outside loop.

- [ ] **OPT-L2: Redundant `get_current_user_id()` Calls**
  - **File**: `app/Services/Helper.php:518-549`
  - **Fix**: Cache in local variable within each method.

- [ ] **OPT-L3: FluentCRM Data Not Cached**
  - **File**: `app/Services/Helper.php:583-584`
  - **Fix**: Use WordPress transients for FluentCRM tags/lists.
---

## 3. TRACEABILITY ISSUES

### 3.1 Frontend UI → REST API

#### CRITICAL

- [ ] **TRACE-FE1: Missing Backend Endpoint — `GET /reports/overview`** *(Resolved)*
  - **Frontend**: `resources/admin/Modules/Reports/Overview.vue:669`
  - **Call**: `this.$get('reports/overview', { date_range: this.dateRange })`
  - **Problem**: No matching route in `app/Http/Routes/api.php`. Will return 404.
  - **Fix**: Add route `$router->get('/overview', 'ReportingController@getOverview')` in the reports prefix group and implement the controller method.

- [x] **TRACE-FE2: Wrong Endpoint in Service Layer — `tickets/bulk`** *(Resolved — both methods now use `tickets/bulk-actions` with POST)*
  - **Frontend**: `resources/admin/services/ticketListService.js:13,17`
  - **Calls**: `Rest.post('tickets/bulk', data)` and `Rest.delete('tickets/bulk', data)`
  - **Problem**: Backend has `POST /tickets/bulk-actions` and `POST /tickets/bulk-reply`, not `tickets/bulk`. These service methods will 404.
  - **Fix**: Update service layer to call correct endpoints (`bulk-actions`).

#### LOW

- [x] **TRACE-FE3: Route Path Typo — `intsall-fluentcrm`** *(Resolved — renamed to `install-fluentcrm` on both sides)*
  - **Frontend**: `resources/admin/Modules/Settings/FluentCRMIntegration.vue`
  - **Backend**: `app/Http/Routes/api.php:122`
  - **Problem**: Both sides use misspelled `intsall-fluentcrm` (missing 'a' in install). Works but confusing.
  - **Fix**: Rename to `install-fluentcrm` on both sides.

---

### 3.2 Routes → Controllers

#### CRITICAL

- [x] **TRACE-RC1: Empty Controller Method — `ConversationController@createCustomerReply`** *(Resolved — route and empty method removed)*
  - **Route**: `POST /tickets/{ticket_id}/customer-responses`
  - **File**: `app/Http/Controllers/ConversationController.php:13-16`
  - **Problem**: Method body is completely empty — returns NULL, causing 500 errors.
  - **Fix**: Implement the method body or remove the route if unused.

- [x] **TRACE-RC2: Static Methods in ReportingController Can't Be Route Targets** *(Resolved — removed `static` from all 4 methods)*
  - **File**: `app/Http/Controllers/ReportingController.php:111, 212, 236, 251`
  - **Methods**: `getResolveChart()`, `getResponseGrowthChart()`, `getProductsSummary()`, `getMailBoxesSummary()` — all declared `public static`
  - **Problem**: Router instantiates controller as object and calls instance methods. Static methods break this.
  - **Fix**: Remove `static` keyword from all 4 methods.

- [x] **TRACE-RC3: Invalid Route Parameter Constraint** *(Resolved — removed `->int('id')` constraint)*
  - **File**: `app/Http/Routes/api.php:238`
  - **Route**: `GET /fluent-bot/preset-prompts` with `->int('id')`
  - **Problem**: Route path has no `{id}` parameter but an `int('id')` constraint is applied.
  - **Fix**: Remove `->int('id')` constraint.

#### MEDIUM

- [x] **TRACE-RC4: Misspelled Route Path** *(Resolved — corrected to `install-fluentcrm`)*
  - **File**: `app/Http/Routes/api.php:122`
  - **Route**: `POST /settings/intsall-fluentcrm` (same as TRACE-FE3)
  - **Fix**: Correct spelling to `install-fluentcrm`.

---

### 3.3 Controllers → Services

#### CRITICAL

- [x] **TRACE-CS1: `TicketService::reopen()` Returns Wrong Value** *(Resolved — now returns `$ticket` instead of `$person`)*
  - **File**: `app/Services/Tickets/TicketService.php:78`
  - **Problem**: Returns `$person` instead of `$ticket`. The `close()` method correctly returns `$ticket`.
  - **Callers**:
    - `TicketController.php:684` — assigns to `'ticket'` key in response
    - `CustomerPortalService.php:150` — assigns to `'ticket'` key in response
  - **Impact**: Frontend receives agent/person data where it expects ticket data.
  - **Fix**: Change `return $person;` to `return $ticket;`.

- [x] **TRACE-CS2: Wrong Arguments in `CustomerController::resetAvatar()`** *(Resolved — `restoreAvatar()` refactored to use `$this`, no args needed)*
  - **File**: `app/Http/Controllers/CustomerController.php:260-263`
  - **Problem**: `$customer->restoreAvatar($customer, $customer_id)` — passes the model instance as first arg when method likely expects only the ID.
  - **Fix**: Verify `restoreAvatar()` signature and pass correct arguments.

- [x] **TRACE-CS3: Wrong Arguments in `AgentController::resetAvatar()`** *(Resolved — same fix as TRACE-CS2)*
  - **File**: `app/Http/Controllers/AgentController.php:177-180`
  - **Problem**: Same pattern — `$agent->restoreAvatar($agent, $agent_id)`.
  - **Fix**: Same as TRACE-CS2.

#### HIGH

- [x] **TRACE-CS4: Undefined Function `FluentBoardsApi()` Without Guard** *(Resolved — routes guarded with `function_exists()` in api.php)*
  - **File**: `app/Http/Controllers/TicketController.php:1304, 1329, 1365`
  - **Problem**: Calls `FluentBoardsApi('boards')` — function from external plugin. No `function_exists()` check. Fatal error if Fluent Boards not installed.
  - **Fix**: Wrap calls in `if (function_exists('FluentBoardsApi')) { ... }`.

#### MEDIUM

- [x] **TRACE-CS5: Orphaned Helper/Service Methods** *(Resolved — audited all public methods; 6 in Helper.php and 1 in TicketHelper.php marked `@internal`; `getAgentsInfoFromActivities` changed to `private`)*
  - **Files**: `app/Services/Helper.php`, `app/Services/TicketHelper.php`
  - **Problem**: Public methods never called from any controller. May be called from hooks/Pro/filters, but unclear.
  - **Audit findings** (searched both dev + pro plugin):
    - `Helper::deleteOption` — `@internal`
    - `Helper::sanitizeOrderValue` — `@internal`
    - `Helper::getDriversKey` — `@internal`
    - `Helper::getFSIntegrationStatus` — `@internal`
    - `Helper::isCfIp` — `@internal`
    - `Helper::getBusinessBox` — `@internal`
    - `TicketHelper::getCarbonCopyCustomerInfo` — `@internal`
    - `TicketHelper::getAgentsInfoFromActivities` — changed to `private` (only called via `self::` internally)

---

### 3.4 Services → Models

#### MEDIUM

- [x] **TRACE-SM1: `Person::updateMeta()` Uses `$meta->update()` Instead of `$meta->save()`** *(Resolved — changed to `$meta->save()`)*
  - **File**: `app/Models/Person.php:212`
  - **Problem**: `$meta->update()` without arguments is non-standard for loaded model instances. Should be `$meta->save()`.
  - **Fix**: Change to `$meta->save()`.

- [ ] **TRACE-SM2: MailBox Meta Methods Use Wrong `object_type`**
  - **File**: `app/Models/MailBox.php:88-145`
  - **Problem**: `getMeta()`, `saveMeta()`, `deleteMeta()`, `deleteAllMeta()` use full namespace `FluentSupport\App\Models\MailBox` as `object_type`. All other models use short strings like `'person_meta'`, `'ticket_meta'`.
  - **Note**: Methods ARE actively used — Pro plugin uses `$box->getMeta('_webhook_token')` and `$mailBox->saveMeta('_webhook_token', $token)` in `ProHelper.php`, `EmailBoxController.php`, `EmailPipingRetryHandler.php`, and `actions.php`. Existing DB rows store `object_type = 'FluentSupport\App\Models\MailBox'`. Changing the string requires a DB migration to UPDATE existing rows first.
  - **Fix**: Add a migration that updates all `fs_meta` rows where `object_type = 'FluentSupport\App\Models\MailBox'` to `'mailbox_meta'`, then update the 4 methods in `MailBox.php`.

---

### 3.5 Models → Database

#### CRITICAL

- [x] **TRACE-MD1: `AIActivityLogsMigrator` Not Registered in DBMigrator** *(Resolved — no change needed)*
  - **File**: `database/DBMigrator.php`
  - **Note**: The table is created on-demand via `SettingsController.php:392` and `SettingsController.php:904` when the user enables AI features. The `CleanupHandler` and `AIActivityLogger` only execute after AI is enabled, so the table always exists by then. Registration in `DBMigrator` is not required.

#### HIGH

#### ~~TRACE-MD2: No Cascade Delete for Attachments on Conversation~~ (SOLVED)
- **File:** `app/Models/Conversation.php` (base plugin)
- **Issue:** `Conversation::deleting()` hook deleted Meta but left `fs_attachments` rows where `conversation_id = $model->id` orphaned.
- **Fix:** Added `Attachment::where('conversation_id', $model->id)->delete()` inside the `deleting` hook using the `__NAMESPACE__ . '\Attachment'` pattern. `Conversation::deleteAll()` iterates and calls `$conversation->delete()` on each record, so the hook fires per-conversation and covers bulk deletions automatically.

#### ~~TRACE-MD3: No Cascade Delete for Ticket-Level Attachments~~ (SOLVED)
- **File:** `app/Models/Ticket.php` (base plugin)
- **Issue:** `Ticket::deleting()` called `Conversation::deleteAll()` (covering conversation-scoped attachments via TRACE-MD2 fix) but did not delete ticket-level attachments where `conversation_id IS NULL`.
- **Fix:** Added `Attachment::where('ticket_id', $model->id)->whereNull('conversation_id')->delete()` at the end of the `deleting` hook, after `Conversation::deleteAll()`. Scoped to `conversation_id IS NULL` to avoid double-deleting rows already removed by conversation cleanup.

#### MEDIUM

- [ ] **TRACE-MD4: Fillable Properties Missing Migration Columns**
  - **Files & Missing Columns**:
    - `Ticket.php`: Missing `secret_content`, `content_hash` in `$fillable`
    - `Conversation.php`: Missing `content_hash`, `serial` in `$fillable`
    - `Person.php`: Missing `avatar`, `description` in `$fillable`
    - `Attachment.php`: Missing `file_hash` in `$fillable`
    - `Product.php`: Missing `mailbox_id` in `$fillable`
  - **Fix**: Add missing columns to `$fillable` arrays (or document why they're excluded).

- [ ] **TRACE-MD5: No Foreign Key Constraints at Database Level**
  - **Files**: All migration files
  - **Problem**: All foreign key relationships enforced only in code. Database-level orphan prevention is absent.
  - **Fix**: Consider adding FK constraints in migrations (with awareness of WordPress table engine compatibility).

- [ ] **TRACE-MD6: Potentially Unused Migration Columns**
  - `fs_tickets.secret_content` — not referenced anywhere in code
  - `fs_conversations.serial` — not referenced anywhere in code
  - **Fix**: Confirm these are deprecated and document accordingly, or remove in future migration.

---

## PRIORITY MATRIX

### Fix Immediately (will cause errors/security holes)

| ID | Type | Summary | Status |
|----|------|---------|--------|
| SEC-C1 | Security | SQL injection in Reporting | ✅ Resolved |
| SEC-C3 | Security | MD5 attachment signatures | ✅ Resolved |
| SEC-C4 | Security | Weak ticket hashes | ✅ Resolved |
| SEC-H1 | Security | File upload extension-only check | ✅ Resolved |
| TRACE-RC1 | Traceability | Empty controller method (500 error) | ✅ Resolved |
| TRACE-RC2 | Traceability | Static methods can't be route targets | ✅ Resolved |
| TRACE-CS1 | Traceability | `reopen()` returns wrong value | ✅ Resolved |
| TRACE-MD1 | Traceability | Missing migration registration | ✅ Resolved |

### Fix Soon (data integrity / significant bugs)

| ID | Type | Summary | Status |
|----|------|---------|--------|
| SEC-H3 | Security | No login rate limiting | ✅ Resolved |
| SEC-H4 | Security | Exception messages exposed | ✅ Resolved |
| SEC-H2 | Security | IDOR via weak signed tickets | ✅ Resolved |
| TRACE-FE1 | Traceability | Missing `reports/overview` endpoint | ✅ Resolved |
| TRACE-FE2 | Traceability | Wrong service layer endpoints | ✅ Resolved |
| TRACE-CS2 | Traceability | Wrong args in resetAvatar (Customer) | ✅ Resolved |
| TRACE-CS3 | Traceability | Wrong args in resetAvatar (Agent) | ✅ Resolved |
| TRACE-CS4 | Traceability | Missing function_exists guard | ✅ Resolved |
| TRACE-MD2 | Traceability | No cascade delete for attachments | ✅ Resolved |
| TRACE-MD3 | Traceability | No cascade delete for ticket attachments | ✅ Resolved |
| OPT-H1 | Optimization | N+1 meta queries in response loop | ✅ Resolved |
| OPT-H2 | Optimization | N+1 live activity in ticket list | ✅ Resolved |

### Fix When Possible (quality / performance)

| ID | Type | Summary | Status |
|----|------|---------|--------|
| SEC-M1 | Security | Insecure unserialize | ✅ False Positive |
| SEC-C2 | Security | TRUNCATE table injection | ✅ Resolved |
| TRACE-RC3 | Traceability | Invalid route constraint | ✅ Resolved |
| TRACE-SM1 | Traceability | update() vs save() | ✅ Resolved |
| TRACE-MD4 | Traceability | Fillable mismatches | ⏳ Pending |
| OPT-H3 | Optimization | CC info not eager loaded | ✅ Resolved |
| OPT-H4 | Optimization | User meta in loop | ✅ Resolved |
| OPT-H5 | Optimization | Bulk attachment inserts | ✅ Resolved |
| OPT-M1-M3, M5-M6 | Optimization | Various medium optimizations | ⏳ Pending |
| OPT-M4 | Optimization | Repeated settings fetches | ✅ Resolved |
| OPT-M7 | Optimization | Deprecated methods marked | ✅ Resolved |

### Backlog (nice to have)

| ID | Type | Summary | Status |
|----|------|---------|--------|
| SEC-L1-L3 | Security | Password length, headers, type comparison | 🔀 Partial (L1 ✅, L2 ⏳, L3 ✅) |
| SEC-M2-M3 | Security | Webhook signatures, MD5 rate limit key | ✅ Resolved |
| TRACE-FE3 | Traceability | Route typo (cosmetic) | ✅ Resolved |
| TRACE-CS5 | Traceability | Orphaned helper methods | ⏳ Pending |
| TRACE-MD5-MD6 | Traceability | FK constraints, unused columns | ⏳ Pending |
| OPT-L1-L3 | Optimization | Minor optimizations | ⏳ Pending |

---

## STATS SUMMARY

| Category | Critical | High | Medium | Low | Total |
|----------|----------|------|--------|-----|-------|
| Security | 4 | 4 | 3 | 3 | **14** |
| Optimization | 0 | 5 | 7 | 3 | **15** |
| Traceability | 5 | 5 | 8 | 1 | **19** |
| **Total** | **9** | **14** | **18** | **7** | **48** |
