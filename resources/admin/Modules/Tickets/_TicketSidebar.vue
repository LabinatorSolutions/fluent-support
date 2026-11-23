<template>
    <div class="fs_tk_sidebar_wrap_new">
        <!-- Client Information Widget -->
        <collapsible-widget
            v-if="ticket && ticket.customer"
            :title="$t('Customer Informations')"
            icon="User"
            :default-expanded="true"
            class="fs_sidebar_widget fs_sidebar_widget_ci"
        >
            <div class="fs_client_info_body">
                <!-- Avatar and Name -->
                <div class="fs_client_header">
                    <router-link :to="{name: 'view_customer', params: { customer_id: ticket.customer.id }}">
                        <div class="fs_client_avatar">
                            <img :src="ticket.customer.photo" :alt="ticket.customer.full_name"/>
                        </div>
                    </router-link>
                    <div class="fs_client_details">
                        <div class="fs_client_name_row">
                            <h4 class="fs_client_name">
                                {{ ticket.customer.full_name }}
                                <span class="fs_blocked_badge" v-if="ticket.customer.status == 'inactive'">({{$t('Blocked')}})</span>
                            </h4>
                        </div>
                        <p class="fs_client_email">
                            <a rel="noopener" target="_blank" v-if="ticket.customer.profile_edit_url"
                               :href="ticket.customer.profile_edit_url">
                                {{ ticket.customer.email }}
                            </a>
                            <span v-else>{{ ticket.customer.email }}</span>
                        </p>
                        <p v-if="ticket.customer.user_id && ticket.customer.profile_edit_url" class="fs_client_meta">
                            <a rel="noopener" target="_blank" :href="ticket.customer.profile_edit_url" class="fs_client_meta_link">
                                <!-- translators: %s: WordPress user ID -->
                                <span>{{ $t('WP User: #%s', ticket.customer.user_id) }}</span>
                                <svg class="fs_client_meta_icon" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                    <path d="M5.1 1.95V2.85H2.85V9.15H9.15V6.9H10.05V9.6C10.05 9.71935 10.0026 9.83381 9.9182 9.9182C9.83381 10.0026 9.71935 10.05 9.6 10.05H2.4C2.28065 10.05 2.16619 10.0026 2.0818 9.9182C1.99741 9.83381 1.95 9.71935 1.95 9.6V2.4C1.95 2.28065 1.99741 2.16619 2.0818 2.0818C2.16619 1.99741 2.28065 1.95 2.4 1.95H5.1ZM8.5137 2.85H6.45V1.95H10.05V5.55H9.15V3.4863L6 6.6363L5.3637 6L8.5137 2.85Z" fill="currentColor"/>
                                </svg>
                            </a>
                        </p>
                    </div>
                    <el-icon v-if="appVars.me.permissions.includes('fst_sensitive_data')"
                             class="fs_client_more_icon"
                             @click="customerManagement">
                        <More />
                    </el-icon>
                </div>

                <!-- Customer Address -->
                <p class="fs_customer_address">
                    {{ getCustomerAddress(ticket.customer) }}
                </p>

                <!-- Customer Note -->
                <div v-if="ticket.customer.note" class="fs_customer_note">
                    {{ ticket.customer.note }}
                </div>

                <!-- FluentCRM Profile -->
                <fluent-crm-profile v-if="fluentcrm_profile" :crm_profile="fluentcrm_profile" />
            </div>
        </collapsible-widget>

        <!-- Time Logging Widget -->
        <collapsible-widget
            v-if="appVars.agent_time_tracking === 'yes' && has_pro"
            :title="$t('Time Logging')"
            icon="Timer"
            :default-expanded="false"
            class="fs_sidebar_widget"
        >
            <TaskTimer :ticket_id="ticket_id" :customer_id="ticket.customer_id" />
        </collapsible-widget>

        <!-- Loading State -->
        <!-- Shaped like a widget card with list rows (extra widgets, previous conversations).
             Only until the first widgets response; a refresh keeps the loaded widgets on screen. -->
        <div class="fs_sidebar_widget fs_sidebar_loading fs_widget_skeleton" v-if="loading && !extra_widgets" aria-busy="true">
            <el-skeleton animated :loading="true">
                <template #template>
                    <el-skeleton-item variant="text" style="width: 55%;" />
                    <div v-for="n in 2" :key="n" class="fs_skeleton_lines" style="margin-top: 14px;">
                        <el-skeleton-item variant="text" :style="{ width: [80, 65][n - 1] + '%' }" />
                        <el-skeleton-item variant="text" style="width: 40%; height: 10px;" />
                    </div>
                </template>
            </el-skeleton>
        </div>

        <fluent-booking-sidebar-widget
            v-if="ticket && appVars.fluent_booking?.active && can_manage_tickets"
            :ticket-id="ticket_id"
            :refresh-key="fetch_other_tickets"
        />

        <!-- Watchers/Bookmarks Widget -->
        <!-- translators: %s is the number of agents watching the ticket -->
        <collapsible-widget :title="$t('Watchers (%s)', watchers.length)"
            v-if="has_pro && watchers.length"
            icon="View"
            :default-expanded="false"
            class="fs_sidebar_widget"
        >
            <div class="fs_watchers_body">
                <ul class="fs_watchers_rows" role="list">
                    <li v-for="watcher in watchers" :key="watcher.id" class="fs_watcher_row">
                        <img class="fs_watcher_avatar" :src="watcher.photo" alt="">
                        <span class="fs_watcher_name">{{ watcher.full_name }}</span>
                        <button
                            v-if="can_manage_tickets"
                            type="button"
                            class="fs_watcher_remove"
                            :aria-label="$t('Remove watcher')"
                            :title="$t('Remove watcher')"
                            :disabled="saving"
                            @click="handleClose(watcher.id)"
                        >
                            <el-icon><Close /></el-icon>
                        </button>
                    </li>
                </ul>

                <el-popover :persistent="false"
                    v-if="can_manage_tickets"
                    v-model:visible="add_watcher"
                    placement="bottom-start"
                    :width="260"
                    trigger="click"
                    popper-class="fs_popover fs_watcher_popover"
                    @after-enter="$refs.watcherSelect && $refs.watcherSelect.focus()"
                >
                    <template #reference>
                        <button type="button" class="fs_watcher_add" :class="{ is_open: add_watcher }">
                            <el-icon><Plus /></el-icon>
                            {{ $t('Add Watcher') }}
                        </button>
                    </template>

                    <el-select
                        ref="watcherSelect"
                        :model-value="null"
                        filterable
                        :placeholder="$t('Select an agent')"
                        :disabled="saving"
                        class="fs_watcher_select fs_select_field"
                        @change="addWatcher"
                    >
                        <el-option
                            v-for="agent in watcherCandidates"
                            :key="agent.id"
                            :value="agent.id"
                            :label="agent.full_name"
                        ></el-option>
                    </el-select>
                </el-popover>
            </div>
        </collapsible-widget>

        <!-- Extra Widgets (WooCommerce, FluentCart, etc.) -->
        <collapsible-widget
            v-for="(widget, widget_key) in renderableWidgets"
            :key="widget_key"
            :title="widget.title || widget.header"
            :icon="widgetIcon(widget)"
            :default-expanded="true"
            :class="`fs_tk_widget_${widget_key}`"
            class="fs_sidebar_widget fs_tk_extra_card"
        >
            <!-- Widgets sent as stats / sections -->
            <structured-widget v-if="isStructuredWidget(widget)" :widget="widget" />

            <!-- Other Widgets -->
            <div v-else-if="widget.body_html" class="fs_widget_custom_body">
                <div v-html="sanitizeWidgetHtml(widget.body_html)"></div>
            </div>
        </collapsible-widget>

        <!-- Dynamic Template Widgets (extensible via PHP/JS hooks) -->
        <DynamicTemplates
            v-if="ticket"
            filter="ticket_sidebar"
            :widgetsQuery="{ ticket_id: ticket.id }"
            :data="{ ticket }"
        />

        <!-- Previous Conversations Widget -->
        <collapsible-widget :title="previousConversationsTitle"
            v-if="other_tickets && other_tickets.length"
            icon="ChatLineSquare"
            :default-expanded="true"
            class="fs_sidebar_widget fs_tk_other_tickets_card"
        >
            <div class="fs_previous_conversations_body">
                <previous-ticket-list :tickets="other_tickets" />
                <div v-if="otherTicketsMore" class="fs_prev_ticket_footer">
                    <button type="button" class="fs_prev_ticket_more" :disabled="loadingMoreTickets" @click="fetchMoreOtherTickets">
                        <el-icon v-if="loadingMoreTickets" class="is-loading"><Loading /></el-icon>
                        <span v-else>{{ $t('Load more') }}</span>
                    </button>
                </div>
            </div>
        </collapsible-widget>

        <!-- Additional Details Widget -->
        <collapsible-widget
            :title="$t('Additional Details')"
            icon="Setting"
            :default-expanded="true"
            class="fs_sidebar_widget"
        >
            <div class="fs_additional_details_body">
                <!-- Client Priority -->
                <div class="fs_detail_field">
                    <label class="fs_detail_label">{{ $t('Client Priority') }}</label>
                    <el-select
                        v-model="ticket.client_priority"
                        @change="updateTicketPriority('client_priority')"
                        size="small"
                        class="fs_select_field"
                        :disabled="!can_manage_tickets"
                    >
                        <el-option
                            v-for="(priorityLabel, priority) in client_priorities"
                            :key="priority"
                            :value="priority"
                            :label="priorityLabel"
                        ></el-option>
                    </el-select>
                </div>

                <!-- Agent Priority -->
                <div class="fs_detail_field">
                    <label class="fs_detail_label">{{ $t('Agent Priority') }}</label>
                    <el-select
                        v-model="ticket.priority"
                        @change="updateTicketPriority('priority')"
                        size="small"
                        class="fs_select_field"
                        :disabled="!can_manage_tickets"
                    >
                        <el-option
                            v-for="(priorityLabel, priority) in admin_priorities"
                            :key="priority"
                            :value="priority"
                            :label="priorityLabel"
                        ></el-option>
                    </el-select>
                </div>

                <!-- Add Tags -->
                <div v-if="can_manage_tickets" class="fs_detail_field">
                    <label class="fs_detail_label">{{ $t('Add Tags') }}</label>
                    <div class="fs_tags_wrapper">
                        <ticket-tags :creatable="true" :ticket_id="ticket.id" :tags.sync="ticket.tags"/>
                    </div>
                </div>
            </div>
        </collapsible-widget>

        <ticket-stats-widget :ticket="ticket" />

        <!-- Customer Management Dialog -->
        <el-dialog v-model="customerManagementModal" :append-to-body="true" :title="$t('Customer Management')" class="fs_dialog">
            <el-tabs v-model="activeTabName" @tab-click="handleClick" class="fs_customer_management_tabs">
                <el-tab-pane :label="$t('Update Customer')" name="update_customer_data">
                    <customer-form ref="customerForm" @updated="closeModal" source="ticket" :customer="ticket.customer" :hide-buttons="true"/>
                </el-tab-pane>

                <el-tab-pane :label="$t('Change Customer')" name="change_customer">
                    <el-form :data="ticket" label-position="top">
                        <el-form-item :label="$t('Select Customer')">
                            <remote-selector
                                v-model="customerID"
                                response_key="customers"
                                api_path="customers"
                                value_selector="id"
                                label_joiner=" - "
                                :label_selectors="['full_name','email']"
                                clearable
                            />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>
            </el-tabs>
            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button class="fs_outline_btn" @click="customerManagementModal = false">
                        {{ $t('Cancel') }}
                    </el-button>
                    <el-button
                        v-if="activeTabName === 'change_customer'"
                        @click="changeCustomer(customerID)"
                        :disabled="changing || !customerID"
                        v-loading="changing"
                        class="fs_filled_btn"
                    >
                        {{ $t('Change Customer') }}
                    </el-button>
                    <el-button v-else class="fs_filled_btn" @click="$refs.customerForm.updateCustomer()">
                        {{ $t('Update Customer') }}
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import CustomerForm from "../Customers/_CustomerForm";
import RemoteSelector from "../../Pieces/RemoteSelector";
import FluentCrmProfile from "./parts/_CrmProfile";
import TaskTimer from "./parts/_TaskTimer";
import CollapsibleWidget from "./parts/_CollapsibleWidget.vue";
import TicketStatsWidget from "./parts/_TicketStatsWidget.vue";
import StructuredWidget, { WIDGET_ICONS, isStructuredWidget, filterRenderableWidgets } from "./parts/_StructuredWidget.vue";
import PreviousTicketList from "./parts/_PreviousTicketList.vue";
import TicketTags from "./parts/_Tags.vue";
import DynamicTemplates from "../../Bits/DynamicTemplates/DynamicTemplates.vue";
import FluentBookingSidebarWidget from "./_FluentBookingSidebarWidget.vue";
import { each } from "lodash-es";
import { sanitizeWidgetHtml } from '@/common/domPurifyGuards';

