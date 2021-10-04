<template>
    <div class="fs_tk_filters">
        <div class="fs_tk_filter">
            <label>Status</label>
            <el-radio-group size="small" @change="fetchTickets()" v-model="filters.status_type">
                <el-radio-button label="open">Open</el-radio-button>
                <el-radio-button label="active">Active</el-radio-button>
                <el-radio-button label="new">New</el-radio-button>
                <el-radio-button label="closed">Closed</el-radio-button>
                <el-radio-button label="all">All</el-radio-button>
            </el-radio-group>
        </div>
        <div class="fs_tk_filter">
            <label>Product</label>
            <el-select clearable filterable size="small" @change="fetchTickets()" v-model="filters.product_id"
                       placeholder="All Products">
                <el-option v-for="product in appVars.support_products" :key="product.id" :value="product.id"
                           :label="product.title"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>Support Staff</label>
            <el-select clearable filterable size="small" @change="fetchTickets()" v-model="filters.agent_id"
                       placeholder="All Support Staff">
                <el-option value="unassigned" label="Un-Assigned"></el-option>
                <el-option v-for="agent in appVars.support_agents" :key="agent.id" :value="agent.id"
                           :label="agent.full_name"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>Priority (Admin)</label>
            <el-select clearable size="small" @change="fetchTickets()" v-model="filters.priority"
                       placeholder="All">
                <el-option v-for="(priority,priorityKey) in appVars.admin_priorities" :key="priorityKey"
                           :value="priorityKey" :label="priority"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>Priority (Customer)</label>
            <el-select clearable size="small" @change="fetchTickets()" v-model="filters.client_priority"
                       placeholder="All">
                <el-option v-for="(priority,priorityKey) in appVars.client_priorities" :key="priorityKey"
                           :value="priorityKey" :label="priority"></el-option>
            </el-select>
        </div>
        <div v-if="appVars.ticket_tags.length" class="fs_tk_filter">
            <label>Tags</label>
            <el-select
                @change="fetchTickets()"
                v-model="filters.ticket_tags"
                placeholder="Filter By Tags"
                multiple
                popper-append-to-body="true"
                size="small"
                collapse-tags
            >
                <el-option v-for="tag in appVars.ticket_tags"
                           :key="tag.id"
                           :label="tag.title"
                           :value="tag.id"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>Waiting for Reply</label>
            <el-switch @change="maybeChangeWaitingReply()" active-value="yes" :inactive-value="false"
                       v-model="filters.waiting_for_reply"></el-switch>
        </div>
        <div class="fs_tk_filter">
            <label>Search</label>
            <el-input @keyup.enter="fetchTickets()" clearable @clear="fetchTickets()" size="mini"
                      placeholder="Please input" v-model="searchInput">
                <template #append>
                    <el-button @click="fetchTickets()" icon="el-icon-search"></el-button>
                </template>
            </el-input>
        </div>
        <div class="fs_tk_filter">
            <el-button :type="(has_active_filter) ? 'danger' : 'default'" @click="resetFilters()"
                       size="small">Reset Filters
            </el-button>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'TicketFilters',
    emits: ['fetchTickets', 'searchChange'],
    props: ['filters', 'resetFilters', 'search'],
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
            searchInput: this.search
        }
    },
    watch: {
        searchInput(value) {
            this.$emit('searchChange', value);
        }
    },
    computed: {
        has_active_filter() {
            const f = this.filters;
            return f.status_type != 'open' || f.product_id || f.agent_id || f.priority || f.client_priority || f.waiting_for_reply || this.searchInput || f.ticket_tags?.length;
        }
    },
    methods: {
        fetchTickets() {
            this.$emit('fetchTickets');
        },
        maybeChangeWaitingReply() {
            if (this.filters.waiting_for_reply == 'yes') {
                if (this.filters.status_type == 'new' || this.filters.status_type == 'active') {
                    this.filters.status_type;
                }
                else{
                    this.filters.status_type = 'open';
                }
            }
            this.fetchTickets();
        }
    }
}
</script>
