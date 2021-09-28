<template>
    <div class="fs_all_tickets">
        <div class="fs_tk_actions fs_tk_header">
            <div class="fs_tk_left">
                <el-radio-group v-model="filter_type">
                    <el-radio-button label="all">All</el-radio-button>
                    <el-radio-button label="open">Open</el-radio-button>
                    <el-radio-button label="closed">Closed</el-radio-button>
                </el-radio-group>
            </div>
            <div class="fs_tk_right">
                <el-button size="small" @click="$router.push({ name: 'create_ticket' })" type="success">Create a New Ticket</el-button>
            </div>
        </div>
        <div v-if="first_loading" style="padding: 20px; background: white; " class="fs_tk_body">
            <el-skeleton :rows="5" animated/>
        </div>
        <div v-else class="fs_tk_body">
            <el-table
                :data="tickets"
                stripe
                @row-click="gotToTicket"
                v-loading="fetching"
                style="width: 100%">
                <el-table-column min-width="200px" label="Conversation">
                    <template #default="scope">
                        <router-link class="fs_tk_preview"
                                     :to="{name: 'view_ticket', params: { ticket_id: scope.row.id }}">
                            <strong>{{ scope.row.title }}</strong>
                            <div class="prev_text_parent">
                                <p class="fs_tk_preview_text">{{ getExcerpt(scope.row) }}</p>
                            </div>
                        </router-link>
                    </template>
                </el-table-column>
                <el-table-column min-width="15">
                    <template #default="scope">
                        <span class="fs_thread_count">{{ scope.row.response_count }}</span>
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
            <div class="fst_pagi_wrapper">
                <pagination @fetch="fetchTickets()" :pagination="pagination"/>
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
                current_page: 0,
                total: 0
            },
            fetching: false,
            first_loading: true
        }
    },
    watch: {
        filter_type() {
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
        gotToTicket(row) {
            this.$router.push({
                name: 'view_ticket',
                params: {ticket_id: row.id}
            });
        },
        getExcerpt(row) {
            let text = row.content;
            if(!text) {
                return '';
            }
            return text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "");
        }
    },
    mounted() {
        this.fetchTickets();
    }
}
</script>