export default {
    name: "TicketSidebar",
    components: {
        CustomerForm,
        RemoteSelector,
        FluentCrmProfile,
        TaskTimer,
        CollapsibleWidget,
        StructuredWidget,
        PreviousTicketList,
        TicketTags,
        DynamicTemplates,
        FluentBookingSidebarWidget,
        TicketStatsWidget,
    },
    props: ["ticket_id", "ticket", "fluentcrm_profile", "watchers", "watcher_ids", "fetch_other_tickets", "can_manage_tickets"],
    data() {
        return {
            loading: true,
            extra_widgets: false,
            other_tickets: [],
            watcherIds: [],
            customerManagementModal: false,
            changing: false,
            activeTabName: "update_customer_data",
            add_watcher: false,
            ticketSummary: "",
            ticketTone: "",
            agents: this.appVars.support_agents,
            client_priorities: this.appVars.client_priorities,
            admin_priorities: this.appVars.admin_priorities,
            customerID: null,
            otherTicketsPage: 1,
            otherTicketsTotal: 0,
            otherTicketsMore: false,
            loadingMoreTickets: false,
            loadMoreRequest: null,
            widgetsRequest: null,
        };
    },
    watch: {
        watcher_ids(newIds) {
            this.watcherIds = [...(newIds || [])];
        },
        // The sidebar is reused when moving between tickets, so resync from each loaded ticket.
        watchers(list) {
            this.watcherIds = (list || []).map(watcher => String(watcher.id));
        },
        ticket_id() {
            // Forget the old ticket's requests first, so their callbacks (run when abort() settles them) change nothing
            const widgetsRequest = this.widgetsRequest;
            const loadMoreRequest = this.loadMoreRequest;
            this._widgetsToken = null;
            this._loadMoreToken = null;
            this.widgetsRequest = null;
            this.loadMoreRequest = null;
            this.loadingMoreTickets = false;
            if (widgetsRequest) {
                widgetsRequest.abort();
            }
            if (loadMoreRequest) {
                loadMoreRequest.abort();
            }
            this.other_tickets = [];
            this.extra_widgets = null;
            this.otherTicketsPage = 1;
            this.otherTicketsMore = false;
            this.fetchWidgets();
        },
        fetch_other_tickets() {
            this.otherTicketsPage = 1;
            this.otherTicketsMore = false;
            this.fetchWidgets();
        },
    },
    mounted() {
        this.fetchWidgets();
        this.watcherIds = [...(this.watcher_ids || [])];
        if (this.has_pro && this.ticket.watchers && this.ticket.watchers.length > 0) {
            this.watcherIds = this.ticket.watchers.map((watcher) => watcher.tag_id.toString());
        }
    },
    computed: {
        previousConversationsTitle() {
            if (!this.otherTicketsTotal) {
                return this.$t('Previous Conversations');
            }
            // translators: %s is the number of other tickets from this customer
            return this.$t('Previous Conversations (%s)', this.otherTicketsTotal);
        },
        // Agents who aren't watching yet, for the Add Watcher picker.
        watcherCandidates() {
            const watching = (this.watcherIds || []).map(String);
            return (this.agents || []).filter(agent => !watching.includes(String(agent.id)));
        },
        // Widgets in an older custom format have nothing to draw: skip them.
        renderableWidgets() {
            return filterRenderableWidgets(this.extra_widgets);
        }
    },
    methods: {
        isStructuredWidget,
        widgetIcon(widget) {
            return WIDGET_ICONS.includes(widget.icon) ? widget.icon : 'ShoppingCart';
        },
        sanitizeWidgetHtml,
        fetchMoreOtherTickets() {
            if (this.loadingMoreTickets || !this.otherTicketsMore) return;
            this.loadingMoreTickets = true;
            const nextPage = this.otherTicketsPage + 1;
            // An aborted or superseded request may still settle; only the current one may change the sidebar.
            // Kept off data(): a plain object, so === works.
            const token = {};
            this._loadMoreToken = token;
            const request = this.$get(`tickets/${this.ticket_id}/widgets`, { page: nextPage })
                .then((response) => {
                    if (this._loadMoreToken !== token) return;
                    this.otherTicketsPage = nextPage;
                    this.other_tickets = this.other_tickets.concat(response.other_tickets);
                    this.otherTicketsTotal = response.other_tickets_total;
                    this.otherTicketsMore = response.other_tickets_more;
                })
                .catch((errors) => this.$handleError(errors))
                .always(() => {
                    if (this._loadMoreToken !== token) return;
                    this._loadMoreToken = null;
                    this.loadingMoreTickets = false;
                    this.loadMoreRequest = null;
                });
            if (this._loadMoreToken === token) {
                this.loadMoreRequest = request;
            }
        },
        fetchWidgets() {
            this.loading = true;
            const token = {};
            this._widgetsToken = token;
            const request = this.$get(`tickets/${this.ticket_id}/widgets`, { with: ['other_tickets', 'extra_widgets'] })
                .then((response) => {
                    if (this._widgetsToken !== token) return;
                    this.other_tickets = response.other_tickets;
                    this.otherTicketsTotal = response.other_tickets_total;
                    this.otherTicketsMore = response.other_tickets_more;
                    this.otherTicketsPage = 1;
                    this.extra_widgets = response.extra_widgets;
                })
                .catch((errors) => this.$handleError(errors))
                .always(() => {
                    if (this._widgetsToken !== token) return;
                    this._widgetsToken = null;
                    this.loading = false;
                    this.widgetsRequest = null;
                });
            if (this._widgetsToken === token) {
                this.widgetsRequest = request;
            }

            },
        customerManagement() {
            this.customerManagementModal = !this.customerManagementModal;
        },
        changeCustomer(customer_id) {
            this.changing = true;
            this.$put(`tickets/${this.ticket_id}/change-customer`, { customer: customer_id })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right"
                    });
                    this.closeModal();
                })
                .catch((errors) => this.$handleError(errors))
                .always(() => {
                    this.changing = false;
                });
        },
        handleClick(tab) {
            this.activeTabName = tab.props.name;
        },
        closeModal() {
            this.customerManagementModal = false;
            window.location.reload();
        },
        getCustomerAddress(customer) {
            if (!customer.custom_field_keys) return "";
            return customer.custom_field_keys
                .map((key) => customer[key])
                .filter(Boolean)
                .join(", ");
        },
        handleClose(watcherId) {
            this.$confirm({
                message: this.$t("watcher_remove_warning"),
                title: this.$t("Warning"),
                options: {
                    confirmButtonText: this.$t("Yes"),
                    cancelButtonText: this.$t("No"),
                    type: "warning",
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'el-button--danger fs_filled_btn',
                },
            })
                .then(() => {
                    this.updateWatcher(this.watcherIds.filter(id => String(id) !== String(watcherId)));
                })
                .catch((errors) => this.$handleError(errors));
        },
        addWatcher(agentId) {
            if (!agentId || this.watcherIds.map(String).includes(String(agentId))) {
                return;
            }
            this.updateWatcher([...this.watcherIds, String(agentId)]);
        },
        // Keep the local list unchanged until the save succeeds, so a failed request can be retried.
        updateWatcher(watcherIds) {
            this.saving = true;
            this.$post(`tickets/${this.ticket.id}/sync-watchers`, { watchers: watcherIds })
                .then((response) => {
                    this.watcherIds = watcherIds;
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right"
                    });
                    this.$emit("refresh");
                    this.add_watcher = false;
                    this.saving = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    this.saving = false;
                });
        },
        updateTicketPriority(propName, propValue = null) {
            // If propValue is provided, use it; otherwise read from props.ticket
            const value = propValue !== null ? propValue : this.ticket[propName];
            this.$put(`tickets/${this.ticket_id}/property`, { prop_name: propName, prop_value: value })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right"
                    });
                    each(response.update_data, (data, key) => {
                        this.ticket[key] = data;
                    });
                    this.$emit("refresh");
                })
                .catch((errors) => this.$handleError(errors));
        },
    },
};
</script>

