<template>
    <div class="fs_ticket_wrapper">
        <div class="fs_tickets_container">
            <div class="fs_tickets_header">
                <h2 class="fs_tickets_heading">{{ $t('All Tickets') }}</h2>

                <div class="fs_tickets_actions">
                    <el-button
                        :disabled="appVars.customer_status === 'inactive'"
                        class="fs_create_ticket_btn"
                        @click="$router.push({ name: 'create_ticket' })"
                    >
                        + {{ $t('create_ticket_cta') }}
                    </el-button>

                    <el-button v-if="appVars.show_logout" class="fs_logout_btn" @click="logout">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"
                             xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M4.75 17.5C4.55109 17.5 4.36032 17.421 4.21967 17.2803C4.07902 17.1397 4 16.9489 4 16.75V3.25C4 3.05109 4.07902 2.86032 4.21967 2.71967C4.36032 2.57902 4.55109 2.5 4.75 2.5H15.25C15.4489 2.5 15.6397 2.57902 15.7803 2.71967C15.921 2.86032 16 3.05109 16 3.25V5.5H14.5V4H5.5V16H14.5V14.5H16V16.75C16 16.9489 15.921 17.1397 15.7803 17.2803C15.6397 17.421 15.4489 17.5 15.25 17.5H4.75ZM14.5 13V10.75H9.25V9.25H14.5V7L18.25 10L14.5 13Z"
                                fill="currentColor"/>
                        </svg>
                        <span>{{ $t('Log Out') }}</span>
                    </el-button>
                </div>
            </div>

            <div class="fs_filters_section">
                <TicketFilters
                    :filters="filters"
                    :sorting="sorting"
                    :sortingColumns="sortingColumns"
                    :search="search"
                    :statusFilter="filterType"
                    @update:search.sync="search = $event"
                    @update:statusFilter="filterType = $event"
                    :appVars="appVars"
                    @fetch-tickets="refetchFromFirstPage"
                />
            </div>

            <div v-if="appVars.customer_status === 'inactive'" class="fs_inactive_message_container">
                <div class="fs_inactive_message">
                    <div class="fs_inactive">
                        <svg width="148" height="148" viewBox="0 0 148 148" fill="none"
                             xmlns="http://www.w3.org/2000/svg">
                            <rect width="148" height="148" rx="74" fill="#F2F5F8"/>
                            <path
                                d="M113.01 64C115.459 64 117.444 65.9887 117.444 68.442V102.07C117.444 104.523 115.459 106.512 113.01 106.512H108.127C107.31 106.512 106.648 107.175 106.648 107.993V115.767C106.648 117.086 105.056 117.747 104.125 116.814L94.2745 106.946C93.9973 106.668 93.6213 106.512 93.2293 106.512H59.4342C56.9853 106.512 55 104.523 55 102.07V68.442C55 65.9887 56.9852 64 59.4342 64H113.01Z"
                                fill="#CACFD8" stroke="#99A0AE" stroke-linecap="round" stroke-linejoin="round"/>
                            <path
                                d="M34.4342 46C31.9853 46 30 47.9887 30 50.442V90.4078C30 92.861 31.9853 94.8497 34.4342 94.8497H40.9276C41.7439 94.8497 42.4056 95.5127 42.4056 96.3304V106.016C42.4056 107.335 43.9977 107.996 44.9289 107.063L56.688 95.2834C56.9652 95.0057 57.3411 94.8497 57.7332 94.8497H97.3194C99.7684 94.8497 101.754 92.861 101.754 90.4077V50.442C101.754 47.9887 99.7684 46 97.3194 46H34.4342Z"
                                fill="white" stroke="#99A0AE" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M45.1162 57.9929L51.8613 64.7505L45.1162 71.5075" stroke="#99A0AE"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M86.6377 57.9929L79.8926 64.7505L86.6377 71.5075" stroke="#99A0AE"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M77.2182 82.3687H54.5342" stroke="#99A0AE" stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="fs_inactive_text">
                        {{ $t('customer_inactive_message') }}
                    </div>
                </div>
            </div>

            <!-- No v-loading in the portal: its mask could cover the whole window while closing. The list dims instead. -->
            <div v-else class="fs_ticket_list_wrap"
                 :class="{ fs_ticket_list_wrap_tall: loading || !tickets.length, fs_ticket_list_loading: loading && !first_loading }"
                 :aria-busy="loading ? 'true' : 'false'">
                <!-- The first load shows placeholder rows; later loads keep the current rows and dim them -->
                <el-skeleton v-if="first_loading" animated class="fs_portal_skeleton" aria-hidden="true">
                    <template #template>
                        <ul class="fs_ticket_list">
                            <li v-for="row in 5" :key="row" class="fs_ticket_item">
                                <div class="fs_ticket_link">
                                    <div class="fs_ticket_main fs_skeleton_lines">
                                        <el-skeleton-item variant="text" :style="{ width: (row % 2 ? 55 : 40) + '%' }"/>
                                        <el-skeleton-item variant="text" style="width: 85%;"/>
                                    </div>
                                    <div class="fs_ticket_meta">
                                        <el-skeleton-item variant="button" class="fs_skeleton_badge"/>
                                        <el-skeleton-item variant="text" style="width: 64px;"/>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </template>
                </el-skeleton>
                <ul v-else-if="tickets.length" class="fs_ticket_list">
                    <li v-for="ticket in tickets" :key="ticket.id" class="fs_ticket_item">
                        <router-link class="fs_ticket_link"
                                     :to="{ name: 'view_ticket', params: { ticket_id: ticket.serial_number || ticket.id }}">
                            <div class="fs_ticket_main">
                                <div class="fs_ticket_title">
                                    <strong>{{ ticket.title }}</strong>
                                    <span v-if="parseInt(ticket.response_count) > 0" class="fs_response_count"
                                          :title="replyCountLabel(ticket)">
                                        {{ ticket.response_count }}
                                    </span>
                                </div>
                                <p class="fs_ticket_preview">{{ getExcerpt(ticket) }}</p>
                            </div>
                            <div class="fs_ticket_meta">
                                <span v-if="isWaitingOnCustomer(ticket)" class="fs_waiting_on_you">{{ $t('Waiting for your reply') }}</span>
                                <span :class="['fs_status_badge', getStatusClass(ticket.status)]">
                                    <span class="fs_status_dot"></span>
                                    {{ appVars.ticket_statuses[ticket.status] || ticket.status }}
                                </span>
                                <span class="fs_ticket_date">{{ ticket.human_date }}</span>
                            </div>
                        </router-link>
                    </li>
                </ul>
                <div v-else-if="!loading" class="fs_ticket_list_empty">
                    <p class="fs_ticket_list_empty_title">{{ emptyState.title }}</p>
                    <p v-if="emptyState.text" class="fs_ticket_list_empty_text">{{ emptyState.text }}</p>
                    <el-button :class="emptyState.primary ? 'fs_create_ticket_btn' : 'fs_ticket_list_empty_action'"
                               @click="emptyState.onClick">
                        {{ emptyState.action }}
                    </el-button>
                </div>
                <div v-if="loading && !first_loading" class="fs_ticket_list_spinner">
                    <span class="fs_spinner" aria-hidden="true"></span>
                </div>
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

