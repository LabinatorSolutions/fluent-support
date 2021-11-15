<template>
    <div class="fs_all_tickets">
        <div class="fs_tk_actions fs_tk_header">
            <div class="fs_tk_left">
                <div class="fs_button_groups">
                    <button class="fs_btn" :class="{ fs_btn_active: filter_type == 'all' }"
                            @click="filter_type = 'all'">All
                    </button>
                    <button class="fs_btn" :class="{ fs_btn_active: filter_type == 'open' }"
                            @click="filter_type = 'open'">Open
                    </button>
                    <button class="fs_btn" :class="{ fs_btn_active: filter_type == 'closed' }"
                            @click="filter_type = 'closed'">Closed
                    </button>
                </div>
            </div>
            <div class="fs_tk_right">
                <button @click="$router.push({ name: 'create_ticket' })" class="fs_btn fs_btn_success">
                    {{ $t('create_ticket_cta') }}
                </button>
            </div>
        </div>
        <div v-if="first_loading" style="padding: 20px; background: white; " class="fs_tk_body">
            <el-skeleton :rows="5" animated/>
        </div>
        <div v-loading="fetching" v-else class="fs_tk_body">
            <table class="fs_table fs_stripe">
                <thead>
                <tr>
                    <th>{{ $t('Conversation') }}</th>
                    <th></th>
                    <th>{{ $t('Status') }}</th>
                    <th>{{ $t('Date') }}</th>
                </tr>
                </thead>
                <tbody v-if="tickets.length">
                <tr v-for="ticket in tickets" :key="ticket.id">
                    <td>
                        <router-link class="fs_tk_preview"
                                     :to="{name: 'view_ticket', params: { ticket_id: ticket.id }}">
                            <strong>{{ ticket.title }}</strong>
                            <div class="prev_text_parent">
                                <p class="fs_tk_preview_text">{{ getExcerpt(ticket) }}</p>
                            </div>
                        </router-link>
                    </td>
                    <td class="fs_thread_count">
                        <span class="fs_thread_count">{{ ticket.response_count }}</span>
                    </td>
                    <td class="fs_tk_status">
                        <el-tag size="mini" :type="getStatus(ticket.status)" :effect="getEffect(ticket.status)">
                            {{ ticket.status }}
                        </el-tag>
                    </td>
                    <td class="fs_tk_date">
                        <span class="fs_tk_date">{{ ticket.human_date }}</span>
                    </td>
                </tr>
                </tbody>
                <tbody v-else>
                <tr>
                    <td colspan="4">
                        <p v-if="filter_type == 'all'" style="text-align: center;">Looks like you did not open any
                            support tickets yet</p>
                        <p v-else style="text-align: center;">No support tickets found for this filter</p>
                    </td>
                </tr>
                </tbody>
            </table>

            <div class="fst_pagi_wrapper">
                <div v-if="hasNextPage || hasPrevPage" class="fs_button_groups fs_pagi">
                    <button :disabled="!hasPrevPage" @click="pagiAction(-1)" class="fs_btn">&laquo; Prev</button>
                    <button :disabled="!hasNextPage" @click="pagiAction(1)" class="fs_btn">Next &raquo;</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import Pagination from '../../admin/Pieces/Pagination';

export default {
    name: 'tickets',
    components: {
        Pagination
    },
    data() {
        return {
            tickets: [],
            filter_type: 'all',
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            },
            fetching: false,
            first_loading: true,
            show_filters: true
        }
    },
    computed: {
        hasNextPage() {
            return this.pagination.total && this.pagination.total > (this.pagination.per_page * this.pagination.current_page);
        },
        hasPrevPage() {
            return this.pagination.total && this.pagination.current_page > 1;
        }
    },
    watch: {
        filter_type() {
            this.pagination.current_page = 1;
            this.fetchTickets();
        }
    },
    methods: {
        fetchTickets() {
            this.fetching = true;

            this.$get('tickets', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                filter_type: this.filter_type
            })
                .then(response => {
                    if (response.tickets && response.tickets.data) {
                        this.tickets = response.tickets.data;
                        this.pagination.total = response.tickets.total;
                    }
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.fetching = false;
                    this.first_loading = false;
                });
        },
        getStatus(status) {
            if (status == 'active') {
                return 'success';
            }

            if (status == 'closed') {
                return 'info';
            }

            return '';

        },
        getEffect(status) {
            return (status == 'active') ? 'dark' : 'plain';
        },
        gotToTicket(row) {
            this.$router.push({
                name: 'view_ticket',
                params: {ticket_id: row.id}
            });
        },
        getExcerpt(row) {

            let text = (row.preview_response) ? row.preview_response.content : row.content;

            if (!text) {
                return '';
            }
            return text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "");
        },
        pagiAction(number) {
            this.pagination.current_page += number;
            this.fetchTickets();
        }
    },
    mounted() {
        this.fetchTickets();
    }
}
</script>
