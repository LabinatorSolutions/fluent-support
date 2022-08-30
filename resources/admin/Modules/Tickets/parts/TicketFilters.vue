<template>
    <div class="fs_tk_filters">
        <div class="fs_tk_filter">
            <label>{{$t('Status')}}</label>
            <el-radio-group @change="fetchTickets()" v-model="filters.status_type">
                <el-radio-button label="open">{{$t('Open')}}</el-radio-button>
                <el-radio-button label="active">{{$t('Active')}}</el-radio-button>
                <el-radio-button label="new">{{$t('New')}}</el-radio-button>
                <el-radio-button label="closed">{{$t('Closed')}}</el-radio-button>
                <el-radio-button label="all">{{$t('All')}}</el-radio-button>
            </el-radio-group>
        </div>
        <div v-if="appVars.mailboxes.length" class="fs_tk_filter">
            <label>{{$t('Inbox')}}</label>
            <el-select clearable filterable size="small" @change="fetchTickets()" v-model="filters.mailbox_id"
                       :placeholder="$t('All Inbox')">
                <el-option v-for="mailbox in appVars.mailboxes" :key="mailbox.id" :value="mailbox.id"
                           :label="mailbox.name"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>{{$t('Product')}}</label>
            <el-select clearable filterable size="small" @change="fetchTickets()" v-model="filters.product_id"
                       :placeholder="$t('All Products')">
                <el-option v-for="product in appVars.support_products" :key="product.id" :value="product.id"
                           :label="product.title"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>{{$t('Support Staff')}}</label>
            <el-select clearable filterable size="small" @change="fetchTickets()" v-model="filters.agent_id"
                       :placeholder="$t('All Support Staff')">
                <el-option value="unassigned" :label="$t('Un-Assigned')"></el-option>
                <el-option v-for="agent in appVars.support_agents" :key="agent.id" :value="agent.id"
                           :label="agent.full_name"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>{{$t('Priority (Admin)')}}</label>
            <el-select clearable size="small" @change="fetchTickets()" v-model="filters.priority"
                       :placeholder="$t('All')">
                <el-option v-for="(priority,priorityKey) in appVars.admin_priorities" :key="priorityKey"
                           :value="priorityKey" :label="priority"></el-option>
            </el-select>
        </div>
        <div class="fs_tk_filter">
            <label>{{$t('Priority (Customer)')}}</label>
            <el-select clearable size="small" @change="fetchTickets()" v-model="filters.client_priority"
                       :placeholder="$t('All')">
                <el-option v-for="(priority,priorityKey) in appVars.client_priorities" :key="priorityKey"
                           :value="priorityKey" :label="priority"></el-option>
            </el-select>
        </div>
        <div v-if="appVars.ticket_tags.length" class="fs_tk_filter">
            <label>{{$t('Tags')}}</label>
            <el-select
                @change="fetchTickets()"
                v-model="filters.ticket_tags"
                :placeholder="$t('Filter By Tags')"
                multiple
                :popper-append-to-body=true
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
            <label>{{$t('Search')}}</label>
            <el-input @keyup.enter="fetchTickets()" clearable @clear="fetchTickets()" size="small"
                      :placeholder="$t('Please input')" v-model="searchInput">
                <template #append>
                    <el-button @click="fetchTickets()" icon="Search"></el-button>
                </template>
            </el-input>
        </div>
        <div class="fs_tk_filter">
            <label>{{$t('Waiting for Reply')}}</label>
            <el-switch @change="maybeChangeWaitingReply()" active-value="yes" :inactive-value="false"
                       v-model="filters.waiting_for_reply"></el-switch>
        </div>
        <div class="fs_tk_filter">
            <el-button :type="(has_active_filter) ? 'danger' : 'default'" @click="resetFilters()"
                       size="small">{{$t('Reset Filters')}}
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
                id: this.$t('Ticket ID'),
                product_id: this.$t('Product ID'),
                priority: this.$t('Priority'),
                client_priority: this.$t('Client Priority'),
                title: this.$t('Title'),
                last_agent_response: this.$t('Last Agent Response'),
                last_customer_response: this.$t('Last Customer Response'),
                waiting_since: this.$t('Waiting Time'),
                response_count: this.$t('Response Count'),
                created_at: this.$t('Created At')
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
            return f.status_type != 'open' || f.product_id || f.mailbox_id || f.agent_id || f.priority || f.client_priority || f.waiting_for_reply || this.searchInput || f.ticket_tags?.length || f.filter_type;
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
