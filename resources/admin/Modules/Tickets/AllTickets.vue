<template>
    <div v-loading="loading" class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('Tickets')}} <span class="fs_badge">{{ pagination.total }}</span></h3>
                    <el-button
                        @click="add_ticket_modal = true"
                        size="mini"
                        icon="el-icon-plus">{{$t('Add Ticket')}}</el-button>
                    <el-button v-loading="loading"
                               @click="fetchTickets()"
                               icon="el-icon-refresh"
                               size="mini"></el-button>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-select filterable @change="fetchTickets()" v-model="order_by" size="mini">
                        <el-option
                            v-for="(column, columnName) in filterColumns"
                            :key="columnName"
                            :value="columnName"
                            :label="column"
                        ></el-option>
                    </el-select>
                    <el-button @click="changeOrderType()" size="mini">
                        <i v-if="order_type == 'DESC'" class="el-icon-caret-bottom"></i>
                        <i v-else class="el-icon-caret-top"></i>
                    </el-button>
                </div>
            </div>
            <div class="fs_box_body">
                <ticket-filters
                    v-if="appReady"
                    @fetchTickets="fetchTickets"
                    :filters="filters"
                    :search="search"
                    @searchChange="(s) => { search = s; }"
                    :reset-filters="resetFilters" />
                <el-table
                    v-if="app_ready"
                    :data="tickets"
                    stripe
                    @row-click="gotToTicket"
                    @selection-change="handleSelectionChange"
                    style="width: 100%">
                    <el-table-column
                        type="selection"
                        width="55">
                    </el-table-column>
                    <el-table-column width="70" label="">
                        <template #default="scope">
                            <img v-if="scope.row.customer" :title="scope.row.customer.full_name"
                                 :alt="scope.row.customer.full_name"
                                 class="tk_customer_avatar" :src="scope.row.customer.photo"/>
                        </template>
                    </el-table-column>
                    <el-table-column min-width="300" :label="$t('Title')">
                        <template #default="scope">
                            <router-link class="fs_tk_preview"
                                         :to="{name: 'view_ticket', params: { ticket_id: scope.row.id }}">
                                <strong>{{ scope.row.title }}</strong>
                                <span style="margin-left: 5px;" v-if="scope.row.product" class="fs_badge">
                                    {{ scope.row.product?.title }}
                                </span>
                                <ticket-tags :tags="scope.row.tags" :ticket_id="scope.row.id"></ticket-tags>
                                <span style="margin-left: 10px;" class="fs_badge fs_badge_new"
                                      v-show="getWaitingStatus(scope.row)">
                                    {{ getWaitingStatus(scope.row) }}
                                </span>
                                <span class="fs_tk_number">
                                    #{{ scope.row.id }}
                                     <span v-if="scope.row.live_activity && scope.row.live_activity.length" class="fs_inline_avatars avatars_small">
                                        <span>
                                            <img v-for="activity in scope.row.live_activity" :title="activity.full_name" :src="activity.photo" />
                                        </span>
                                    </span>
                                </span>
                                <span v-if="scope.row.source == 'email'"><i class="el-icon-message"></i></span>
                                <div class="prev_text_parent">
                                    <p class="fs_tk_preview_text">{{ getExcerpt(scope.row) }}</p>
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
                            <span class="fs_badge" :class="'fs_badge_'+scope.row.status">{{ scope.row.status }}</span>
                            <span class="fs_badge" :title="$t('Client Priority: ') + scope.row.client_priority " :class="'fs_badge_priority_'+scope.row.client_priority">
                                <i class="el-icon-s-flag"></i>  {{ scope.row.client_priority }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column
                        :label="$t('Date')"
                        width="180">
                        <template #default="scope">
                            <span style="opacity: 0.4;" :title="$t('Ticket created at ') + scope.row.created_at">
                                <i class="el-icon-chat-line-square"></i> {{ $timeDiff(scope.row.created_at) }}
                            </span>
                            <span style="display: block;" :title="$t('Waiting Time')">
                                <i class="el-icon-stopwatch"></i> {{ $timeDiff(scope.row.waiting_since) }}
                            </span>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="fs_row">
                    <div class="fs_half fs_padded_20">
                        <div v-if="ticket_selections.length">
                            <el-dropdown trigger="click">
                                <el-button type="primary" size="mini">
                                    {{$t('Actions')}}<i class="el-icon-arrow-down el-icon--right"></i>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu class="ticket-action-buttons">

                                        <el-dropdown-item>
                                            <el-popconfirm
                                            @confirm="deleteSelected()"
                                            :title="$t('ticket_delete_warning')"
                                        >
                                                <template #reference>
                                                    <el-button
                                                        v-loading="doing_bulk"
                                                        :disabled="doing_bulk"
                                                        type="text"
                                                        size="small"
                                                        style="color: #F56C6C;">
                                                        {{$t('Delete Selected Tickets')}} ({{ ticket_selections.length }})
                                                    </el-button>
                                                </template>
                                            </el-popconfirm>
                                        </el-dropdown-item>

                                        <el-dropdown-item>
                                            <el-popconfirm
                                                @confirm="closeSelected()"
                                                :title="$t('bulk_ticket_close_warning')"
                                            >
                                                <template #reference>
                                                    <el-button
                                                        v-loading="doing_bulk"
                                                        :disabled="doing_bulk"
                                                        type="text"
                                                        size="small"
                                                        style="color: #303133;">
                                                        {{$t('Close Selected Tickets')}} ({{ ticket_selections.length }})
                                                    </el-button>
                                                </template>
                                            </el-popconfirm>
                                        </el-dropdown-item>

                                        <el-dropdown-item>
                                            <el-button
                                                v-loading="doing_bulk"
                                                :disabled="doing_bulk"
                                                @click="add_response_modal = true"
                                                type="text"
                                                size="small"
                                                style="color: #67C23A;">
                                                {{$t('Reply To Selected Tickets')}} ({{ ticket_selections.length }})
                                            </el-button>
                                        </el-dropdown-item>

                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                    <div class="fs_half">
                        <div style="padding-bottom: 20px;" class="fframe_pagination_wrapper">
                            <pagination @fetch="fetchTickets()" :pagination="pagination"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <el-dialog
            :title="$t('Create a Ticket')"
            v-model.sync="add_ticket_modal"
            width="60%">
            <add-ticket v-if="add_ticket_modal"></add-ticket>
        </el-dialog>

        <el-dialog
            :title="$t('Reply To Selected Tickets')"
            v-model.sync="add_response_modal"
            width="60%">
            <create-response @created="fetchTickets()" v-if="add_response_modal" :ticket="ticket_selections" />
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'
import each from 'lodash/each';
import AddTicket from './_AddTicket';
import TicketTags from './parts/_Tags';
import TicketFilters from '@/admin/Modules/Tickets/parts/TicketFilters';
import CreateResponse from "./_CreateResponse";

export default {
    name: 'AllTickets',
    components: {
        Pagination,
        AddTicket,
        TicketTags,
        TicketFilters,
        CreateResponse
    },
    data() {
        return {
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
                ticket_tags: []
            },
            search: '',
            order_by: 'last_customer_response',
            order_type: 'ASC',
            filterColumns: {
                id: this.$t('Ticket ID'),
                product_id: this.$t('Product ID'),
                priority: this.$t('Priority'),
                client_priority: this.$t('Client Priority'),
                title: this.$t('Title'),
                last_agent_response: this.$t('Last Agent Response'),
                last_customer_response: this.$t('Last Customer Response'),
                waiting_since: this.$t('Waiting Time'),
                response_count: this.$t('Response Count'),
                created_at: this.$t('Created At')
            },
            ticket_selections: [],
            doing_bulk: false,
            app_ready: false,
            add_ticket_modal: false,
            appReady: false,
            add_response_modal: false,
        }
    },
    watch: {
        '$route.query.agent_id'() {
            if (this.app_ready) {
                this.filters.agent_id = this.$route.query.agent_id;
                this.fetchTickets();
            }
        }
    },
    methods: {
        fetchTickets() {
            if(!this.app_ready) {
                return false;
            }
            this.ticket_selections = [];
            this.loading = true;
            this.$get('tickets', {
                page: this.pagination.current_page,
                per_page: this.pagination.per_page,
                order_by: this.order_by,
                order_type: this.order_type,
                search: this.search,
                filters: this.filters
            })
                .then(response => {
                    this.tickets = response.tickets.data;
                    this.pagination.total = response.tickets.total;
                    this.saveFilters();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                })
        },
        gotToTicket(row) {
            this.$router.push({
                name: 'view_ticket',
                params: {ticket_id: row.id}
            });
        },
        setFromSaveFilters(callback) {
            let filters = this.$getData('tickets_filter', {});
            each(filters, (filter, filterKey) => {
                this.filters[filterKey] = filter;
            });

            let ticketPref = this.$getData('tickets_pref', false);
            if (ticketPref) {
                this.order_by = ticketPref.order_by;
                this.order_type = ticketPref.order_type;
                this.pagination.per_page = ticketPref.per_page;
                this.pagination.current_page = ticketPref.current_page;
                this.search = ticketPref.search;
            }
            this.appReady = true;
        },
        saveFilters() {
            this.$saveData('tickets_filter', this.filters);
            this.$saveData('tickets_pref', {
                order_by: this.order_by,
                order_type: this.order_type,
                per_page: this.pagination.per_page,
                search: this.search,
                current_page: this.pagination.current_page
            });
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
                return 'require response';
            } else if (!ticket.last_customer_response) {
                return '';
            }
            if (this.moment(ticket.last_agent_response).isAfter(ticket.last_customer_response, 'seconds')) {
                return '';
            }

            return 'require response';
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
            let text = row.content;
            if(!text) {
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
            this.fetchTickets();
        }
    },
    mounted() {
        this.setFromSaveFilters();
        if (this.$route.query.agent_id) {
            this.filters.agent_id = this.$route.query.agent_id;
        }

        if(this.$route.query.tags) {
            const tagIds = this.$route.query.tags;
            if(typeof tagIds == 'object') {
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

        this.app_ready = true;
        this.$nextTick(() => {
            this.fetchTickets();
        });

        this.$setTitle('All Tickets');
    }
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
</style>
