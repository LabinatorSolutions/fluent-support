<template>
    <div v-loading="loading" class="fs_all_tickets">
        <div class="fs_tk_filters">
            <div class="fs_tk_filter">

            </div>
        </div>
        <el-table
            :data="tickets"
            stripe
            style="width: 100%">
            <el-table-column width="160" label="Customer">
                <template #default="scope">
                    <span v-if="scope.row.customer">{{scope.row.customer.full_name}}</span>
                    <span v-else>n/a</span>
                </template>
            </el-table-column>
            <el-table-column label="Title">
                <template #default="scope">
                    <router-link class="fs_tk_preview" :to="{name: 'view_ticket', params: { ticket_id: scope.row.id }}">
                        <strong>{{scope.row.title}}</strong>
                        <p class="fs_tk_preview_text">{{scope.row.excerpt}}</p>
                    </router-link>
                </template>
            </el-table-column>
            <el-table-column width="40" >
                <template #default="scope">
                    <span class="fs_thread_count">{{scope.row.response_count}}</span>
                </template>
            </el-table-column>
            <el-table-column width="100" label="Manager">
                <template #default="scope">
                    <span :title="scope.row.agent.full_name" v-if="scope.row.agent">{{scope.row.agent.first_name}}</span>
                    <span v-else>not assigned</span>
                </template>
            </el-table-column>
            <el-table-column width="80" label="Status">
                <template #default="scope">
                    <span class="fs_badge" :class="'fs_badge_'+scope.row.status">{{scope.row.status}}</span>
                </template>
            </el-table-column>
            <el-table-column
                prop="created_at"
                label="Date"
                width="180">
            </el-table-column>
        </el-table>
        <div class="fframe_pagination_wrapper">
            <pagination @fetch="fetchTickets()" :pagination="pagination" />
        </div>
    </div>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'
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
            order_by: 'created_at',
            order_type: 'DESC'
        }
    },
    methods: {
        fetchTickets() {
            this.loading = true;
            this.$get('tickets', {
                page: this.pagination.current_page,
                per_page: this.pagination.per_page,
                order_by: this.order_by,
                order_type: this.order_type
            })
                .then(response => {
                    this.tickets = response.tickets.data;
                    this.pagination.total = response.tickets.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                })
        }
    },
    mounted() {
        this.fetchTickets();
    }
}
</script>
