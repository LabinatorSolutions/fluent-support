<template>
    <div class="fs_box_actions fs_ticket_orders">
        <el-select filterable @change="fetchTickets()" v-model="order_by" size="mini">
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
</template>

<script type="text/babel">
export default {
    name: 'TicketFilters',
    emits: ['fetchTickets'],
    data() {
        return {
            filterColumns: {
                id: 'Ticket ID',
                product_id: 'Product ID',
                priority: 'Priority',
                client_priority: 'Client Priority',
                title: 'Title',
                last_agent_response: 'Last Agent Response',
                last_customer_response: 'Last Customer Response',
                waiting_since: 'Waiting Time',
                response_count: 'Response Count',
                created_at: 'Created At'
            },
            products: this.appVars.support_products,
            agents: this.appVars.support_agents,
        }
    },
    methods: {
        fetchTickets() {
            this.trigger('fetchTickets');
        }
    }
}
</script>
