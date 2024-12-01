<template>
    <div class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>
                        {{ translate("Tickets") }}
                        <span class="fs_badge">{{ pagination.total }}</span>
                    </h3>
                    <el-button
                        @click="add_ticket_modal = true"
                        size="small"
                        icon="Plus"
                        >{{ translate("Add Ticket") }}
                    </el-button>
                    <el-button
                        @click="fetchTickets()"
                        icon="Refresh"
                        size="small"
                    ></el-button>
                    <el-switch
                        v-model="filter_type"
                        active-value="advanced"
                        inactive-value="simple"
                        :active-text="translate('Advanced Filter')"
                        inactive-text=""
                        style="margin-left: 0.6em"
                        :disabled="!has_pro"
                    />

                    <el-tooltip
                        class="box-item"
                        effect="dark"
                        content="Saved Filtering Options"
                        placement="top-start">
                        <div
                        v-if="filter_type === 'advanced'"
                        class="fs_advanced_filter_btn"
                        @click="fetchLabelSearch"
                    >
                        <svg
                            width="14"
                            height="10"
                            viewBox="0 0 14 10"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M5.5 9.5H8.5V8H5.5V9.5ZM0.25 0.5V2H13.75V0.5H0.25ZM2.5 5.75H11.5V4.25H2.5V5.75Z"
                                fill="#525866"
                            />
                        </svg>
                    </div>
                    </el-tooltip>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-select filterable @change="fetchTickets()" v-model="order_by" style="padding-right: 10px;">
                        <el-option
                            v-for="(column, columnName) in filterColumns"
                            :key="columnName"
                            :value="columnName"
                            :label="column"
                        ></el-option>
                    </el-select>
                    <el-button @click="changeOrderType()">
                        <el-icon v-if="order_type === 'DESC'">
                            <CaretBottom />
                        </el-icon>
                        <el-icon v-else> <CaretTop /> </el-icon>
                    </el-button>
                </div>
            </div>
            <div class="fs_box_body">
                <div v-if="filter_type === 'advanced'">
                    <div v-if="has_pro" class="fs_rich_container">
                        <div v-if="appReady" class="fs_rich_wrap">
                            <div
                                v-for="(
                                    rich_filter, filterIndex
                                ) in advanced_filters"
                                :key="filterIndex"
                            >
                                <div class="fs_rich_filter">
                                    <rich-filter
                                        @maybeRemove="
                                            maybeRemoveGroup(filterIndex)
                                        "
                                        :items="rich_filter"
                                    />
                                </div>
                                <div
                                    class="fs_cond_or"
                                    v-if="
                                        filterIndex + 1 !=
                                        advanced_filters.length
                                    "
                                >
                                    <em>{{ translate("OR") }}</em>
                                </div>
                            </div>
                        </div>
                        <div class="fs_cond_or">
                            <em
                                @click="addConditionGroup()"
                                style="
                                    cursor: pointer;
                                    color: rgb(0, 119, 204);
                                    font-weight: bold;
                                "
                                ><el-icon><Plus /></el-icon>
                                {{ translate("OR") }}</em
                            >
                        </div>

                        <el-button type="primary" @click="fetchTickets()"
                            >{{ translate("Filter") }}
                        </el-button>
                        <el-button
                            @click="
                                advanced_filters = [[]];
                                fetchTickets();
                            "
                        >
                            {{ translate("Clear Filters") }}
                        </el-button>
                        <el-button
                            v-if="countAdvancedFilterData(advanced_filters)"
                            @click="openSaveSearchModal"
                        >
                            {{ label_search_id ? translate("Update Search") : translate("Save Search") }}
                        </el-button>
                    </div>
                    <div class="fs_narrow_promo" v-else>
                        <h3>{{ translate("advance_filter_promo") }}</h3>
                        <p>{{ translate("pro_promo") }}</p>
                        <a
                            target="_blank"
                            rel="noopener"
                            href="https://fluentsupport.com"
                            class="el-button el-button--success"
                            >{{ translate("Upgrade To Pro") }}</a
                        >
                    </div>
                </div>
                <template v-else>
                    <div v-if="show_filters">
                        <ticket-filters
                            v-if="appReady"
                            @fetchTickets="fetchTickets"
                            :filters="filters"
                            :search="search"
                            @searchChange="
                                (s) => {
                                    search = s;
                                }
                            "
                            :reset-filters="resetFilters"
                        />
                    </div>
                    <el-button
                        size="small"
                        style="margin: 10px"
                        @click="show_filters = true"
                        v-else
                        >{{ translate("Show Filters") }}
                    </el-button>
                </template>

                <el-table
                    v-loading="loading"
                    v-if="app_ready && !first_time_loading"
                    :data="tickets"
                    stripe
                    class="fs_all_tickets_table"
                    @row-click="gotToTicket"
                    @selection-change="handleSelectionChange"
                    style="width: 100%"
                >
                    <el-table-column type="selection" width="55">
                    </el-table-column>
                    <el-table-column width="70" label="">
                        <template #default="scope">
                            <img
                                v-if="scope.row.customer"
                                :title="
                                    scope.row.customer.email +
                                    ' - ' +
                                    scope.row.customer.full_name
                                "
                                :alt="scope.row.customer.full_name"
                                class="tk_customer_avatar"
                                :src="scope.row.customer.photo"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column
                        min-width="300"
                        :label="translate('Title')"
                    >
                        <template #default="scope">
                            <div class="fs_tk_preview">
                                <strong>{{ scope.row.title }}</strong>
                                <span style="font-size: 10px">
                                    {{ translate(" by") }}
                                    {{ scope.row.customer.first_name }}</span
                                >
                                <span
                                    style="margin-left: 5px; font-size: 10px"
                                    v-if="
                                        scope.row.product && !filters.product_id
                                    "
                                    class="fs_badge"
                                >
                                    {{ scope.row.product?.title }}
                                </span>
                                <span
                                    style="font-size: 10px; margin-right: 5px"
                                    class="fs_tk_number"
                                >
                                    #{{ scope.row.id }}
                                    <span
                                        v-if="
                                            scope.row.live_activity &&
                                            scope.row.live_activity.length
                                        "
                                        class="fs_inline_avatars avatars_small"
                                    >
                                        <span>
                                            <img
                                                v-for="activity in scope.row
                                                    .live_activity"
                                                :title="activity.full_name"
                                                :src="activity.photo"
                                            />
                                        </span>
                                    </span>
                                </span>

                                <el-tooltip
                                    v-if="
                                        scope.row.mailbox?.settings
                                            .hide_business_box !== 'yes' &&
                                        !filters.mailbox_id
                                    "
                                    class="box-item"
                                    effect="dark"
                                    :content="
                                        translate('Inbox - ') +
                                        scope.row.mailbox?.name
                                    "
                                    placement="top"
                                >
                                    <span
                                        class="fs_inbox_identifier"
                                        :style="{
                                            backgroundColor:
                                                scope.row.mailbox?.settings
                                                    .color || '#a3b2bd',
                                        }"
                                        v-html="
                                            getExcerptBox(
                                                scope.row.mailbox?.name
                                            )
                                        "
                                    ></span>
                                </el-tooltip>

                                <span
                                    v-if="scope.row.source"
                                    style="margin-right: 5px"
                                    :title="'Source: ' + scope.row.source"
                                    :class="
                                        'fc_source_icon fc_source_icon_' +
                                        scope.row.source
                                    "
                                ></span>

                                <ticket-tags
                                    :tags="scope.row.tags"
                                    :ticket_id="scope.row.id"
                                ></ticket-tags>
                                <div class="prev_text_parent">
                                    <p
                                        class="fs_tk_preview_text"
                                        v-html="getExcerpt(scope.row)"
                                    ></p>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column width="60">
                        <template #default="scope">
                            <span class="fs_thread_count">{{
                                scope.row.response_count
                            }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column width="100" :label="translate('Manager')">
                        <template #default="scope">
                            <span
                                :title="scope.row.agent.full_name"
                                v-if="scope.row.agent"
                                >{{ scope.row.agent.first_name }}</span
                            >
                            <span v-else>n/a</span>
                        </template>
                    </el-table-column>
                    <el-table-column width="120" :label="translate('Status')">
                        <template #default="scope">
                            <span
                                class="fs_badge fs_badge_new"
                                v-if="getWaitingStatus(scope.row)"
                            >
                                {{ translate(getWaitingStatus(scope.row)) }}
                            </span>
                            <span
                                v-else
                                class="fs_badge"
                                :class="'fs_badge_' + scope.row.status"
                                >{{ translate(scope.row.status) }}</span
                            >
                            <span
                                class="fs_badge"
                                :title="
                                    translate('Client Priority: ') +
                                    translate(scope.row.client_priority)
                                "
                                :class="
                                    'fs_badge_priority_' +
                                    scope.row.client_priority
                                "
                            >
                                <el-icon><User /></el-icon>
                                <el-icon><Flag /></el-icon>
                                {{ translate(scope.row.client_priority) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="translate('Date')" width="180">
                        <template #default="scope">
                            <span
                                style="opacity: 0.4"
                                :title="
                                    translate('Ticket created at ') +
                                    scope.row.created_at
                                "
                            >
                                <el-icon style="vertical-align: middle">
                                    <ChatLineSquare />
                                </el-icon>
                                {{ $timeDiff(scope.row.created_at) }}
                            </span>
                            <span
                                style="display: block"
                                :title="
                                    translate('Waiting Since: ') +
                                    scope.row.waiting_since
                                "
                            >
                                <el-icon style="vertical-align: middle">
                                    <Stopwatch />
                                </el-icon>
                                {{ $timeDiff(scope.row.waiting_since) }}
                            </span>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-else style="padding: 15px">
                    <el-skeleton :rows="10" animated />
                </div>

                <div
                    style="padding-bottom: 20px"
                    class="fframe_pagination_wrapper"
                    v-if="tickets.length"
                >
                    <pagination
                        @fetch="fetchTickets()"
                        :pagination="pagination"
                    />
                </div>
            </div>
        </div>
        <modal
            :show="add_ticket_modal"
            @close="add_ticket_modal = false"
            :title="translate('Create a Ticket')"
        >
            <template #body>
                <add-ticket v-if="add_ticket_modal"></add-ticket>
            </template>
        </modal>

        <el-dialog
            :title="translate('Save search')"
            v-model="isLabelSearchOpen"
            @close="isLabelSearchOpen = false"
            class="fs_dialog"
        >
            <el-form label-position="top">
                <el-form-item :label="translate('Search Name')">
                    <el-input
                        type="text"
                        :placeholder="translate('Search Name')"
                        v-model="label_search_name"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        type="success"
                        @click="handleSaveSearch"
                        >{{ translate("Save") }}</el-button
                    >
                </span>
            </template>
        </el-dialog>

        <el-drawer
            :with-header="false"
            :append-to-body="true"
            v-model="openLabelSearchDrawer"
            class="fs-el-drawer"
        >
            <div class="fs_label_search_drawer">
                <div class="fs_label_search_drawer_header">
                    <div class="head">
                        <div class="left">
                            {{ $t("Saved Filtering Options") }}
                        </div>
                        <div class="right">
                            <div class="icon" @click="openLabelSearchDrawer = false">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <path
                                        d="M8 8L16 16M16 8L8 16"
                                        stroke="#1B2533"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fs-table-items">
                    <div
                        class="fs-each-search"
                        v-for="(item, index) in labelSearchList"
                        :key="index"
                    >
                        <div class="fs-search-name" @click="handleAdvanceSearch(item)">{{ item.label_search_name }}</div>

                        <div class="fs-search-actions">
                            <el-button @click="handleLabelSearchEdit(item)">
                                <svg
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M6.9725 15.6L16.1003 6.47215L14.8277 5.19955L5.6999 14.3274V15.6H6.9725ZM7.7186 17.4H3.8999V13.5813L14.1914 3.28975C14.3602 3.12103 14.5891 3.02625 14.8277 3.02625C15.0664 3.02625 15.2952 3.12103 15.464 3.28975L18.0101 5.83585C18.1788 6.00463 18.2736 6.23351 18.2736 6.47215C18.2736 6.7108 18.1788 6.93968 18.0101 7.10845L7.7186 17.4ZM3.8999 19.2H20.0999V21H3.8999V19.2Z"
                                        fill="#525866"
                                    />
                                </svg>
                            </el-button>
                            <el-popconfirm title="Are you sure to delete this?" @confirm="handleLabelSearchDelete(item.id)">
                                <template #reference>
                                    <el-button type="danger">
                                        <svg
                                            width="24"
                                            height="24"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <path
                                                d="M16.5 6.6H21V8.4H19.2V20.1C19.2 20.3387 19.1052 20.5676 18.9364 20.7364C18.7676 20.9052 18.5387 21 18.3 21H5.7C5.46131 21 5.23239 20.9052 5.0636 20.7364C4.89482 20.5676 4.8 20.3387 4.8 20.1V8.4H3V6.6H7.5V3.9C7.5 3.66131 7.59482 3.43239 7.7636 3.2636C7.93239 3.09482 8.1613 3 8.4 3H15.6C15.8387 3 16.0676 3.09482 16.2364 3.2636C16.4052 3.43239 16.5 3.66131 16.5 3.9V6.6ZM17.4 8.4H6.6V19.2H17.4V8.4ZM9.3 4.8V6.6H14.7V4.8H9.3Z"
                                                fill="#525866"
                                            />
                                        </svg>
                                    </el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div style="flex: auto">
                    <el-button @click="cancelClick">{{
                        translate("Close")
                    }}</el-button>
                </div>
            </template>
        </el-drawer>

        <ticket-bulk-actions
            v-if="appReady"
            @fetchTickets="fetchTickets()"
            :ticket_selections="ticket_selections"
        />
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import each from "lodash/each";
import AddTicket from "./_AddTicket";
import TicketTags from "./parts/_Tags";
import TicketFilters from "./parts/TicketFilters";
import TicketBulkActions from "./_BulkActions";
import RichFilter from "./parts/RichFilters/RichFilter";
import Modal from "../../Pieces/Modal";

const isEmpty = require("lodash/isEmpty");
const isArray = require("lodash/isArray");
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import { nextTick, onMounted, reactive, toRefs, watch, onUnmounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { Search } from "@element-plus/icons-vue";

export default {
    name: "AllTickets",
    components: {
        Modal,
        Pagination,
        AddTicket,
        TicketTags,
        TicketFilters,
        TicketBulkActions,
        RichFilter,
        Search,
    },
    setup() {
        const {
            appVars,
            get,
            post,
            del,
            translate,
            handleError,
            moment,
            setTitle,
            ucFirst,
            humanDiffTime,
            has_pro,
            getData,
            saveData,
        } = useFluentHelper();
        const { notify } = useNotify();
        const router = useRouter();
        const route = useRoute();
        const state = reactive({
            loading: false,
            tickets: [],
            pagination: {
                current_page: 1,
                total: 0,
                per_page: 10,
            },
            filters: {
                status_type: "open",
                product_id: "",
                agent_id: "",
                priority: "",
                client_priority: "",
                waiting_for_reply: "",
                ticket_tags: [],
                mailbox_id: "",
                watcher: "",
            },
            filterColumns: {
                id: translate("Ticket ID"),
                product_id: translate("Product ID"),
                priority: translate("Priority"),
                client_priority: translate("Client Priority"),
                title: translate("Title"),
                last_agent_response: translate("Last Agent Response"),
                last_customer_response: translate("Last Customer Response"),
                waiting_since: translate("Waiting Time"),
                response_count: translate("Response Count"),
                created_at: translate("Created At"),
            },
            label_search_id: '',
            search: "",
            label_search_name: "",
            labelSearchList: [],
            openLabelSearchDrawer: false,
            isLabelSearchOpen: false,
            order_by: "last_customer_response",
            order_type: "ASC",
            ticket_selections: [],
            doing_bulk: false,
            app_ready: false,
            add_ticket_modal: false,
            appReady: false,
            add_response_modal: false,
            show_filters: true,
            first_time_loading: true,
            advanced_filters: [[]],
            filter_type: "simple",
            openTicketInNewTab:
                appVars.open_ticket_in_new_tab === "yes" ? true : false,
        });

        const cancelClick = () => {
            state.openLabelSearchDrawer = false;
        };

        const fetchTickets = async () => {
            if (!state.app_ready) {
                return false;
            }
            state.ticket_selections = [];
            state.loading = true;
            let query = {
                page: state.pagination.current_page,
                per_page: state.pagination.per_page,
                order_by: state.order_by,
                order_type: state.order_type,
                filter_type: state.filter_type,
            };
            if (state.filter_type == "advanced" && has_pro) {
                query.advanced_filters = JSON.stringify(state.advanced_filters);
            } else {
                query.filters = state.filters;
                query.search = state.search;

                if (!has_pro) {
                    query.filter_type = "simple";
                    state.filter_type = "simple";
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
                await get("tickets", query)
                    .then((response) => {
                        if (
                            response.tickets.total &&
                            !response.tickets.from &&
                            state.pagination.current_page > 1
                        ) {
                            state.pagination.current_page = 1;
                            fetchTickets();
                            return false;
                        }

                        state.tickets = response.tickets.data;
                        window.fsCurrentFilteredTickets = response.tickets.data;
                        state.pagination.total = response.tickets.total;
                        saveFilters();
                    })
                    .always(() => {
                        state.loading = false;
                        state.first_time_loading = false;
                    })
                    .catch((error) => {
                        handleError(error);
                    });
            } catch (e) {
                handleError(e);
                state.loading = false;
            }
        };

        const addConditionGroup = () => {
            state.advanced_filters.push([]);
        };

        const maybeRemoveGroup = (index) => {
            if (state.advanced_filters.length > 1) {
                state.advanced_filters.splice(index, 1);
            }
        };

        const gotToTicket = (row) => {
            router.push({
                name: "view_ticket",
                params: { ticket_id: row.id },
            });
        };

        const setFromSaveFilters = (callback) => {
            state.filter_type = getData("tickets_filter_type", "simple");
            const filters = getData("tickets_filter", {});

            each(filters, (filter, filterKey) => {
                state.filters[filterKey] = filter;
            });

            const ticketPref = getData("tickets_pref", false);
            if (ticketPref) {
                state.order_by = ticketPref.order_by;
                state.order_type = ticketPref.order_type;
                state.pagination.per_page = ticketPref.per_page;
                state.pagination.current_page = ticketPref.current_page;
                state.search = ticketPref.search;
            }

            const advancedFilters = getData("tickets_advanced_filters", [[]]);
            if (advancedFilters) {
                state.advanced_filters = advancedFilters;
            }

            state.appReady = true;
        };

        const saveFilters = () => {
            saveData("tickets_pref", {
                order_by: state.order_by,
                order_type: state.order_type,
                per_page: state.pagination.per_page,
                search: state.search,
                current_page: state.pagination.current_page,
            });

            saveData("tickets_filter_type", state.filter_type);

            if (state.filter_type == "advanced") {
                saveData("tickets_advanced_filters", state.advanced_filters);
            } else {
                saveData("tickets_filter", state.filters);
            }
        };

        const changeOrderType = () => {
            if (state.order_type == "DESC") {
                state.order_type = "ASC";
            } else {
                state.order_type = "DESC";
            }
            fetchTickets();
        };

        const getWaitingStatus = (ticket) => {
            if (ticket.status == "closed") {
                return "";
            }
            if (ticket.status === "new") {
                return "new";
            }
            if (!ticket.last_agent_response) {
                return "waiting";
            } else if (!ticket.last_customer_response) {
                return "";
            }
            if (
                moment(ticket.last_agent_response).isAfter(
                    ticket.last_customer_response,
                    "seconds"
                )
            ) {
                return "";
            }

            return "waiting";
        };

        const getLastResponse = (ticket) => {
            if (
                moment(ticket.last_agent_response).isAfter(
                    ticket.last_customer_response,
                    "seconds"
                )
            ) {
                return humanDiffTime(ticket.last_agent_response);
            } else {
                return humanDiffTime(ticket.last_customer_response);
            }
        };

        const handleSelectionChange = (selections) => {
            const selectionIds = [];
            each(selections, (selection) => {
                selectionIds.push(selection.id);
            });
            state.ticket_selections = selectionIds;
        };

        const deleteSelected = () => {
            if (state.ticket_selections.length) {
                state.doing_bulk = true;
                del("tickets/bulk", {
                    ticket_ids: state.ticket_selections,
                })
                    .then((response) => {
                        if (response.success) {
                            fetchTickets();
                        }
                    })
                    .catch((error) => {
                        handleError(error);
                    })
                    .always(() => {
                        state.doing_bulk = false;
                    });
            }
        };

        const closeSelected = () => {
            state.doing_bulk = true;
            post("tickets/bulk", {
                ticket_ids: this.ticket_selections,
                bulk_action: "close_tickets",
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    fetchTickets();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.doing_bulk = false;
                });
        };

        const getExcerpt = (row) => {
            let text = row.preview_response
                ? row.preview_response.content
                : row.content;

            if (!text) {
                return "";
            }
            return text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "");
        };

        const resetFilters = () => {
            state.filters = {
                status_type: "open",
                product_id: "",
                agent_id: "",
                priority: "",
                client_priority: "",
                waiting_for_reply: "",
                ticket_tags: [],
            };
            state.search = "";
            state.order_type = "ASC";
            state.order_by = "last_customer_response";
            state.pagination.current_page = 1;
            if (route.query.agent_id) {
                state.filters.agent_id = route.query.agent_id;
            }
            if (route.query.watcher) {
                state.filters.watcher = route.query.watcher;
            }
            fetchTickets();
        };

        const resetWithOutFetch = () => {
            state.filters = {
                status_type: "open",
                product_id: "",
                agent_id: "",
                priority: "",
                client_priority: "",
                waiting_for_reply: "",
                ticket_tags: [],
            };
            state.search = "";
            state.pagination.current_page = 1;
        };

        const getExcerptBox = (text) => {
            return text.substring(0, 3).padEnd(5, ".");
        };

        const countAdvancedFilterData = (filters) => {
            return filters.reduce((total, innerArray) => {
                if (Array.isArray(innerArray)) {
                    const validEntries = innerArray.filter(
                        (item) => item.value && item.value.trim() !== ""
                    );
                    return total + validEntries.length;
                }
                return total;
            }, 0);
        };

        const handleSaveSearch = () => {
            if (!state.label_search_name || (state.filter_type === "advanced" && !state.advanced_filters.length)) {
                notify({
                    message: "Please provide a valid name or filter criteria.",
                    type: "warning",
                    position: "bottom-right",
                });
                return;
            }
            state.isLabelSearchOpen = false
            state.ticket_selections = [];
            state.loading = true;
            let query = {
                filter_type: state.filter_type,
                label_search_name: state.label_search_name,
            };

            if (state.filter_type == "advanced" && has_pro) {
                query.advanced_filters = JSON.stringify(state.advanced_filters);
            }

            if (state.label_search_id) {
                query.id = state.label_search_id;
            }

            post("tickets/label-search", {
                query,
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    state.label_search_id = null;
                    state.loading = false;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {});
        };

        const fetchLabelSearch = () => {
            state.openLabelSearchDrawer = true;
            get("tickets/label-search")
                .then((response) => {
                    state.labelSearchList = response;
                })
                .always(() => {
                    this.loading = false;
                })
                .catch((error) => {
                    handleError(error);
                });
        };

        const handleAdvanceSearch = (item) => {
            state.advanced_filters = JSON.parse(item.advanced_filters)
            state.filter_type = item.filter_type
            state.label_search_name = ''
            state.label_search_id = ''
            fetchTickets()
        } 

        const handleLabelSearchEdit = (item) => {
            state.advanced_filters = JSON.parse(item.advanced_filters)
            state.filter_type = item.filter_type
            state.label_search_name = item.label_search_name
            state.label_search_id = item.id
        }

        const openSaveSearchModal = () => {
            state.isLabelSearchOpen = true
            state.label_search_name = state.label_search_id ? state.label_search_name : ''
        }

        const handleLabelSearchDelete = (id) => {
            del(`tickets/${id}/label-search`)
                .then((response) => {
                    if (response) {
                        state.labelSearchList = state.labelSearchList.filter(item => item.id !== id);
                        notify({
                                message: response.message,
                                type: "success",
                                position: "bottom-right",
                            });
                        }
                })
                .catch((error) => {
                    handleError(error);
                })
                .always(() => {
                    state.doing_bulk = false;
                });
        }

        const handleKeydown = (event) => {
            const { metaKey, altKey, code } = event;

            if (metaKey && altKey && code === "KeyI") {
                return;
            }

            if (metaKey && altKey) {
                event.preventDefault();

                const keyActions = {
                    KeyN: () => {
                        state.add_ticket_modal = true;
                    },
                    KeyQ: () => {
                        fetchTickets();
                    },
                    KeyF: () => {
                        state.filter_type =
                            state.filter_type === "advanced"
                                ? "simple"
                                : "advanced";
                    },
                };

                const action = keyActions[code];
                if (action) {
                    action();
                }
            }
        };

        onMounted(() => {
            state.app_ready = true;
            setFromSaveFilters();

            if (route.query.agent_id) {
                state.filters.agent_id = route.query.agent_id;
                state.filters.watcher = route.query.watcher;
            }
            if (route.query.waiting_for_reply) {
                state.filters.waiting_for_reply = route.query.waiting_for_reply;
            }

            if (route.query.tags) {
                const tagIds = route.query.tags;
                if (typeof tagIds == "object") {
                    state.filters.ticket_tags = tagIds.map((tagId) => {
                        return parseInt(tagId);
                    });
                } else {
                    state.filters.ticket_tags = [parseInt(tagIds)];
                }
            }

            if (route.query.search) {
                state.search = route.query.search;
            }

            if (route.query.filter_type) {
                //resetWithOutFetch();
                state.filter_type = route.query.filter_type;
                state.filters.status_type = route.query.status_type;
            }

            nextTick(() => {
                fetchTickets();
            });
            setTitle(translate("All Tickets"));
            window.addEventListener("keydown", handleKeydown);
        });

        onUnmounted(() => {
            window.removeEventListener("keydown", handleKeydown);
        });

        watch(
            [
                () => route.query.agent_id,
                () => route.query.watcher,
                () => state.filter_type,
            ],
            (
                [newAgentId, newWatcher, newFilterType],
                [oldAgentId, oldWatcher, oldFilterType]
            ) => {
                if (state.app_ready) {
                    if (route.name == "view_ticket") {
                        return;
                    }

                    if (newAgentId !== oldAgentId) {
                        state.filters.agent_id = newAgentId;
                    }

                    if (newWatcher !== oldWatcher) {
                        state.filters.watcher = newWatcher;
                    }

                    if (newFilterType !== oldFilterType) {
                        state.filter_type = newFilterType;
                    }

                    fetchTickets();
                }
            }
        );

        return {
            appVars,
            get,
            del,
            translate,
            handleError,
            moment,
            setTitle,
            ucFirst,
            humanDiffTime,
            has_pro,
            getData,
            notify,
            ...toRefs(state),
            fetchTickets,
            addConditionGroup,
            maybeRemoveGroup,
            gotToTicket,
            setFromSaveFilters,
            saveFilters,
            changeOrderType,
            getWaitingStatus,
            getLastResponse,
            handleSelectionChange,
            deleteSelected,
            closeSelected,
            getExcerpt,
            resetFilters,
            getExcerptBox,
            handleSaveSearch,
            countAdvancedFilterData,
            cancelClick,
            fetchLabelSearch,
            handleAdvanceSearch,
            handleLabelSearchEdit,
            handleLabelSearchDelete,
            openSaveSearchModal,
        };
    },
};
</script>

<style lang="scss" scoped>
.ticket-action-buttons.el-dropdown-menu__item {
    &:focus {
        background: #eeeeee;
    }
}

.el-dropdown-menu__item {
    &:not(.is-disabled) {
        &:hover {
            background: #eeeeee;
        }
    }
}

.ticket-action-buttons {
    li.el-dropdown-menu__item {
        margin: 10px 0px;
    }
}

.fs_inbox_identifier {
    min-width: 45px;
    height: 19px;
    padding: 6px 12px;
    color: #fff;
    border-radius: 3px;
    opacity: 0.8;
    display: inline-flex;
    flex-direction: row;
    justify-content: space-evenly;
    align-items: center;
    margin-right: 6px;
}
.fs_follow_up_identifier {
    min-width: 45px;
    height: 19px;
    padding: 9px;
    color: #fff;
    border-radius: 3px;
    opacity: 0.8;
    display: inline-flex;
    flex-direction: column;
    justify-content: space-evenly;
    margin-right: 6px;
    background: #f6c343;
}
</style>
