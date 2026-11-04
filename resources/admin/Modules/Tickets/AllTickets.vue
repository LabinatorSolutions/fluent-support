<template>
    <div class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_component_header">
                <div class="fs_component_head">
                    <h3 class="fs_page_title">
                        {{ active_view_label || $t("Tickets") }}
                        <span>({{ pagination.total }})</span>
                    </h3>
                    <!-- Phones and tablets have no views sidebar; the title opens the views instead. -->
                    <el-button text class="fs_view_switcher_btn fs_list_view_btn" :aria-label="$t('Change ticket view')" @click="views_sheet_open = true">
                        <span class="fs_view_switcher_label">{{ active_view_label || $t("Tickets") }}</span>
                        <span class="fs_view_switcher_total">{{ pagination.total }}</span>
                        <el-icon class="fs_view_switcher_arrow"><ArrowDown /></el-icon>
                    </el-button>
                </div>

                <div class="fs_box_actions fs_tickets_actions_btn">
                    <el-button v-tip="$t('Refresh')"
                        @click="refreshTickets()"
                        class="fs_outline_btn fs_refresh_btn"
                    >
                        <IconPack iconKey="refresh" :width="20" :height="20" />
                    </el-button>
                    <el-button
                        @click="toggleWaitingForReply()"
                        :class="['fs_outline_btn', 'fs_waiting_reply_btn', { 'fs_waiting_reply_btn_active': filters.waiting_for_reply === 'yes' }]"
                    >
                        <el-checkbox
                            :model-value="filters.waiting_for_reply === 'yes'"
                            @click.prevent.stop
                            class="fs_waiting_reply_checkbox"
                        />
                        {{ $t('Waiting For Reply') }}
                    </el-button>
                    <el-dropdown :persistent="false" trigger="click" @command="handleMoreAction">
                        <el-button class="fs_outline_btn fs_more_action_btn" :aria-label="$t('More Action')">
                            <span class="fs_btn_label">{{ $t("More Action") }}</span>
                            <el-icon class="el-icon--right fs_btn_label"><ArrowDown /></el-icon>
                            <el-icon class="fs_btn_icon_mobile"><MoreFilled /></el-icon>
                        </el-button>
                        <template #dropdown>
                            <el-dropdown-menu class="fs_global_dropdown fs_global_dropdown">
                                <el-dropdown-item :command="show_bulk_actions ? 'hide_bulk' : 'show_bulk'">
                                    <el-icon v-if="!show_bulk_actions"><View /></el-icon>
                                    <el-icon v-else><Hide /></el-icon>
                                    <span>{{ show_bulk_actions ? $t("Hide Bulk Action") : $t("Show Bulk Action") }}</span>
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                    <el-button
                        @click="add_ticket_modal = true"
                        class="fs_filled_btn fs_add_ticket_btn"
                        icon="Plus"
                        :aria-label="$t('Add Ticket')"
                    ><span class="fs_btn_label">{{ $t("Add Ticket") }}</span>
                    </el-button>
                </div>
            </div>
            <ticket-list-filter-bar
                :advanced-open="advancedOpen"
                @fetch="fetchTickets()"
                @status="setStatus"
                @toggle-advanced="toggleAdvancedFilter"
                @saved-filters="$refs.advancedFilter.fetchLabelSearch()"
            />
            <div class="fs_box_body">
                <advanced-ticket-filter ref="advancedFilter" :advanced-open="advancedOpen" @fetch="fetchTickets()"/>
                <!-- Select All Section Bar -->
                <div class="fs_select_all_section" :class="{ 'fs_advance_section_display': advancedOpen }" v-if="tickets.length && show_bulk_actions && ticketLayout !== 'grid'">
                    <div class="fs_select_all_container">
                        <div class="fs_select_all">
                            <el-checkbox
                                :indeterminate="isIndeterminate"
                                v-model="selectAllChecked"
                                @change="handleSelectAll"
                            >
                                {{ $t('Select All') }}
                            </el-checkbox>
                            <span class="fs_total_count">
                                <!-- translators: %1$s is the number of selected tickets, %2$s the total number of tickets -->
                                {{ $t('%1$s out of %2$s tickets', ticket_selections.length, pagination.total) }}
                            </span>
                        </div>
                    </div>
                </div>
                <ticket-list-rows :show_bulk_actions="show_bulk_actions"/>
                <div class="fs_pagination_wrapper" v-if="tickets.length">
                    <span class="fs_pagination_left">
                        <p>Page {{ pagination.current_page }} of {{ Math.ceil(pagination.total / pagination.per_page) }}</p>
                        <pagination
                            @fetch="fetchTickets()"
                            :pagination="pagination"
                            layout="sizes"
                        />
                    </span>
                    <span class="fs_pagination_right">
                        <pagination
                            @fetch="fetchTickets()"
                            :pagination="pagination"
                            :background="true"
                            layout="prev, pager, next"
                        />
                    </span>
                </div>
            </div>
        </div>

        <!-- Create Ticket Modal -->
        <el-dialog
            :append-to-body="true"
            v-model="add_ticket_modal"
            width="50%"
            class="fs_dialog fs_tk_dialog">
            <add-ticket @close="add_ticket_modal = false"></add-ticket>
        </el-dialog>

        <ticket-bulk-actions
            v-if="appReady && show_bulk_actions"
            @fetchTickets="refreshTickets()"
        />
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import { each } from "lodash-es";
import AddTicket from "./_AddTicket";
import TicketBulkActions from "./_BulkActions";
import TicketListFilterBar from "./parts/_TicketListFilterBar";
import AdvancedTicketFilter from "./parts/_AdvancedTicketFilter";
import TicketListRows from "./parts/_TicketListRows";
import IconPack from "@/admin/Components/IconPack.vue";
import { mapState, mapActions, mapWritableState } from 'pinia';
import { useTicketListStore } from '@/admin/stores/ticketList.store';
import { shortcutModifiers, isTypingTarget } from '@/admin/Bits/shortcuts';

