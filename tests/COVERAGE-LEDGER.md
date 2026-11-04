# Coverage ledger

Every audited surface, classified honestly. Four states — nothing else:

- **DONE** — behaviourally tested: real boot, real request, values asserted.
- **THIN** — a detector or smoke test only; would miss a subtle regression.
- **PINNED** — a shipped fix is pinned by a source-contract detector, but no
  behavioural test exists.
- **LEFT** — known gap; the reason it's left is recorded, not implied.

Rules: every row carries file:line evidence. THIN and PINNED rows are the
next round's work-list. Moving a row to DONE requires a proof-of-catch note
(what was broken, what went red). Never delete a row — reclassify it.

| Surface | State | Evidence (test / detector) | Production ref | Notes |
|---|---|---|---|---|
| raw()/selectRaw()/whereRaw() table-prefix bug class | PINNED | tests/lint/raw-sql-prefix.php (proof: fixtures exit 1 with 3 planted hits) | app/, ../fluent-support-pro/app/ (304 files scanned) | founding bug class; runs on host, no WP |
| PHP parse/fatal lint, core + pro | THIN | tests/bin/run-all.sh `run_static` php -l loop | app/, boot/, database/ both plugins | syntax only |
| Route census vs manifests (124 GET + 127 mutating) | PINNED | tests/lint/route-coverage.php | app/Http/Routes/api.php + pro routes | new route without a manifest entry fails the gate bidirectionally |
| Every GET route, core + pro (124 routes, 226 cases) | THIN | tests/smoke/routes.manifest.php via tests/bin/run-smoke.php | app/Http/Routes/api.php | asserts status + no wpdb error + no exception under real DB w/ fstest_ prefix; caught FS-SMOKE-001..004 on first run |
| Every mutating route ×2 identities (254 cases) | DONE | tests/smoke/mutating.manifest.php via tests/bin/run-permissions.php | app/Http/Policies/ | 418 fuse proves gate outcome without mutation; proof: subscriber_allowed on an admin route went red |
| Portal any-logged-in-user contract (8 routes) | DONE | mutating.manifest.php `subscriber_allowed` entries | app/Http/Policies/PortalPolicy.php | subscriber leg asserts the gate PASSES (418) — locks the portal contract |
| Ticket lifecycle create→reply→close→reopen→property | DONE | tests/integration/10-ticket-lifecycle.php | app/Services/Tickets/TicketService.php, ResponseService.php | proof: removed resolved_at stamp (TicketService.php:28) → 2 cases red, restored → green |
| Ticket index filters/search/pagination | DONE | tests/integration/20-ticket-filters.php | app/Models/Ticket.php scopes | agent-written; tier verified twice, protected counts stable |
| fs_persons / fs_taggables / fs_meta discriminators | DONE | tests/integration/30-shared-table-discriminators.php | app/Models/{Customer,Agent,Tag,TicketTag,Meta}.php | global-scope cross-leak checks |
| Conversations + mail interception | DONE | tests/integration/40-conversation-and-mail.php | app/Services/Tickets/ResponseService.php | wp_mail intercepted process-wide |
| Literal-backslash preservation on the ticket/reply write path | DONE | tests/integration/45-content-slash-preservation.php | app/Services/Tickets/ResponseService.php:48, TicketService.php:184-185 | byte-for-byte assertion, not a substring probe — the boundary unslashes once, the services must sanitize only; proof: restored the `wp_unslash()` in both services → 6 assertions red (`C:\Users\test` → `C:Userstest`), removed again → green |
| Customer portal behavioural flow | DONE | tests/integration/50-customer-portal.php | app/Modules/CustomerPortal/ | includes portal close stamping (part of the proof-of-catch above) |
| Customer ticket list + customers-list counts access scope | DONE | tests/integration/61-customer-ticket-history-scope.php | app/Modules/Customers/CustomerController.php (show, withCounts) | assigned-only and assigned+unassigned viewers with a restricted mailbox; no-mailbox ticket stays visible; admin sees all. proof: reverted the counts scope → 2 assertions red; whole file back to develop → 4 red; restored → green |
| MCP customer ticket data access scope | DONE | tests/integration/62-mcp-customer-ticket-scope.php | app/Modules/MCP/Tools/TicketTools.php (getTicket, listTickets), CustomerTools.php (searchCustomers, getCustomerTickets), ManagementTools.php (support context, insights) | assigned-only viewer with a restricted mailbox gets only its openable siblings and scoped counts in get-ticket and search-customers; no-mailbox tickets survive the mailbox restriction in list-tickets, get-customer-tickets, the search-customers customer filter, the support-context queue and insights volume; admin sees all. proof: get-ticket reverted → 3 assertions red (counts 6/5 instead of 3/2); every other case watched red before its fix (queue 1 vs 3, volume 2 vs 4); restored → green |
| FluentCRM "Support Tickets" provider access scope | DONE | tests/integration/63-fluentcrm-support-tickets-scope.php | app/Services/Integrations/FluentCrm/FluentCRMWidgets.php (getSupportTickets) | calls the provider directly (lab has no FluentCRM): CRM-only user without an agent record gets none; agent without a ticket permission gets none (ticket routes return 403 for it); assigned-only agent with a restricted mailbox gets own + no-mailbox only; admin sees all; an HTML ticket title (as stored by the SupportCandy/HelpScout/JSHelpdesk importers) comes back escaped. proof: reverted the scope → 2 cases red; agent-without-ticket-permission case red before the canAccessTicketRoutes() gate; title case red before esc_html(); restored → green |
| Pro workflows via REST | DONE | ../fluent-support-pro/tests/integration/90-pro-workflows.php (symlinked) | pro app/Http/Controllers/WorkflowsController.php | skips by name when pro absent |
| OAuth redirect endpoints (dropbox/google) | LEFT | — | pro AuthorizeController.php:19 | wp_redirect terminates WP-CLI; browser flow only |
| External-plugin integrations (FluentCRM, FluentBoards) | THIN | smoke entries allow 400 "not installed" | app/Http/Controllers/ (integration routes) | lab has no external siblings; absence path is what's covered |
| Webhook listeners (telegram/slack/twilio/mail-piping) | LEFT | permission entries skip "public by design" | app/Http/Routes/api.php public group | need signed-payload fixtures to test bodies |
| Reporting response-growth without `type` | THIN | smoke cases in routes.manifest.php | app/Modules/Reporting/Reporting.php | FS-SMOKE-001 fixed |
| fs_ai_activity_logs surface | THIN | smoke cases in routes.manifest.php (lab has no table) | app/Services/Helper.php getAIActivities | FS-SMOKE-002 fixed: empty page while the table is missing |
| preset-prompts without `type` (core + pro) | THIN | smoke cases in routes.manifest.php | pro AIController | FS-SMOKE-003 fixed |
| reset_avatar permission gates (agents + customers) | DONE | canonical denial entries in mutating.manifest.php | AgentController@resetAvatar, Modules/Customers/CustomerController::resetAvatar | FS-PERM-001 fixed: type-hinted binding dropped, findOrFail runs post-auth |
| WPFluent Person query possibly inheriting Customer global scope | LEFT | observed during integration mutation M25: `Person::where(...)->delete()` removed only the customer twin | vendor/wpfluent framework, app/Models/Person.php | unconfirmed framework oddity, not a plugin bug; investigate before relying on Person-level bulk queries in tests |
