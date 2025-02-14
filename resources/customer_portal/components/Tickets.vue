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
                    <el-table-column prop="human_date" label="Date" class-name="date-cell" width="196"/>
                    <el-table-column prop="status" label="Status" class-name="fs_status_cell" width="130">
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
.fs_ticket_wrapper {
    padding: 20px;
    background: #f8fafc;
    min-height: 100vh;

    .fs_tickets_container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(225, 228, 234, 1);
        max-width: 1200px;
        margin: 0 auto;

        .fs_tickets_header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #E5E7EB;

            label {
                font-size: 20px;
                font-weight: 500;
                line-height: 28px;
                text-align: left;
                text-underline-position: from-font;
                text-decoration-skip-ink: none;
                margin: 0;
                color: #111827;
            }

            .fs_create_ticket_btn {
                background: #18181B;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 8px 16px;
                font-size: 14px;
                font-weight: 500;
                height: auto;
                display: flex;
                align-items: center;
                gap: 8px;

                &:hover {
                    background: #27272A;
                }
            }
        }

        .fs_filters_section {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 20px 0px 20px;

            .fs_filter_one {
                display: flex;
                gap: 10px;
                align-items: center;

                .fs_product_filter {
                    min-width: 180px;

                    .el-select__wrapper {
                        border-radius: 8px;
                    }
                }
            }

            .fs_filter_buttons {
                display: flex;
                gap: 4px;
                padding: 4px;
                background: rgba(245, 247, 250, 1);
                border-radius: 10px;

                .filter-btn {
                    padding: 6px 16px;
                    border: none;
                    background: transparent;
                    color: #6B7280;
                    font-size: 14px;
                    font-weight: 500;
                    border-radius: 6px;
                    cursor: pointer;
                    min-width: 78px;

                    &.active {
                        background: white;
                        color: #111827;
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                    }
                }
            }

            .fs_filter_controls {
                .fs_product_select {
                    width: 200px;
                    font-size: 14px;
                }

                .fs_search_input {
                    flex: 1;
                    font-size: 14px;
                    min-width: 200px;

                    .el-input__wrapper {
                        border-radius: 8px;
                    }
                }
            }
        }

        .fs_tickets_table {
            background: rgba(255, 255, 255, 1);
            overflow: hidden;
            padding: 20px 16px 16px;

            .el-table {

                overflow: hidden;

                .el-table__header {
                    table-layout: auto;
                    thead {
                        th {
                            &:first-child {
                                border-top-left-radius: 8px;
                                border-bottom-left-radius: 8px;
                                overflow: hidden; // Ensure the borders render correctly
                            }

                            &:last-child {
                                border-top-right-radius: 8px;
                                border-bottom-right-radius: 8px;
                                overflow: hidden; // Ensure the borders render correctly
                            }
                        }
                    }

                    th {
                        background: rgba(245, 247, 250, 1);
                        color: rgba(14, 18, 27, 1);
                        font-weight: 500;
                        font-size: 14px;
                        line-height: 20px;
                    }
                }

                .el-table__body {
                    .ticket-row {
                        background: white;

                        &:hover {
                            background: rgba(245, 247, 250, 1);
                        }

                        td {
                            padding: 16px 24px;
                            border-bottom: 1px solid #F3F4F6;

                            &.conversation-cell {
                                .fs_ticket_conversation {
                                    text-decoration: none;

                                    .fs_ticket_title {
                                        display: flex;
                                        align-items: center;
                                        gap: 8px;
                                        margin-bottom: 4px;

                                        strong {
                                            font-size: 14px;
                                            font-weight: 500;
                                            line-height: 20px;
                                            letter-spacing: -0.006em;
                                            text-align: left;
                                            text-underline-position: from-font;
                                            text-decoration-skip-ink: none;
                                            margin: 0;
                                        }

                                        .fs_response_count {
                                            background: rgba(225, 228, 234, 1);
                                            color: rgba(34, 37, 48, 1);
                                            border-radius: 50%;
                                            width: 16px;
                                            height: 16px;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            font-size: 11px;
                                            font-weight: 500;
                                            line-height: 12px;
                                            letter-spacing: 0.02em;
                                            text-align: center;
                                            text-underline-position: from-font;
                                            text-decoration-skip-ink: none;
                                        }
                                    }

                                    .fs_ticket_preview {
                                        color: #6B7280;
                                        font-size: 12px;
                                        font-weight: 400;
                                        text-align: left;
                                        text-underline-position: from-font;
                                        text-decoration-skip-ink: none;
                                        margin: 0;
                                        max-height: 23px;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                    }
                                }
                            }

                            &.date-cell {
                                font-size: 14px;
                                font-weight: 400;
                                line-height: 20px;
                                text-align: left;
                                color: rgba(14, 18, 27, 1);
                                width: 20%;
                            }

                            &.fs_status_cell {
                                width: 20%;
                            }

                            .fs_status_badge {
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                                padding: 4px 8px;
                                border-radius: 6px;
                                border: 1px solid rgba(225, 228, 234, 1);
                                font-size: 12px;
                                font-weight: 500;
                                line-height: 16px;
                                text-align: left;
                                color: rgba(82, 88, 102, 1);

                                &.active {
                                    .fs_status_dot {
                                        color: rgba(31, 193, 107, 1);
                                    }
                                }

                                &.new {
                                    .fs_status_dot {
                                        color: #e76b6b;
                                    }
                                }

                                .fs_status_dot {
                                    width: 6px;
                                    height: 6px;
                                    border-radius: 50%;
                                    background: currentColor;
                                }
                            }
                        }
                    }
                }
            }
        }

        .fs_pagination_section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 4px 0;

            .fs_page_info {
                color: #6B7280;
                font-size: 14px;
                font-weight: 500;
            }

            .fs_pagination_controls {
                display: flex;
                align-items: center;
                gap: 16px;

                .fs_per_page_select {
                    width: 120px;
                    font-size: 14px;
                }

                .fs_page_numbers {
                    display: flex;
                    gap: 4px;

                    .fs_page_btn,
                    .fs_page_number {
                        min-width: 32px;
                        height: 32px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border: 1px solid #E5E7EB;
                        background: white;
                        border-radius: 6px;
                        color: #6B7280;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 500;

                        &.active {
                            background: #F3F4F6;
                            color: #111827;
                            border-color: #E5E7EB;
                        }

                        &:disabled {
                            opacity: 0.5;
                            cursor: not-allowed;
                        }
                    }

                    .fs_page_ellipsis {
                        display: flex;
                        align-items: center;
                        padding: 0 8px;
                        color: #6B7280;
                        font-size: 14px;
                    }
                }
            }
        }
    }
}
</style>
