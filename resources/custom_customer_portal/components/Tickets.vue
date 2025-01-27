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

        handlePageChange(page) {
            this.pagination.current_page = page
        },

        updateSearchQuery(search) {
            this.search = search;
        },

        debouncedSearch: debounce(function () {
            this.pagination.current_page = 1
        }, 300)
    },
}
</script>

