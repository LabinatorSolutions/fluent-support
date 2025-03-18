<template>
    <div class="fs_ticket_wrapper">
        <div class="fs_logout_container">
            <div v-if="appVars.show_logout" class="fs_logout_button">
                <a @click.prevent="logout" href="#" class="fs_btn fs_btn_logout">
                    <svg class="fs_logout_icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4.75 17.5C4.55109 17.5 4.36032 17.421 4.21967 17.2803C4.07902 17.1397 4 16.9489 4 16.75V3.25C4 3.05109 4.07902 2.86032 4.21967 2.71967C4.36032 2.57902 4.55109 2.5 4.75 2.5H15.25C15.4489 2.5 15.6397 2.57902 15.7803 2.71967C15.921 2.86032 16 3.05109 16 3.25V5.5H14.5V4H5.5V16H14.5V14.5H16V16.75C16 16.9489 15.921 17.1397 15.7803 17.2803C15.6397 17.421 15.4489 17.5 15.25 17.5H4.75ZM14.5 13V10.75H9.25V9.25H14.5V7L18.25 10L14.5 13Z" fill="#FB3748"/>
                    </svg>
                    <span class="fs_logout_text">{{ $t('Log Out') }}</span>
                </a>
            </div>
        </div>
        <div class="fs_tickets_container">
            <div class="fs_tickets_header">
                <label> {{$t('All Tickets')}}</label>
                <el-button class="fs_create_ticket_btn" @click="$router.push({ name: 'create_ticket' })">
                    {{ '+ ' + $t('Create Ticket') }}
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
                    <el-table-column prop="title" :label="$t('Conversation')" class-name="conversation-cell" width="450">
                        <template #default="{ row }">
                            <router-link class="fs_ticket_conversation"
                                         :to="{ name: 'view_ticket', params: { ticket_id: row.id }}">
                                <div class="fs_ticket_title">
                                    <strong>{{ row.title }}</strong>
                                    <span v-show="parseInt(row?.response_count) > 0" class="fs_response_count">
                                      {{ row.response_count }}
                                    </span>
                                </div>
                                <p class="fs_ticket_preview">{{ getExcerpt(row) }}</p>
                            </router-link>
                        </template>
                    </el-table-column>
                    <el-table-column prop="human_date" :label="$t('Date')" class-name="date-cell" width="160"/>
                    <el-table-column prop="status" :label="$t('Status')" class-name="fs_status_cell" width="120">
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
        logout() {
            this.$post('logout')
                .then(response => {
                    window.location.reload()
                })
                .catch((errors) => {
                    console.log(errors);
                });
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
