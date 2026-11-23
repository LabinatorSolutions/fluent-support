<template>
    <div class="fs_box_header">
        <div class="fs_box_head fs_status_tabs_wrapper">
            <!-- Status Tabs - Hidden when search is active or advanced filter is open -->
            <div v-if="!advancedOpen" class="fs_status_tabs">
                <div class="fs_segmented_control">
                    <button
                        v-for="(status, statusKey) in ticket_statuses_group"
                        :key="statusKey"
                        @click="$emit('status', statusKey)"
                        :class="['fs_segment_button', { 'fs_segment_active': filters.status_type === statusKey }]"
                    >
                        {{ appVars.ticket_statuses[statusKey] || $t(statusKey) }}
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="fs_filter_controls">
                <!-- Left Section: Status bar and Search -->
                <div class="fs_filter_left_section" :class="{ 'fs_search_active': searchActive }">
                    <!-- Advanced Filter Button - Hidden when search is active -->
                    <div v-if="!searchActive" class="fs_tk_filter fs_advanced_button">
                        <el-switch
                            @change="$emit('toggle-advanced', $event)"
                            :model-value="advancedOpen"
                            :active-text="$t('Advanced filter')"
                            inactive-text=""
                        />
                    </div>

                    <!-- Search Button - Disabled when advanced filter is open or search is active -->
                    <div class="fs_tk_filter fs_table_search_field">
                        <el-button v-tip="$t('Search')" @click="openSearch()" class="fs_outline_btn fs_search_button" :disabled="advancedOpen">
                            <IconPack iconKey="search" :width="20" :height="20" />
                        </el-button>
                    </div>
                </div>

                <!-- Right Section: Filter, Folder, and Sort buttons -->
                <div class="fs_filter_right_section">
                    <!-- Filter Button with Custom Filter Component - Disabled when advanced filter is open -->
                    <CustomTicketFilters ref="customTicketFiltersRef" @apply-filter="handleCustomFilter" :disabled="advancedOpen" />

                    <!-- Add this after the Filter Button -->
                    <el-button v-tip="$t('Saved Filters')"
                        @click="$emit('saved-filters')"
                        class="fs_outline_btn"
                        v-if="has_pro"
                    >
                        <IconPack iconKey="labelSearch" :width="20" :height="20" />
                    </el-button>

                    <el-popover :persistent="false"
                        trigger="click"
                        placement="bottom-start"
                        width="300"
                        popper-class="fs_popover"
                    >
                        <div class="fs_popover_item">
                            <label class="fs_popover_title">{{ $t('Sort By') }}</label>
                            <div class="fs_radio_blocks">
                                <el-radio-group
                                    @change="$emit('fetch')"
                                    v-model="order_by"
                                >
                                    <el-radio v-for="(column, columnName) in filterColumns" :key="columnName"
                                              :value="columnName">
                                        {{ column }}
                                    </el-radio>
                                </el-radio-group>
                            </div>
                            <hr/>
                            <el-radio-group
                                class="fs_switch_button"
                                size="small"
                                v-model="order_type"
                                @change="$emit('fetch')"
                            >
                                <el-radio-button value="ASC">{{ $t('Ascending') }}</el-radio-button>
                                <el-radio-button value="DESC">{{ $t('Descending') }}</el-radio-button>
                            </el-radio-group>
                        </div>
                        <template #reference>
                        <span>
                            <el-button v-tip="$t('Sort')" class="fs_outline_btn">
                                <IconPack iconKey="sort" :width="20" :height="20" />
                            </el-button>
                        </span>
                        </template>
                    </el-popover>

                    <el-popover :persistent="false"
                        trigger="click"
                        placement="bottom-end"
                        width="280"
                        popper-class="fs_popover fs_display_settings_popover"
                    >
                        <div class="fs_popover_item">
                            <div class="fs_popover_header">
                                <label class="fs_popover_title">{{ $t('Layout Type') }}</label>
                            </div>
                            <div class="fs_radio_blocks fs_layout_options">
                                <el-radio-group v-model="ticketLayout" @change="setTicketLayout">
                                    <el-radio value="default">{{ $t('Default') }} <span class="fs-radio__sublabel">{{$t('(Standard spacing)')}}</span></el-radio>
                                    <el-radio value="compact">{{ $t('Compact') }} <span class="fs-radio__sublabel">{{$t('(Maximum visibility)')}}</span></el-radio>
                                    <el-radio value="grid">{{ $t('Grid View') }} <span class="fs-radio__sublabel">{{$t('(Table columns)')}}</span></el-radio>
                                </el-radio-group>
                            </div>
                            <hr/>
                            <div class="fs_popover_header">
                                <label class="fs_popover_title">{{ $t('Display Settings') }}</label>
                                <el-button @click="resetActiveVisibility()" link size="small">
                                    <IconPack iconKey="refresh" :width="20" :height="20" />
                                </el-button>

                            </div>
                            <div class="fs_checkbox_list">
                                <el-checkbox
                                    v-for="field in activeVisibilityOptions"
                                    :key="field.key"
                                    v-model="activeVisibility[field.key]"
                                    @change="saveActiveVisibility()"
                                >
                                    {{ field.label }}
                                </el-checkbox>
                            </div>
                        </div>
                        <template #reference>
                            <span>
                                <el-button v-tip="$t('Display Settings')" class="fs_outline_btn">
                                    <IconPack iconKey="layoutView" :width="20" :height="20" />
                                </el-button>
                            </span>
                        </template>
                    </el-popover>
                </div>
            </div>
        </div>
        <div v-if="searchActive || search.length > 0" class="fs_search_bar_section">
            <div class="fs_search_bar_container">
                <el-input
                    @keyup.enter="searchTickets()"
                    :placeholder="$t('Search')"
                    v-model="search"
                    class="fs_text_input fs_table_search_input"
                    ref="searchInput"
                    :disabled="advancedOpen"
                    clearable
                >
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>
                 <el-button @click="closeSearch()" class="fs_text_btn" text>
                     {{ $t('Cancel') }}
                 </el-button>
            </div>
        </div>
    </div>

    <div class="fs_active_filters_section" v-if="activeFilters && Object.keys(activeFilters).length">
        <div class="fs_active_filters_container">
            <div
                v-for="(filter, key) in activeFilters"
                :key="key"
                class="fs_filter_tag"
                :class="{ 'fs_filter_tag_with_select': key === 'product_id' }"
            >
                <div class="fs_filter_icon_name">
                    <IconPack :iconKey="getFilterIcon(key)" :width="20" :height="20" class="fs_filter_icon" />
                    {{ getFilterLabel(key) }}
                </div>
                <div class="fs_filter_value" v-if="key !== 'product_id'">
                    <span :title="getFilterDisplayValue(key, filter)">{{ getFilterDisplayValue(key, filter) }}</span>
                </div>
                <!-- Product dropdown -->
                <div class="fs_filter_value" v-else>
                    <el-select
                        v-model="filters.product_id"
                        multiple
                        filterable
                        collapse-tags
                        collapse-tags-tooltip
                        :max-collapse-tags="2"
                        :placeholder="$t('Select products')"
                        class="fs_product_filter_select"
                        @change="handleProductFilterChange"
                    >
                        <el-option
                            v-for="product in appVars?.support_products || []"
                            :key="product.id"
                            :label="product.title"
                            :value="product.id"
                        />
                    </el-select>
                </div>
                <div class="fs_filter_close" @click="removeFilter(key)">
                    <svg class="fs_icon_secondary" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10.0001 8.93906L13.7126 5.22656L14.7731 6.28706L11.0606 9.99956L14.7731 13.7121L13.7126 14.7726L10.0001 11.0601L6.28755 14.7726L5.22705 13.7121L8.93955 9.99956L5.22705 6.28706L6.28755 5.22656L10.0001 8.93906Z" fill="currentColor"/>
                    </svg>
                </div>
            </div>
    </div>
        <div class="fs_filter_actions">
            <el-button
                @click="clearAllFilters()"
                link
                class="fs_reset_filter_btn"
            >
                {{ $t('Reset Filter') }}
            </el-button>
        </div>
    </div>
