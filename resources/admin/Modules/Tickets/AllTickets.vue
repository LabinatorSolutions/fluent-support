<template>
    <div class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('Tickets') }} <span class="fs_badge">{{ pagination.total }}</span></h3>
                    <el-button
                        @click="add_ticket_modal = true"
                        size="small"
                        icon="Plus">{{ $t('Add Ticket') }}
                    </el-button>
                    <el-button
                        @click="fetchTickets()"
                        icon="Refresh"
                        size="small"></el-button>
                    <el-switch
                        v-model="filter_type"
                        active-value="advanced"
                        inactive-value="simple"
                        active-text="Advanced Filter"
                        inactive-text=""
                        style="margin-left: 0.6em;"
                    />
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-select filterable @change="fetchTickets()" v-model="order_by" size="small" style="padding-right: 10px;">
                        <el-option
                            v-for="(column, columnName) in filterColumns"
                            :key="columnName"
                            :value="columnName"
                            :label="column"
                        ></el-option>
                    </el-select>
                    <el-button @click="changeOrderType()">
                        <el-icon v-if="order_type == 'DESC'"> <CaretBottom/> </el-icon>
                        <el-icon v-else> <CaretTop/> </el-icon>
                    </el-button>
                </div>
            </div>
            <div class="fs_box_body">
                <div v-if="filter_type == 'advanced'">
                    <div v-if="has_pro" class="fs_rich_container">
                        <div v-if="appReady" class="fs_rich_wrap">
                            <div v-for="(rich_filter, filterIndex) in advanced_filters" :key="filterIndex">
                                <div class="fs_rich_filter">
                                    <rich-filter @maybeRemove="maybeRemoveGroup(filterIndex)" :items="rich_filter"/>
                                </div>
                                <div class="fs_cond_or" v-if="(filterIndex+1) != advanced_filters.length">
                                    <em>OR</em>
                                </div>
                            </div>
                        </div>
                        <div class="fs_cond_or">
                            <em @click="addConditionGroup()"
                                style="cursor: pointer; color: rgb(0, 119, 204); font-weight: bold;"><el-icon><Plus /></el-icon> OR</em>
                        </div>

                        <el-button type="primary" @click="fetchTickets()">{{ $t('Filter') }}
                        </el-button>
                        <el-button @click="advanced_filters = [[]]; fetchTickets()">
                            {{ $t('Clear Filters') }}
                        </el-button>
                    </div>
                    <div class="fs_narrow_promo" v-else>
                        <h3>{{ $t('advance_filter_promo') }}</h3>
                        <p>{{ $t('pro_promo') }}</p>
                        <a target="_blank" rel="noopener" href="https://fluentsupport.com"
                           class="el-button el-button--success">{{ $t('Upgrade To Pro') }}</a>
                    </div>
                </div>
                <template v-else>
                    <div v-if="show_filters">
                        <ticket-filters
                            v-if="appReady"
                            @fetchTickets="fetchTickets"
                            :filters="filters"
                            :search="search"
                            @searchChange="(s) => { search = s; }"
                            :reset-filters="resetFilters"/>
                    </div>
                    <el-button size="small" style="margin: 10px;" @click="show_filters = true" v-else>{{$t('Show Filters')}}
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
                    style="width: 100%">
                    <el-table-column
                        type="selection"
                        width="55">
                    </el-table-column>
                    <el-table-column width="70" label="">
                        <template #default="scope">
                            <img v-if="scope.row.customer" :title="scope.row.customer.email +' - '+ scope.row.customer.full_name"
                                 :alt="scope.row.customer.full_name"
                                 class="tk_customer_avatar" :src="scope.row.customer.photo"/>
                        </template>
                    </el-table-column>
                    <el-table-column min-width="300" :label="$t('Title')">
                        <template #default="scope">
                            <router-link class="fs_tk_preview"
                                         :to="{name: 'view_ticket', params: { ticket_id: scope.row.id }}">
                                <strong>{{ scope.row.title }}</strong>
                                <span style="font-size: 10px;"> {{$t(' by')}} {{ scope.row.customer.first_name }}</span>
                                <span style="margin-left: 5px; font-size: 10px;"
                                      v-if="scope.row.product && !filters.product_id"
                                      class="fs_badge">
                                    {{ scope.row.product?.title }}
                                </span>
                                <span style="font-size: 10px; margin-right: 5px;" class="fs_tk_number">
                                    #{{ scope.row.id }}
                                     <span v-if="scope.row.live_activity && scope.row.live_activity.length"
                                           class="fs_inline_avatars avatars_small">
                                        <span>
                                            <img v-for="activity in scope.row.live_activity" :title="activity.full_name"
                                                 :src="activity.photo"/>
                                        </span>
                                    </span>
                                </span>

                                <el-tooltip
                                    v-if="scope.row.mailbox.settings.hide_business_box !=='yes' && !filters.mailbox_id"
                                    class="box-item"
                                    effect="dark"
                                    :content="$t('Inbox - ') + scope.row.mailbox.name"
                                    placement="top"
                                >
                                    <span class="fs_inbox_identifier" :style="{backgroundColor: scope.row.mailbox.settings.color || '#a3b2bd'}" v-html="getExcerptBox(scope.row.mailbox.name)"></span>
                                </el-tooltip>

                                <span v-if="scope.row.source" style="margin-right: 5px;" :title="'Source: ' + scope.row.source" :class="'fc_source_icon fc_source_icon_'+scope.row.source"></span>

                                <ticket-tags :tags="scope.row.tags" :ticket_id="scope.row.id"></ticket-tags>
                                <div class="prev_text_parent">
                                    <p class="fs_tk_preview_text" v-html="getExcerpt(scope.row)"></p>
                                </div>
                            </router-link>
                        </template>
                    </el-table-column>
                    <el-table-column width="60">
                        <template #default="scope">
                            <span class="fs_thread_count">{{ scope.row.response_count }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column width="100" :label="$t('Manager')">
                        <template #default="scope">
                            <span :title="scope.row.agent.full_name"
                                  v-if="scope.row.agent">{{ scope.row.agent.first_name }}</span>
                            <span v-else>n/a</span>
                        </template>
                    </el-table-column>
                    <el-table-column width="120" :label="$t('Status')">
                        <template #default="scope">
                            <span class="fs_badge fs_badge_new" v-if="getWaitingStatus(scope.row)">
                                    {{ getWaitingStatus(scope.row) }}
                                </span>
                            <span v-else class="fs_badge" :class="'fs_badge_'+scope.row.status">{{
                                    scope.row.status
                                }}</span>
                            <span class="fs_badge" :title="$t('Client Priority: ') + scope.row.client_priority "
                                  :class="'fs_badge_priority_'+scope.row.client_priority"> <el-icon><User/></el-icon>
                                <el-icon><Flag/></el-icon>  {{ scope.row.client_priority }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column
                        :label="$t('Date')"
                        width="180">
                        <template #default="scope">
                            <span style="opacity: 0.4;" :title="$t('Ticket created at ') + scope.row.created_at">
                                <el-icon style="vertical-align: middle;"> <ChatLineSquare /> </el-icon> {{ $timeDiff(scope.row.created_at) }}
                            </span>
                            <span style="display: block;" :title="$t('Waiting Time')">
                                <el-icon style="vertical-align: middle;"> <Stopwatch /> </el-icon> {{ $timeDiff(scope.row.waiting_since) }}
                            </span>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-else style="padding: 15px;">
                    <el-skeleton :rows="10" animated/>
                </div>

                <div style="padding-bottom: 20px;" class="fframe_pagination_wrapper">
                    <pagination @fetch="fetchTickets()" :pagination="pagination"/>
                </div>
            </div>
        </div>
        <modal :show="add_ticket_modal" @close="add_ticket_modal = false" :title="$t('Create a Ticket')">
            <template #body>
                <add-ticket v-if="add_ticket_modal"></add-ticket>
            </template>
        </modal>

        <ticket-bulk-actions v-if="appReady" @fetchTickets="fetchTickets()" :ticket_selections="ticket_selections"/>

    </div>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'
import each from 'lodash/each';
import AddTicket from './_AddTicket';
import TicketTags from './parts/_Tags';
import TicketFilters from "./parts/TicketFilters";
import TicketBulkActions from './_BulkActions';
import RichFilter from "./parts/RichFilters/RichFilter";
import Modal from "../../Pieces/Modal";

const isEmpty = require('lodash/isEmpty');
const isArray = require('lodash/isArray');
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";
import {nextTick, onMounted, reactive, toRefs} from "vue";
import {createRouter as router, useRoute} from "vue-router";

export default {
    name: 'AllTickets',
    components: {
        Modal,
        Pagination,
        AddTicket,
        TicketTags,
        TicketFilters,
        TicketBulkActions,
        RichFilter
    },
    setup() {
        const {
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
        } = useFluentHelper();
        const {notify} = useNotify();
        const route = useRoute()
        const state = reactive({
            loading: false,
            tickets: [],
            pagination: {
                current_page: 1,
                total: 0,
                per_page: 10
            },
            filters: {
                status_type: 'open',
                product_id: '',
                agent_id: '',
                priority: '',
                client_priority: '',
                waiting_for_reply: '',
                ticket_tags: [],
                mailbox_id: '',
                watcher: '',
            },
            filterColumns: {
                id: translate('Ticket ID'),
                product_id: translate('Product ID'),
                priority: translate('Priority'),
                client_priority: translate('Client Priority'),
                title: translate('Title'),
                last_agent_response: translate('Last Agent Response'),
                last_customer_response: translate('Last Customer Response'),
                waiting_since: translate('Waiting Time'),
                response_count: translate('Response Count'),
                created_at: translate('Created At')
            },
            search: '',
            order_by: 'last_customer_response',
            order_type: 'ASC',
            ticket_selections: [],
            doing_bulk: false,
            app_ready: false,
            add_ticket_modal: false,
            appReady: false,
            add_response_modal: false,
            first_time_loading: true,
            advanced_filters: false,
            filter_type: 'simple'
        })

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
                filter_type: state.filter_type
            };
            if(state.filter_type == 'advanced' && has_pro) {
                query.advanced_filters = JSON.stringify(state.advanced_filters);
            } else {
                query.filters = state.filters;
                query.search = state.search;

                if(!has_pro) {
                    query.filter_type = 'simple';
                    state.filter_type = 'simple';
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
                await get('tickets', query)
                    .then(response => {
                        if (response.tickets.total && (!response.tickets.from && state.pagination.current_page > 1)) {
                            state.pagination.current_page = 1;
                            fetchTickets();
                            return false;
                        }

                        state.tickets = response.tickets.data;
                        state.pagination.total = response.tickets.total;
                        //this.saveFilters();
                    })
                    .always(() => {
                        state.loading = false;
                        state.first_time_loading = false;
                    })
                    .catch(error => {
                        handleError(error);
                    });
            } catch (e) {
                handleError(e);
                state.loading = false;
            }
        }

        const addConditionGroup = () =>{
            state.advanced_filters.push([]);
        }

        const maybeRemoveGroup = (index) =>{
            if (state.advanced_filters.length > 1) {
                state.advanced_filters.splice(index, 1);
            }
        }

        const gotToTicket = (row) =>{
            router.push({
                name: 'view_ticket',
                params: {ticket_id: row.id}
            });
        }

        const setFromSaveFilters = (callback) => {
            state.filter_type = getData('tickets_filter_type', 'simple');
            const filters = getData('tickets_filter', {});

            each(filters, (filter, filterKey) => {
                state.filters[filterKey] = filter;
            });

            const ticketPref = getData('tickets_pref', false);
            if (ticketPref) {
                state.order_by = ticketPref.order_by;
                state.order_type = ticketPref.order_type;
                state.pagination.per_page = ticketPref.per_page;
                state.pagination.current_page = ticketPref.current_page;
                state.search = ticketPref.search;
            }

            const advancedFilters = getData('tickets_advanced_filters', false);
            if(advancedFilters) {
                state.advanced_filters = advancedFilters;
            }

            state.appReady = true;
        }

        onMounted(() => {
            state.app_ready = true;
            setFromSaveFilters();

            if (route.query.agent_id) {
                state.filters.agent_id = route.query.agent_id;
                state.filters.watcher = route.query.watcher;
            }
            if (route.query.waiting_for_reply) {
                this.filters.waiting_for_reply = route.query.waiting_for_reply;
            }

            if (route.query.tags) {
                const tagIds = route.query.tags;
                if (typeof tagIds == 'object') {
                    state.filters.ticket_tags = tagIds.map(tagId => {
                        return parseInt(tagId);
                    });
                } else {
                    state.filters.ticket_tags = [parseInt(tagIds)];
                }
            }

            if (route.query.search) {
                state.search = route.query.search;
            }

            if(route.query.filter_type){
                //resetWithOutFetch();
                state.filter_type = route.query.filter_type;
                state.filters.status_type = route.query.status_type;
            }

            nextTick(() => {
                fetchTickets();
            });
            setTitle(translate('All Tickets'));
        });

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
        }
    },
    watch: {
        '$route.query.agent_id'() {
            if (this.app_ready) {
                this.filters.agent_id = this.$route.query.agent_id;
                this.fetchTickets();
            }
        },
        '$route.query.watcher'() {
            if (this.app_ready) {
                this.filters.watcher = this.$route.query.watcher;
                this.fetchTickets();
            }
        },
        '$route.query.status_type'() {
            if (this.app_ready) {
                this.filters.status_type = this.$route.query.status_type;
                this.fetchTickets();
            }
        },
        '$route.query.filter_type'() {
            if (this.app_ready) {
                this.filter_type = this.$route.query.filter_type;
                this.fetchTickets();
            }
        },
        '$route.query.waiting_for_reply'() {
            if (this.app_ready) {
                this.filters.waiting_for_reply = this.$route.query.waiting_for_reply;
                this.fetchTickets();
            }
        },
    },
    methods: {
        saveFilters() {
            this.$saveData('tickets_pref', {
                order_by: this.order_by,
                order_type: this.order_type,
                per_page: this.pagination.per_page,
                search: this.search,
                current_page: this.pagination.current_page
            });

            this.$saveData('tickets_filter_type', this.filter_type);

            if(this.filter_type == 'advanced') {
                this.$saveData('tickets_advanced_filters', this.advanced_filters);
            } else {
                this.$saveData('tickets_filter', this.filters);
            }

        },
        changeOrderType() {
            if (this.order_type == 'DESC') {
                this.order_type = 'ASC';
            } else {
                this.order_type = 'DESC';
            }
            this.fetchTickets();
        },
        getWaitingStatus(ticket) {
            if (ticket.status == 'closed') {
                return '';
            }
            if (!ticket.last_agent_response) {
                return 'waiting';
            } else if (!ticket.last_customer_response) {
                return '';
            }
            if (this.moment(ticket.last_agent_response).isAfter(ticket.last_customer_response, 'seconds')) {
                return '';
            }

            return 'waiting';
        },
        getLastResponse(ticket) {
            if (this.moment(ticket.last_agent_response).isAfter(ticket.last_customer_response, 'seconds')) {
                return this.$timeDiff(ticket.last_agent_response);
            } else {
                return this.$timeDiff(ticket.last_customer_response);
            }
        },
        handleSelectionChange(selections) {
            const selectionIds = [];
            each(selections, (selection) => {
                selectionIds.push(selection.id);
            })
            this.ticket_selections = selectionIds;
        },
        deleteSelected() {
            this.doing_bulk = true;
            this.$del('tickets/bulk', {
                ticket_ids: this.ticket_selections
            })
                .then((response) => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right',
                        type: 'success'
                    });
                    this.fetchTickets();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.doing_bulk = false;
                });
        },
        closeSelected() {
            this.doing_bulk = true;
            this.$post('tickets/bulk', {
                ticket_ids: this.ticket_selections,
                bulk_action: 'close_tickets'
            })
                .then((response) => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right',
                        type: 'success'
                    });
                    this.fetchTickets();
                })
                .catch((errros) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.doing_bulk = false;
                });
        },
        getExcerpt(row) {
            let text = (row.preview_response) ? row.preview_response.content : row.content;

            if (!text) {
                return '';
            }
            return text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "");
        },
        resetFilters() {
            this.filters = {
                status_type: 'open',
                product_id: '',
                agent_id: '',
                priority: '',
                client_priority: '',
                waiting_for_reply: '',
                ticket_tags: []
            };
            this.search = '';
            this.pagination.current_page = 1;
            this.fetchTickets();
        },
        resetWithOutFetch(){
            this.filters = {
                status_type: 'open',
                product_id: '',
                agent_id: '',
                priority: '',
                client_priority: '',
                waiting_for_reply: '',
                ticket_tags: []
            };
            this.search = '';
            this.pagination.current_page = 1;
        },
        getExcerptBox(text) {
            return text.substring(0, 3).padEnd(5, '.');
        },
    },
   /* mounted() {
        this.setFromSaveFilters();
        if (this.$route.query.agent_id) {
            this.filters.agent_id = this.$route.query.agent_id;
            this.filters.watcher = this.$route.query.watcher;
        }

        if (this.$route.query.waiting_for_reply) {
            this.filters.waiting_for_reply = this.$route.query.waiting_for_reply;
        }

        if (this.$route.query.tags) {
            const tagIds = this.$route.query.tags;
            if (typeof tagIds == 'object') {
                this.filters.ticket_tags = tagIds.map(tagId => {
                    return parseInt(tagId);
                });
            } else {
                this.filters.ticket_tags = [parseInt(tagIds)];
            }
        }

        if (this.$route.query.search) {
            this.search = this.$route.query.search;
        }

        if(this.$route.query.filter_type){
            this.resetWithOutFetch();
            this.filter_type = this.$route.query.filter_type;
            this.filters.status_type = this.$route.query.status_type;
        }

        this.app_ready = true;
        this.$nextTick(() => {
            this.fetchTickets();
        });

        this.$setTitle('All Tickets');
    }*/
}
</script>

<style lang="scss" scoped>
.ticket-action-buttons.el-dropdown-menu__item {
    &:focus {
        background: #EEEEEE;
    }
}

.el-dropdown-menu__item {
    &:not(.is-disabled) {
        &:hover {
            background: #EEEEEE;
        }
    }
}

.ticket-action-buttons {
    li.el-dropdown-menu__item {
        margin: 10px 0px;
    }
}

.fs_inbox_identifier{
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
}
</style>