<script type="text/babel">
import { debounce } from 'lodash-es';
import TicketFilters from "./_TicketFilter.vue";
import Pagination from "./pieces/Pagination";

export default {
    name: 'TicketsList',
    components: {
        Pagination,
        TicketFilters
    },
    data() {
        return {
            inactiveMessage: '',
            tickets: [],
            filterType: 'all',
            loading: true,
            first_loading: true,
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
        },
        // What the empty list says depends on why it is empty, with a way back to tickets
        emptyState() {
            if (this.search) {
                return {
                    // translators: %s is what the customer searched for
                    title: this.$t('No tickets match "%s"', this.search),
                    text: this.$t('Check the spelling or try other words.'),
                    action: this.$t('Clear search'),
                    onClick: () => {
                        this.search = '';
                    }
                };
            }

            if (this.filters.product_id) {
                return {
                    title: this.$t('No tickets for this product'),
                    action: this.$t('Show all products'),
                    onClick: () => {
                        this.filters.product_id = '';
                        this.pagination.current_page = 1;
                        this.fetchTickets();
                    }
                };
            }

            if (this.filterType === 'open' || this.filterType === 'closed') {
                return {
                    title: this.filterType === 'open' ? this.$t('No open tickets') : this.$t('No closed tickets'),
                    text: this.filterType === 'closed' ? this.$t('Tickets show up here once they are resolved.') : '',
                    action: this.$t('View all tickets'),
                    onClick: () => {
                        this.filterType = 'all';
                    }
                };
            }

            return {
                title: this.$t('You have not created any tickets yet'),
                text: this.$t('Need help with something? Create a ticket and our team will get back to you.'),
                action: this.$t('create_ticket_cta'),
                primary: true,
                onClick: () => {
                    this.$router.push({name: 'create_ticket'});
                }
            };
        }
    },
    methods: {
        fetchTickets() {
            this.loading = true
            this.$get('tickets', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                filter_type: this.filterType,
                search: this.search,
                filters: this.filters,
                sorting: this.sorting
            })
                .then(response => {
                    this.tickets = response.tickets.data
                    this.pagination.total = response.tickets.total;
                })
                .catch(errors => {
                    this.$handleErrors(errors);
                })
                .finally(() => {
                    this.loading = false;
                    this.first_loading = false;
                });
        },
        // A changed filter, sort or search (TicketFilters) starts again from the first page
        refetchFromFirstPage() {
            this.pagination.current_page = 1;
            this.fetchTickets();
        },
        /**
         * The support team replied last and the ticket is still open.
         * Informational replies never set last_agent_response, so they don't count.
         */
        isWaitingOnCustomer(row) {
            if (!row || row.status === 'closed' || !row.last_agent_response) {
                return false;
            }
            // Both are "Y-m-d H:i:s" in site time, so they compare as strings.
            return !row.last_customer_response || String(row.last_agent_response) > String(row.last_customer_response);
        },
        replyCountLabel(ticket) {
            const count = parseInt(ticket.response_count);
            // translators: %s: number of replies on the ticket
            return this.$_n('%s reply', '%s replies', count);
        },
        getStatusClass(status) {
            return status.toLowerCase()
        },

        getExcerpt(row) {
            let text = (row.preview_response) ? row.preview_response.content : row.content;
            if (!text) {
                return '';
            }
            // Drops the tags and decodes entities (&gt; shows as >). DOMParser runs no
            // scripts and loads nothing, and the result is text-interpolated.
            return new DOMParser().parseFromString(text, 'text/html').body.textContent || '';
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
    mounted() {
        if (this.appVars.customer_status !== 'inactive') {
            this.fetchTickets();
        }
    }
}
</script>
