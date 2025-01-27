<template>
    <div class="fs_section_one">
        <div class="status-filter">
            <div class="button-groups">
                <button
                    v-for="status in statusOptions"
                    :key="status.value"
                    :class="['status-btn', { active: localStatusFilter === status.value }]"
                    @click="updateStatusFilter(status.value)"
                >
                    {{ status.label }}
                </button>
            </div>
        </div>

    </div>
</template>

<script>
import { debounce } from 'lodash';

export default {
    name: 'TicketFilters',
    props: {
        filters: { type: Object, required: true },
        sorting: { type: Object, required: true },
        sortingColumns: { type: Array, required: true },
        search: { type: String, default: '' },
        statusFilter: { type: String, default: 'all' },
        appVars: { type: Object, required: true }
    },
    data() {
        return {
            localSearchQuery: this.search,
            localStatusFilter: this.statusFilter
        };
    },
    computed: {
        statusOptions() {
            return [
                { label: this.$t('All'), value: 'all' },
                { label: this.$t('Open'), value: 'open' },
                { label: this.$t('Closed'), value: 'closed' }
            ];
        }
    },
    created() {
        // Debounce the fetchTickets method with a 300ms delay
        this.debouncedFetchTickets = debounce(this.fetchTickets, 300);
    },
    methods: {
        updateStatusFilter(status) {
            this.localStatusFilter = status;
            this.$emit('update:statusFilter', status);
            this.fetchTickets();
        },
        fetchTickets() {
            this.$emit('fetch-tickets', {
                search: this.localSearchQuery,
                statusFilter: this.localStatusFilter
            });
        },
        onSearchInput() {
            this.debouncedFetchTickets();
        }
    },
    watch: {
        localSearchQuery(newVal) {
            this.$emit('update:search', newVal);
        }
    }
};
</script>
