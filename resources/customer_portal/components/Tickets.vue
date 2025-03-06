<template>
    <div class="fs_ticket_wrapper">
        <div class="fs_tickets_container">
            <div class="fs_tickets_header">
                <label>All Tickets</label>
                <el-button class="fs_create_ticket_btn" @click="$router.push({ name: 'create_ticket' })">
                    + Create a New Ticket
                </el-button>
            </div>

            <div class="fs_filters_section">
                <TicketFilters
                    :filters="filters"
                    :sorting="sorting"
                    :sortingColumns="sortingColumns"
                    :search.sync="search"
                    :statusFilter.sync="filterType"
                    :appVars="appVars"
                    @fetch-tickets="fetchTickets"
                />
            </div>

            <div class="fs_tickets_table">
                <el-table
                    :data="tickets"
                    v-loading="loading"
                    style="width: 100%"
                    :header-cell-style="{ background: '#f5f7fa', color: '#0e121b', fontWeight: '500'}"
                    :row-class-name="'ticket-row'"
                >
                    <el-table-column prop="title" label="Conversation" class-name="conversation-cell" width="450">
                        <template #default="{ row }">
                            <router-link class="fs_ticket_conversation"
                                         :to="{ name: 'view_ticket', params: { ticket_id: row.id }}">
                                <div class="fs_ticket_title">
                                    <strong>{{ row.title }}</strong>
                                    <span class="fs_response_count">{{ row.response_count }}</span>
                                </div>
                                <p class="fs_ticket_preview">{{ getExcerpt(row) }}</p>
                            </router-link>
                        </template>
                    </el-table-column>
                    <el-table-column prop="human_date" label="Date" class-name="date-cell" width="160"/>
                    <el-table-column prop="status" label="Status" class-name="fs_status_cell" width="120">
                        <template #default="{ row }">
                            <span :class="['fs_status_badge', getStatusClass(row.status)]">
                                <span class="fs_status_dot"></span>
                                {{ row.status }}
                            </span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <div
                style="padding-bottom: 20px"
                class="fs_pagination_section"
                v-if="tickets.length"
            >
                <Pagination
                    @fetch="fetchTickets()"
                    :pagination="pagination"
                />
            </div>
        </div>
    </div>
</template>

<script>
import {debounce} from 'lodash'
import {List} from '@element-plus/icons-vue'
import TicketFilters from "@/customer_portal/components/_TicketFilter.vue";
import Pagination from "./pieces/Pagination";

export default {
    name: 'TicketsList',
    components: {
        Pagination,
        TicketFilters,
        List
    },
    data() {
        return {
            tickets: [],
            filterType: 'all',
            loading: true,
            search: '',
            filters: {
                product_id: ''
            },
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            },
            sortingColumns: [
                {
                    label: this.$t('Ticket ID'),
                    value: 'id'
                },
                {
                    label: this.$t('Title'),
                    value: 'title'
                },
                {
                    label: this.$t('Created at'),
                    value: 'created_at'
                },
            ],
            sorting: {
                sort_by: 'id',
                sort_type: 'desc'
            }
        }
    },
    computed: {
        totalPages() {
            return Math.ceil(this.pagination.total / this.pagination.per_page)
        }
    },
    methods: {
        async fetchTickets() {
            this.loading = true
            try {
                const response = await this.$get('tickets', {
                    per_page: this.pagination.per_page,
                    page: this.pagination.current_page,
                    filter_type: this.filterType,
                    search: this.search,
                    filters: this.filters,
                    sorting: this.sorting
                })

                if (response.tickets && response.tickets.data) {
                    this.tickets = response.tickets.data
                    this.pagination.total = response.tickets.total
                }
            } catch (error) {
                console.error('Error fetching tickets:', error)
            } finally {
                this.loading = false
            }
        },

        getStatusClass(status) {
            return status.toLowerCase()
        },

        getExcerpt(row) {
            let text = (row.preview_response) ? row.preview_response.content : row.content;
            if (!text) {
                return '';
            }
            return text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "");
        },

        handlePageChange(page) {
            this.pagination.current_page = page
            this.fetchTickets()
        },

        updateSearchQuery(search) {
            this.search = search;
        },

        debouncedSearch: debounce(function () {
            this.pagination.current_page = 1
            this.fetchTickets()
        }, 300)
    },
    watch: {
        filterType: {
            handler() {
                this.pagination.current_page = 1
                this.fetchTickets()
            }
        },
        search: {
            handler() {
                this.debouncedSearch()
            }
        },
        'pagination.per_page': {
            handler() {
                this.fetchTickets()
            }
        }
    },
    created() {
        this.fetchTickets()
    }
}
</script>

<style lang="scss">

</style>