/**
 * The ticket list: header actions, the filter bar and advanced filter, the
 * rows, select all, paging, Add Ticket and bulk actions. Loads the list through
 * the list store, keeps the agent's filters and layout between visits, and
 * reads filters from the URL (sidebar views, reports).
 */
export default {
    name: "AllTickets",
    components: {
        IconPack,
        Pagination,
        AddTicket,
        TicketBulkActions,
        TicketListFilterBar,
        AdvancedTicketFilter,
        TicketListRows,
    },
    data() {
        return {
            add_ticket_modal: false,
            show_bulk_actions: true,
            // Free shows the builder without switching the store to advanced (it can't run there).
            advancedPreview: false,
        };
    },
    computed: {
        advancedOpen() {
            return this.has_pro ? this.filter_type === 'advanced' : this.advancedPreview;
        },
        ...mapState(useTicketListStore, [
            'tickets',
            'pagination',
            'ticket_statuses_group',
            'appReady',
            'active_view_label',
            'selectAllChecked',
            'isIndeterminate',
        ]),
        ...mapWritableState(useTicketListStore, [
            'views_sheet_open',
            'filters',
            'search',
            'filter_type',
            'ticket_selections',
            'searchActive',
            'ticketLayout',
        ]),
    },
    watch: {
        '$route.query.agent_id'(newAgentId, oldAgentId) {
            const store = useTicketListStore();
            if (store.app_ready && this.$route.name !== "view_ticket") {
                if (newAgentId !== oldAgentId) {
                    store.filters.agent_id = newAgentId || [];
                    this.fetchTickets();
                }
            }
        },
        '$route.query.watcher'(newWatcher, oldWatcher) {
            const store = useTicketListStore();
            if (store.app_ready && this.$route.name !== "view_ticket") {
                if (newWatcher !== oldWatcher) {
                    store.filters.watcher = newWatcher || '';
                    this.fetchTickets();
                }
            }
        },
        '$route.query.product_id'(newProductId, oldProductId) {
            const store = useTicketListStore();
            if (store.app_ready && this.$route.name !== "view_ticket") {
                if (newProductId !== oldProductId) {
                    store.filters.product_id = newProductId ? (Array.isArray(newProductId) ? newProductId : [newProductId]) : [];
                    this.fetchTickets();
                }
            }
        },
    },
    methods: {
        ...mapActions(useTicketListStore, {
            storeSetStatus: 'setStatus',
            storeResetWithOutFetch: 'resetWithOutFetch',
            storeHandleSelectAll: 'handleSelectAll',
        }),

        async fetchTickets() {
            const store = useTicketListStore();
            if (!store.app_ready) {
                return false;
            }

            try {
                await store.fetchTickets();
                this.saveFilters();
            } catch (e) {
                this.$handleError(e);
            }
        },

        refreshTickets() {
            const store = useTicketListStore();
            store.invalidateCache();
            this.fetchTickets();
        },

        setFromSaveFilters() {
            const store = useTicketListStore();
            store.filter_type = this.$getData("tickets_filter_type", "simple");
            store.ticket_statuses_group = this.appVars.ticket_statuses_group || {};

            const filters = this.$getData("tickets_filter", {});
            each(filters, (filter, filterKey) => {
                store.filters[filterKey] = filter;
            });

            const ticketPref = this.$getData("tickets_pref", false);
            if (ticketPref) {
                store.order_by = ticketPref.order_by;
                store.order_type = ticketPref.order_type;
                store.pagination.per_page = ticketPref.per_page;
                store.pagination.current_page = ticketPref.current_page;
                store.search = ticketPref.search;
            }

            const advancedFilters = this.$getData("tickets_advanced_filters", [[]]);
            if (advancedFilters) {
                store.advanced_filters = advancedFilters;
            }

            const savedBulkActionsState = this.$getData("tickets_show_bulk_actions", 'yes');
            this.show_bulk_actions = savedBulkActionsState !== 'no';

            const savedFieldVisibility = this.$getData('tickets_field_visibility', null);
            if (savedFieldVisibility) {
                store.fieldVisibility = { ...store.fieldVisibility, ...savedFieldVisibility };
            }

            const savedGridFieldVisibility = this.$getData('tickets_grid_field_visibility', null);
            if (savedGridFieldVisibility) {
                store.gridFieldVisibility = { ...store.gridFieldVisibility, ...savedGridFieldVisibility };
            }

            const savedLayout = this.$getData('tickets_layout', 'default');
            store.ticketLayout = savedLayout;

            store.appReady = true;
        },

        saveFilters() {
            const store = useTicketListStore();
            this.$saveData("tickets_pref", {
                order_by: store.order_by,
                order_type: store.order_type,
                per_page: store.pagination.per_page,
                search: store.search,
                current_page: store.pagination.current_page,
            });

            this.$saveData("tickets_filter_type", store.filter_type);

            if (store.filter_type === "advanced") {
                this.$saveData("tickets_advanced_filters", store.advanced_filters);
            } else {
                this.$saveData("tickets_filter", store.filters);
            }
        },

        setStatus(statusKey) {
            this.storeSetStatus(statusKey);
            this.fetchTickets();
        },

        toggleAdvancedFilter(value) {
            if (!this.has_pro) {
                this.advancedPreview = value;
                return;
            }

            if (value) {
                this.search = "";
                this.searchActive = false;
            }

            this.filter_type = value ? "advanced" : "simple";
            this.saveFilters();

            if (!value) {
                this.fetchTickets();
            }
        },

        toggleWaitingForReply() {
            // Mirrors the old el-switch: active-value="yes" :inactive-value="false"
            this.filters.waiting_for_reply = this.filters.waiting_for_reply === 'yes' ? false : 'yes';
            this.maybeChangeWaitingReply();
        },

        maybeChangeWaitingReply() {
            this.fetchTickets();
        },

        handleSelectAll(checked) {
            this.storeHandleSelectAll(checked);
        },

        handleBulkActionsToggle(value) {
            this.$saveData("tickets_show_bulk_actions", value ? 'yes' : 'no');
            if (!value) {
                this.ticket_selections = [];
            }
        },

        handleMoreAction(command) {
            if (command === 'show_bulk') {
                this.show_bulk_actions = true;
                this.handleBulkActionsToggle(true);
            } else if (command === 'hide_bulk') {
                this.show_bulk_actions = false;
                this.handleBulkActionsToggle(false);
            }
        },

        handleKeydown(event) {
            const { code } = event;
            const { primary, alt, shift } = shortcutModifiers(event);

            if (!primary || isTypingTarget(event.target) || (alt && code === "KeyI")) {
                return;
            }

            if (shift && (code === "ArrowRight" || code === "ArrowLeft")) {
                event.preventDefault();
                const statusKeys = Object.keys(this.ticket_statuses_group || {});
                if (statusKeys.length === 0) return;

                const currentIndex = statusKeys.indexOf(this.filters.status_type);
                let newIndex;

                if (code === "ArrowRight") {
                    newIndex = (currentIndex + 1) % statusKeys.length;
                } else {
                    newIndex = (currentIndex - 1 + statusKeys.length) % statusKeys.length;
                }

                this.setStatus(statusKeys[newIndex]);
                return;
            }

            if (!alt) {
                return;
            }

            const keyActions = {
                KeyN: () => {
                    this.add_ticket_modal = true;
                },
                KeyQ: () => {
                    this.refreshTickets();
                },
                KeyF: () => {
                    this.toggleAdvancedFilter(!this.advancedOpen);
                },
                KeyR: () => {
                    this.$refs.advancedFilter.resetAdvancedFilters();
                },
                KeyW: () => {
                    this.filters.waiting_for_reply = this.filters.waiting_for_reply === "yes" ? false : "yes";
                    this.maybeChangeWaitingReply();
                },
            };

            const action = keyActions[code];
            if (action) {
                event.preventDefault();
                action();
            }
        }
    },
    mounted() {
        const store = useTicketListStore();
        store.app_ready = true;
        this.setFromSaveFilters();

        if (this.$route.query.search) {
            this.storeResetWithOutFetch();
            store.filter_type = "simple";
            store.advanced_filters = [[]];
        }

        if (this.$route.query.agent_id) {
            store.filters.agent_id = this.$route.query.agent_id;
            store.filters.watcher = this.$route.query.watcher;
        }
        if (this.$route.query.waiting_for_reply) {
            store.filters.waiting_for_reply = this.$route.query.waiting_for_reply;
        }
        // Checked by key presence, not truthiness: an empty string means the
        // Ticket Overview drill-down explicitly wants no product filter
        // (see TicketOverview.vue's handleViewDetails()), which must still
        // override a stale filter left in the persistent store. The key
        // being absent entirely (e.g. arriving from the sidebar) leaves any
        // saved filter alone.
        if ('product_id' in this.$route.query) {
            const productId = this.$route.query.product_id;
            store.filters.product_id = productId ? (Array.isArray(productId) ? productId : [productId]) : [];
        }

        if (this.$route.query.tags) {
            const tagIds = this.$route.query.tags;
            if (typeof tagIds === "object") {
                store.filters.ticket_tags = tagIds.map((tagId) => {
                    return parseInt(tagId);
                });
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

        // Cache-aware fetch: show cached data instantly, then silent background refresh
        if (store.shouldShowCache) {
            store.first_time_loading = false;
            this.$nextTick(() => {
                store.fetchTickets(true, true)
                    .then(() => this.saveFilters())
                    .catch(() => {});
            });
        } else {
            this.$nextTick(() => {
                this.fetchTickets();
            });
        }

        this.$setTitle(this.$t("All Tickets"));
        if(this.appVars.keyboard_shortcuts === 'yes') {
            window.addEventListener("keydown", this.handleKeydown);
        }
    },
    beforeUnmount() {
        if(this.appVars.keyboard_shortcuts === 'yes') {
            window.removeEventListener("keydown", this.handleKeydown);
        }
    }
};</script>
