<template>
    <div v-loading="loading" class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Tickets</h3>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-select @change="fetchTickets()" v-model="order_by" size="mini">
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
                <div class="fs_tk_filters">
                    <div class="fs_tk_filter">
                        <label>Status</label>
                        <el-radio-group size="small" @change="fetchTickets()" v-model="filters.status_type">
                            <el-radio-button label="open">Open</el-radio-button>
                            <el-radio-button label="new">New</el-radio-button>
                            <el-radio-button label="closed">Closed</el-radio-button>
                            <el-radio-button label="all">All</el-radio-button>
                        </el-radio-group>
                    </div>
                    <div class="fs_tk_filter">
                        <label>Product</label>
                        <el-select clearable size="small" @change="fetchTickets()" v-model="filters.product_id"
                                   placeholder="All Product">
                            <el-option v-for="product in products" :key="product.id" :value="product.id"
                                       :label="product.title"></el-option>
                        </el-select>
                    </div>
                    <div class="fs_tk_filter">
                        <label>Support Staff</label>
                        <el-select clearable size="small" @change="fetchTickets()" v-model="filters.agent_id"
                                   placeholder="All Support Staff">
                            <el-option v-for="agent in agents" :key="agent.id" :value="agent.id"
                                       :label="agent.full_name"></el-option>
                        </el-select>
                    </div>
                    <div class="fs_tk_filter">
                        <label>Priority (Admin)</label>
                        <el-select clearable size="small" @change="fetchTickets()" v-model="filters.priority"
                                   placeholder="All">
                            <el-option v-for="(priority,priorityKey) in appVars.admin_priorities" :key="priorityKey"
                                       :value="priorityKey" :label="priority"></el-option>
                        </el-select>
                    </div>
                    <div class="fs_tk_filter">
                        <label>Priority (Customer)</label>
                        <el-select clearable size="small" @change="fetchTickets()" v-model="filters.client_priority"
                                   placeholder="All">
                            <el-option v-for="(priority,priorityKey) in appVars.client_priorities" :key="priorityKey"
                                       :value="priorityKey" :label="priority"></el-option>
                        </el-select>
                    </div>
                    <div class="fs_tk_filter">
                        <label>Waiting for Reply</label>
                        <el-switch @change="fetchTickets()" active-value="yes" :inactive-value="false"
                                   v-model="filters.waiting_for_reply"></el-switch>
                    </div>
                    <div class="fs_tk_filter">
                        <label>Search</label>
                        <el-input @keyup.enter="fetchTickets()" clearable @clear="fetchTickets()" size="mini"
                                  placeholder="Please input" v-model="search">
                            <template #append>
                                <el-button @click="fetchTickets()" icon="el-icon-search"></el-button>
                            </template>
                        </el-input>
                    </div>
                    <div class="fs_tk_filter">
                        <el-button @click="resetFilters()" size="small">Reset Filters</el-button>
                    </div>
                </div>
                <el-table
                    :data="tickets"
                    stripe
                    @row-click="gotToTicket"
                    style="width: 100%">
                    <el-table-column width="160" label="Customer">
                        <template #default="scope">
                            <span v-if="scope.row.customer">{{ scope.row.customer.full_name }}</span>
                            <span v-else>n/a</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="Title">
                        <template #default="scope">
                            <router-link class="fs_tk_preview"
                                         :to="{name: 'view_ticket', params: { ticket_id: scope.row.id }}">
                                <strong>{{ scope.row.title }}</strong> <span v-if="scope.row.product"
                                                                             class="fs_product">{{
                                    scope.row.product?.title
                                }}</span>
                                <span class="fs_tk_number"> #{{ scope.row.id }}</span>
                                <p class="fs_tk_preview_text">{{ scope.row.excerpt }}</p>
                            </router-link>
                        </template>
                    </el-table-column>
                    <el-table-column width="60">
                        <template #default="scope">
                            <span class="fs_thread_count">{{ scope.row.response_count }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column width="100" label="Manager">
                        <template #default="scope">
                            <span :title="scope.row.agent.full_name"
                                  v-if="scope.row.agent">{{ scope.row.agent.first_name }}</span>
                            <span v-else>not assigned</span>
                        </template>
                    </el-table-column>
                    <el-table-column width="100" label="Status">
                        <template #default="scope">
                            <span class="fs_badge" :class="'fs_badge_'+scope.row.status">{{ scope.row.status }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column
                        prop="created_at"
                        label="Date"
                        width="180">
                    </el-table-column>
                </el-table>
                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="fetchTickets()" :pagination="pagination"/>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'
import each from 'lodash/each';

export default {
    name: 'AllTickets',
    components: {
        Pagination
    },
    data() {
        return {
            loading: false,
            tickets: [],
            pagination: {
                current_page: 1,
                total: 0,
                per_page: 15
            },
            filters: {
                status_type: 'open',
                product_id: '',
                agent_id: '',
                priority: '',
                client_priority: '',
                waiting_for_reply: ''
            },
            search: '',
            order_by: 'id',
            order_type: 'ASC',
            products: this.appVars.support_products,
            agents: this.appVars.support_agents,
            filterColumns: {
                id: 'Ticket ID',
                product_id: 'Product ID',
                priority: 'Priority',
                client_priority: 'Client Priority',
                title: 'Title',
                last_agent_response: 'Last Agent Response',
                last_customer_response: 'Last Customer Response',
                response_count: 'Response Count'
            }
        }
    },
    watch: {
        '$route.query.agent_id'() {
            this.filters.agent_id = this.$route.query.agent_id;
            this.fetchTickets();
        }
    },
    methods: {
        fetchTickets() {
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
            if(ticketPref) {
                this.order_by = ticketPref.order_by;
                this.order_type = ticketPref.order_type;
                this.pagination.per_page = ticketPref.per_page;
                this.pagination.current_page = ticketPref.current_page;
                this.search = ticketPref.search;
            }
            
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
        resetFilters() {
            this.filters = {
                status_type: 'open',
                product_id: '',
                agent_id: '',
                priority: '',
                client_priority: '',
                waiting_for_reply: ''
            };
            this.search = '';

            this.fetchTickets();
        },
        changeOrderType() {
            if(this.order_type == 'DESC') {
                this.order_type = 'ASC';
            } else {
                this.order_type = 'DESC';
            }
            this.fetchTickets();
        }
    },
    mounted() {
        this.setFromSaveFilters();
        if (this.$route.query.agent_id) {
            this.filters.agent_id = this.$route.query.agent_id;
        }
        this.fetchTickets();
    }
}
</script>