</template>

<script type="text/babel">
import CustomTicketFilters from './_TicketFilters';
import IconPack from '@/admin/Components/IconPack.vue';
import { mapState, mapActions, mapWritableState } from 'pinia';
import { useTicketListStore } from '@/admin/stores/ticketList.store';

/**
 * The list's filter bar: status tabs, the advanced filter switch, search, the
 * filter menu, saved filters, sort and display settings, then the search box
 * and the chips of the active filters. Changes the list store and emits fetch;
 * AllTickets.vue reloads.
 */
export default {
    name: 'TicketListFilterBar',
    components: { CustomTicketFilters, IconPack },
    props: ['advancedOpen'],
    emits: ['fetch', 'status', 'toggle-advanced', 'saved-filters'],
    computed: {
        ...mapState(useTicketListStore, ['ticket_statuses_group', 'activeFilters', 'pagination']),
        ...mapWritableState(useTicketListStore, [
            'filters',
            'search',
            'order_by',
            'order_type',
            'searchActive',
            'fieldVisibility',
            'gridFieldVisibility',
            'ticketLayout',
        ]),
        filterColumns() {
            return {
                id: this.$t("Ticket ID"),
                product_id: this.$t("Product ID"),
                priority: this.$t("Priority"),
                client_priority: this.$t("Client Priority"),
                title: this.$t("Title"),
                last_agent_response: this.$t("Last Agent Response"),
                last_customer_response: this.$t("Last Customer Response"),
                waiting_since: this.$t("Waiting Time"),
                response_count: this.$t("Response Count"),
                created_at: this.$t("Created At"),
            };
        },
        fieldVisibilityOptions() {
            return [
                { key: 'title', label: this.$t('Ticket Title') },
                { key: 'author', label: this.$t('Author Name') },
                { key: 'tags', label: this.$t('Tags') },
                { key: 'source', label: this.$t('Source') },
                { key: 'description', label: this.$t('Description') },
                { key: 'response_count', label: this.$t('Response Count') },
                { key: 'product', label: this.$t('Product') },
                { key: 'mailbox', label: this.$t('Mailbox') },
                { key: 'agent', label: this.$t('Assigned Agent') },
                { key: 'status', label: this.$t('Status') },
                { key: 'client_priority', label: this.$t('Client Priority') },
                { key: 'waiting_time', label: this.$t('Waiting Time') },
            ];
        },
        gridVisibilityOptions() {
            return [
                { key: 'agent', label: this.$t('Assigned Agent') },
                { key: 'tags', label: this.$t('Tags') },
                { key: 'status', label: this.$t('Status') },
                { key: 'response_count', label: this.$t('Response Count') },
                { key: 'product', label: this.$t('Product') },
                { key: 'mailbox', label: this.$t('Mailbox') },
                { key: 'client_priority', label: this.$t('Client Priority') },
                { key: 'waiting_time', label: this.$t('Waiting Time') },
                { key: 'author', label: this.$t('Author Name') },
                { key: 'description', label: this.$t('Description') },
                { key: 'source', label: this.$t('Source') },
            ];
        },
        activeVisibility() {
            return this.ticketLayout === 'grid' ? this.gridFieldVisibility : this.fieldVisibility;
        },
        activeVisibilityOptions() {
            return this.ticketLayout === 'grid' ? this.gridVisibilityOptions : this.fieldVisibilityOptions;
        }
    },
    methods: {
        ...mapActions(useTicketListStore, {
            storeClearAllFilters: 'clearAllFilters',
            storeHandleCustomFilter: 'handleCustomFilter',
        }),

        openSearch() {
            this.searchActive = true;
            this.$nextTick(() => {
                if (this.$refs.searchInput) {
                    this.$refs.searchInput.focus();
                }
            });
        },

        searchTickets() {
            this.pagination.current_page = 1;
            this.$emit('fetch');
        },

        closeSearch() {
            this.searchActive = false;
            this.search = "";
            this.pagination.current_page = 1;
            this.$emit('fetch');
        },

        handleCustomFilter(filterType, filterValue) {
            this.storeHandleCustomFilter(filterType, filterValue);
            this.$emit('fetch');
        },

        handleProductFilterChange() {
            if (this.$refs.customTicketFiltersRef && this.$refs.customTicketFiltersRef.updateFilterSelection) {
                this.$refs.customTicketFiltersRef.updateFilterSelection('product_id', this.filters.product_id);
            }
            this.$emit('fetch');
        },

        removeFilter(filterKey) {
            if (filterKey === 'status_type') {
                this.filters.status_type = 'open';
            } else if (Array.isArray(this.filters[filterKey])) {
                this.filters[filterKey] = [];
            } else {
                delete this.filters[filterKey];
            }
            if (this.$refs.customTicketFiltersRef && this.$refs.customTicketFiltersRef.clearFilterSelection) {
                this.$refs.customTicketFiltersRef.clearFilterSelection(filterKey);
            }

            if (filterKey === 'agent_id' && this.clearRouteFilters(['agent_id', 'watcher'])) {
                return;
            }

            if (filterKey === 'product_id' && this.clearRouteFilters(['product_id'])) {
                return;
            }

            this.$emit('fetch');
        },

        /**
         * agent_id and watcher are mirrored in the URL, and the sidebar highlight is
         * derived from the URL alone. Clearing them from the store without updating the
         * route leaves the sidebar pointing at "My Tickets" over unfiltered data, so drop
         * them from the route instead and let the $route watchers sync the store + refetch.
         *
         * Returns true when a navigation was pushed, meaning the caller must not refetch.
         */
        clearRouteFilters(routeKeys) {
            const query = {...this.$route.query};
            const hasRouteFilter = routeKeys.some(key => key in query);

            // _TicketsMenu persists the sidebar query here and TicketsView replays it into
            // the URL on the next navigation, which would silently re-apply the filter.
            this.$removeData('routesData');

            if (!hasRouteFilter) {
                return false;
            }

            routeKeys.forEach(key => delete query[key]);
            this.$router.push({name: 'tickets', query});

            return true;
        },

        clearAllFilters() {
            this.storeClearAllFilters();
            if (this.$refs.customTicketFiltersRef) {
                this.$refs.customTicketFiltersRef.clearAllSelections();
            }
            this.removeData('tickets_filter');
            this.removeData('tickets_pref');

            if (this.clearRouteFilters(['agent_id', 'watcher', 'product_id'])) {
                return;
            }

            this.$emit('fetch');
        },

        removeData(key) {
            let existingData = this.$getData('__fluentsupport_data');
            if (!existingData) {
                return;
            }
            existingData = JSON.parse(existingData);
            delete existingData[key];
            this.$saveData('__fluentsupport_data', JSON.stringify(existingData));
        },

        getFilterLabel(key) {
            const labels = {
                status_type: this.$t('Status'),
                priority: this.$t('Priority'),
                client_priority: this.$t('Client Priority'),
                agent_id: this.$t('Agent'),
                product_id: this.$t('Product'),
                mailbox_id: this.$t('Mailbox'),
                ticket_tags: this.$t('Tags'),
                agent_group: this.$t('Agent Group'),
                waiting_for_reply: this.$t('Waiting For Reply')
            };
            return labels[key] || key;
        },

        getFilterDisplayValue(key, value) {
            if (!value) return '';

            if (key === 'status_type') {
                return (this.appVars.ticket_statuses || {})[value] || this.$t(value);
            }
            if (key === 'priority') {
                if (Array.isArray(value)) {
                    return value.map(v => this.appVars?.admin_priorities?.[v] || v).join(', ');
                }
                return this.appVars?.admin_priorities?.[value] || value;
            }
            if (key === 'client_priority') {
                if (Array.isArray(value)) {
                    return value.map(v => this.appVars?.client_priorities?.[v] || v).join(', ');
                }
                return this.appVars?.client_priorities?.[value] || value;
            }
            if (key === 'agent_id') {
                if (Array.isArray(value)) {
                    return value.map(v => {
                        if (v === 'unassigned') return this.$t('Un-Assigned');
                        const agent = this.appVars?.support_agents?.find(a => a.id == v);
                        return agent ? agent.full_name : v;
                    }).join(', ');
                }
                if (value === 'unassigned') return this.$t('Un-Assigned');
                const agent = this.appVars?.support_agents?.find(a => a.id == value);
                return agent ? agent.full_name : value;
            }
            if (key === 'product_id' && Array.isArray(value)) {
                return value.map(id => {
                    const product = this.appVars?.support_products?.find(p => p.id == id);
                    return product ? product.title : id;
                }).join(', ');
            }
            if (key === 'mailbox_id') {
                if (Array.isArray(value)) {
                    return value.map(v => {
                        const mailbox = this.appVars?.mailboxes?.find(m => m.id == v);
                        return mailbox ? mailbox.name : v;
                    }).join(', ');
                }
                const mailbox = this.appVars?.mailboxes?.find(m => m.id == value);
                return mailbox ? mailbox.name : value;
            }
            if (key === 'ticket_tags' && Array.isArray(value)) {
                return value.map(id => {
                    const tag = this.appVars?.ticket_tags?.find(t => t.id == id);
                    return tag ? tag.title : id;
                }).join(', ');
            }
            if (key === 'agent_group' && Array.isArray(value)) {
                return value.map(id => {
                    const group = this.appVars?.agent_groups?.find(g => g.id == id);
                    return group ? group.title : id;
                }).join(', ');
            }
            if (key === 'waiting_for_reply') {
                return value === 'yes' ? this.$t('Yes') : this.$t('No');
            }
            return value;
        },

        getFilterIcon(filterKey) {
            const iconMap = {
                'mailbox_id': 'filtersBusinessBox',
                'product_id': 'filtersProduct',
                'agent_id': 'filtersAgentIcon',
                'agent_group': 'agentGroupIcon',
                'priority': 'filtersAdminPriority',
                'client_priority': 'filtersCustomerPriority',
                'ticket_tags': 'filtersTagIcon',
                'status_type': 'circleCheck'
            };
            return iconMap[filterKey] || 'filter';
        },

        setTicketLayout(layout) {
            this.$saveData('tickets_layout', layout);
        },

        saveFieldVisibility() {
            this.$saveData('tickets_field_visibility', this.fieldVisibility);
        },

        saveGridFieldVisibility() {
            this.$saveData('tickets_grid_field_visibility', this.gridFieldVisibility);
        },

        saveActiveVisibility() {
            if (this.ticketLayout === 'grid') {
                this.saveGridFieldVisibility();
            } else {
                this.saveFieldVisibility();
            }
        },

        resetFieldVisibility() {
            const store = useTicketListStore();
            store.fieldVisibility = {
                title: true,
                author: true,
                tags: true,
                source: true,
                description: true,
                response_count: true,
                product: true,
                mailbox: true,
                agent: true,
                status: true,
                client_priority: true,
                waiting_time: true
            };
            this.saveFieldVisibility();
        },

        resetGridFieldVisibility() {
            const store = useTicketListStore();
            store.gridFieldVisibility = {
                title: true,
                author: true,
                tags: true,
                source: true,
                description: true,
                response_count: true,
                product: true,
                mailbox: false,
                agent: true,
                status: false,
                client_priority: true,
                waiting_time: true,
            };
            this.saveGridFieldVisibility();
        },

        resetActiveVisibility() {
            if (this.ticketLayout === 'grid') {
                this.resetGridFieldVisibility();
            } else {
                this.resetFieldVisibility();
            }
        }
    }
};
</script>
