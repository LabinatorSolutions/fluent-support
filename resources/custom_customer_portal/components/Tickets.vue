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
                <table>
                    <thead>
                    <tr>
                        <th class="conversation-header">Conversation</th>
                        <th class="fs_date_header">Date</th>
                        <th class="fs_status_header">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-if="loading">
                        <td colspan="3">
                            <el-skeleton :rows="5" animated/>
                        </td>
                    </tr>
                    <tr v-else v-for="ticket in tickets" :key="ticket.id" class="ticket-row">
                        <td class="conversation-cell">
                            <router-link class="fs_ticket_conversation"
                                         :to="{name: 'view_ticket', params: { ticket_id: ticket.id }}">
                                <div class="fs_ticket_title">
                                    <strong>{{ ticket.title }}</strong>
                                    <span class="fs_response_count">{{ ticket.response_count }}</span>
                                </div>
                                <p class="fs_ticket_preview">{{ getExcerpt(ticket) }}</p>
                            </router-link>
                        </td>
                        <td class="date-cell">{{ ticket.human_date }}</td>
                        <td class="fs_status_cell">
                                <span :class="['fs_status_badge', getStatusClass(ticket.status)]">
                                    <span class="fs_status_dot"></span>
                                    {{ ticket.status }}
                                </span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</template>

<script>
import {debounce} from 'lodash'
import {List} from '@element-plus/icons-vue'
import TicketFilters from "@/custom_customer_portal/components/_TicketFilter.vue";

export default {
    name: 'TicketsList',
    components: {
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
            this.fetchTickets()
        }, 300)
    },
    watch: {
        filterType: {
            handler() {
                this.fetchTickets()
            }
        },
        search: {
            handler() {
                this.debouncedSearch()
            }
        },
    },
    created() {
        this.fetchTickets()
    }
}
</script>
