<template>
    <div class="fs_customers">
        <div class="fs_customers_page_head">
            <h1 class="fs_customers_page_title">{{ $t('Customers') }}</h1>
            <el-button @click="showAddCustomerModal({})" class="fs_filled_btn" icon="Plus">
                {{ $t('Add Customer') }}
            </el-button>
        </div>

        <div class="fs_customers_card">
            <div class="fs_customers_card_head">
                <div class="fs_customers_views" role="tablist">
                    <button
                        v-for="(label, key) in views"
                        :key="key"
                        type="button"
                        role="tab"
                        :aria-selected="view === key"
                        :class="['fs_customers_view', { 'fs_customers_view_active': view === key }]"
                        @click="setView(key)"
                    >
                        {{ label }}
                    </button>
                </div>

                <div class="fs_customers_tools">
                    <el-tooltip :content="$t('Search')" placement="top">
                        <el-button
                            class="fs_customers_icon_btn"
                            :class="{ 'is-active': searchOpen }"
                            :aria-label="$t('Search')"
                            @click="toggleSearch()"
                        >
                            <el-icon><Search /></el-icon>
                        </el-button>
                    </el-tooltip>

                    <el-popover trigger="click" placement="bottom-end" :width="220" popper-class="fs_customers_popover">
                        <h4 class="fs_customers_popover_title">{{ $t('Columns') }}</h4>
                        <el-checkbox-group v-model="visibleColumns" class="fs_customers_popover_list" @change="saveColumns()">
                            <el-checkbox v-for="(label, key) in toggleableColumns" :key="key" :value="key">
                                {{ label }}
                            </el-checkbox>
                        </el-checkbox-group>
                        <template #reference>
                            <span>
                                <el-tooltip :content="$t('Toggle columns')" placement="top">
                                    <el-button class="fs_customers_icon_btn" :aria-label="$t('Toggle columns')">
                                        <DynamicIcon name="Columns"/>
                                    </el-button>
                                </el-tooltip>
                            </span>
                        </template>
                    </el-popover>

                    <el-popover trigger="click" placement="bottom-end" :width="240" popper-class="fs_customers_popover">
                        <h4 class="fs_customers_popover_title">{{ $t('Sort By') }}</h4>
                        <el-radio-group v-model="sortBy" class="fs_customers_popover_list" @change="applySort()">
                            <el-radio v-for="(label, key) in sortOptions" :key="key" :value="key">{{ label }}</el-radio>
                        </el-radio-group>
                        <div class="fs_customers_popover_divider"></div>
                        <el-radio-group v-model="sortType" size="small" @change="applySort()">
                            <el-radio-button value="ASC">{{ $t('Ascending') }}</el-radio-button>
                            <el-radio-button value="DESC">{{ $t('Descending') }}</el-radio-button>
                        </el-radio-group>
                        <template #reference>
                            <span>
                                <el-tooltip :content="$t('Sort')" placement="top">
                                    <el-button class="fs_customers_icon_btn" :aria-label="$t('Sort')">
                                        <DynamicIcon name="ArrowUpDown"/>
                                    </el-button>
                                </el-tooltip>
                            </span>
                        </template>
                    </el-popover>
                </div>
            </div>

            <div v-if="searchOpen" class="fs_customers_search">
                <div class="fs_customers_search_field">
                    <customer-searcher
                        ref="searcher"
                        v-model="searchInput"
                        @search="runSearch"
                    />
                    <customer-search-summary
                        v-if="searchMeta"
                        :meta="searchMeta"
                        :can-rank="canRank"
                        @best-match="sortByBestMatch()"
                    />
                    <p v-else class="fs_customers_search_hint">{{ $t('Press Enter to search. Type / to search one field, or ? for tips.') }}</p>
                </div>
                <el-button text @click="closeSearch()">{{ $t('Cancel') }}</el-button>
            </div>

            <div v-if="selectedCustomers.length" class="fs_customers_bulk_bar">
                <span class="fs_customers_bulk_count">
                    <!-- translators: %s: number of selected customers -->
                    {{ $_n('%s customer selected', '%s customers selected', selectedCustomers.length, selectedCustomers.length) }}
                </span>
                <el-button size="small" class="fs_outline_btn" :disabled="bulkBusy" @click="bulkSetStatus('inactive')">
                    {{ $t('Block') }}
                </el-button>
                <el-button size="small" class="fs_outline_btn" :disabled="bulkBusy" @click="bulkSetStatus('active')">
                    {{ $t('Unblock') }}
                </el-button>
                <el-button v-if="canDeleteCustomers" size="small" class="fs_stroke_btn" :disabled="bulkBusy" @click="confirmBulkDelete()">
                    {{ $t('Delete') }}
                </el-button>
                <el-button size="small" text @click="clearSelection()">{{ $t('Clear selection') }}</el-button>
            </div>

            <div class="fs_customers_table" v-loading="loading && !first_time_loading">
                <TableSkeleton v-if="first_time_loading" :rows="8" :columns="[2.6, '130px', 1.6, '150px', '140px', '60px']" avatar />
                <el-table
                    v-else
                    ref="customersTable"
                    :data="customers"
                    row-key="id"
                    row-class-name="fs_table_row fs_customers_row"
                    header-row-class-name="fs_table_header_row"
                    cell-class-name="fs_table_cell"
                    header-cell-class-name="fs_table_header_cell"
                    @selection-change="handleSelectionChange"
                    @row-click="openCustomer"
                >
                    <el-table-column type="selection" width="48" />
                    <el-table-column :label="$t('Customer')" min-width="260" class-name="fs_col_customer">
                        <template #default="{ row }">
                            <div class="fs_customer_name_cell">
                                <div class="fs_customer_avatar">
                                    <img
                                        :src="row.photo"
                                        :alt="row.full_name || $t('Customer')"
                                        class="fs_avatar_img"
                                        @error="handleImageError"
                                    />
                                </div>
                                <div class="fs_customer_info">
                                    <span class="fs_customer_name_row">
                                        <router-link
                                            :to="{ name: 'view_customer', params: { customer_id: row.id } }"
                                            class="fs_customer_name_link"
                                            @click.stop
                                        >{{ row.full_name || $t('No name') }}</router-link>
                                        <span class="fs_customer_id">#{{ row.id }}</span>
                                        <span v-if="row.status === 'inactive'" class="fs_status_badge fs_status_blocked">
                                            {{ $t('Blocked') }}
                                        </span>
                                    </span>
                                    <div class="fs_customer_email">{{ row.email }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('Tickets')" width="130" class-name="fs_col_tickets">
                        <template #default="{ row }">
                            <router-link
                                :to="{ name: 'tickets', query: { search: 'customer_id:' + row.id } }"
                                class="fs_customer_ticket_count"
                                :title="$t('View tickets')"
                                @click.stop
                            >
                                <!-- translators: %1$s: open tickets, %2$s: all tickets -->
                                {{ $t('%1$s open / %2$s', row.open_tickets || 0, row.total_tickets || 0) }}
                            </router-link>
                        </template>
                    </el-table-column>
                    <el-table-column v-if="showLocation" :label="$t('Location')" min-width="160" class-name="fs_col_location">
                        <template #default="{ row }">
                            <span v-if="customerLocation(row)">{{ customerLocation(row) }}</span>
                            <span v-else class="fs_customer_muted">--</span>
                        </template>
                    </el-table-column>
                    <el-table-column v-if="isVisible('last_activity')" :label="$t('Last Activity')" width="150" class-name="fs_col_activity">
                        <template #default="{ row }">
                            <span v-if="row.last_response_at" :title="dateTimeFormat(row.last_response_at, 'DD MMM YYYY, hh:mm A')">
                                {{ $timeDiff(row.last_response_at) }}
                            </span>
                            <span v-else class="fs_customer_muted">--</span>
                        </template>
                    </el-table-column>
                    <el-table-column v-if="isVisible('created_at')" :label="$t('Created at')" width="140" class-name="fs_col_created">
                        <template #default="{ row }">
                            {{ dateTimeFormat(row.created_at, 'DD MMM YYYY') }}
                        </template>
                    </el-table-column>
                    <el-table-column width="60" align="right" class-name="fs_col_actions">
                        <template #default="{ row }">
                            <div @click.stop>
                                <el-dropdown trigger="click" placement="bottom-end" @command="(command) => handleRowCommand(command, row)">
                                    <el-button text class="fs_action_button" icon="More" :aria-label="$t('More actions')" />
                                    <template #dropdown>
                                        <el-dropdown-menu class="fs_global_dropdown">
                                            <el-dropdown-item command="edit">
                                                <div class="fs_dropdown_item_content">
                                                    <el-icon class="fs_dropdown_icon"><EditPen /></el-icon>
                                                    {{ $t('Edit') }}
                                                </div>
                                            </el-dropdown-item>
                                            <el-dropdown-item command="tickets">
                                                <div class="fs_dropdown_item_content">
                                                    <el-icon class="fs_dropdown_icon"><Tickets /></el-icon>
                                                    {{ $t('View tickets') }}
                                                </div>
                                            </el-dropdown-item>
                                            <el-dropdown-item command="toggle_block">
                                                <div class="fs_dropdown_item_content">
                                                    <el-icon class="fs_dropdown_icon">
                                                        <Unlock v-if="row.status === 'inactive'" />
                                                        <Lock v-else />
                                                    </el-icon>
                                                    {{ row.status === 'inactive' ? $t('Unblock') : $t('Block') }}
                                                </div>
                                            </el-dropdown-item>
                                            <el-dropdown-item v-if="canDeleteCustomers" command="delete" class="fs_delete_action" divided>
                                                <div class="fs_dropdown_item_content">
                                                    <el-icon class="fs_dropdown_icon"><Delete /></el-icon>
                                                    {{ $t('Delete') }}
                                                </div>
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                        </template>
                    </el-table-column>
                    <template #empty>
                        <div class="fs_table_empty_state">
                            <el-empty :description="emptyText" :image-size="130">
                                <template #image>
                                    <TableEmptyStateImage />
                                </template>
                            </el-empty>
                        </div>
                    </template>
                </el-table>
            </div>

            <div class="fs_pagination_wrapper fs_customers_footer" v-if="pagination.total">
                <span class="fs_pagination_left">
                    <!-- translators: %1$s: current page, %2$s: total pages -->
                    <p>{{ $t('Page %1$s of %2$s', pagination.current_page, totalPages) }}</p>
                    <pagination @fetch="onPageChange()" :pagination="pagination" layout="sizes" />
                    <!-- translators: %s: number of customers -->
                    <p>{{ $t('Total %s', pagination.total) }}</p>
                </span>
                <span class="fs_pagination_right">
                    <pagination
                        @fetch="onPageChange()"
                        :pagination="pagination"
                        :background="true"
                        layout="prev, pager, next"
                    />
                </span>
            </div>
        </div>

        <el-dialog
            :append-to-body="true"
            :title="editing_customer.id ? $t('Update customer') : $t('Add new customer')"
            v-model="showEditModal"
            v-if="editing_customer"
            width="60%"
            class="fs_dialog"
        >
            <customer-form
                v-if="editing_customer"
                :customer="editing_customer"
                :hide-buttons="true"
                :key="componentKey"
            />
            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button class="fs_outline_btn" @click="() => showEditModal = false">
                        {{ $t("Cancel") }}
                    </el-button>
                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        @click="createOrUpdateCustomer()"
                        class="fs_filled_btn"
                    >
                        {{ editing_customer.id ? $t("Update Customer") : $t("Create Customer") }}
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import CustomerForm from "./_CustomerForm";
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import TableEmptyStateImage from '@/admin/Components/TableEmptyStateImage.vue';
import CustomerSearcher from './Search/CustomerSearcher.vue';
import CustomerSearchSummary from './Search/CustomerSearchSummary.vue';
import TableSkeleton from "@/admin/Components/TableSkeleton.vue";
import { canDeleteCustomers } from './customerPermissions';

const COLUMNS_STORAGE_KEY = 'customers_list_columns';

export default {
    name: "Customers",
    components: {
        DynamicIcon,
        Pagination,
        CustomerForm,
        TableEmptyStateImage,
        CustomerSearcher,
        CustomerSearchSummary,
        TableSkeleton,
    },
    data() {
        const query = this.$route.query;

        return {
            customers: [],
            pagination: {
                per_page: parseInt(query.per_page) || 20,
                current_page: parseInt(query.page) || 1,
                total: 0,
            },
            loading: false,
            saving: false,
            bulkBusy: false,
            first_time_loading: true,
            showEditModal: false,
            editing_customer: {},
            view: query.view || 'all',
            // search is what the list shows; searchInput is the box, applied on Enter.
            search: query.search || '',
            searchInput: query.search || '',
            searchOpen: !!query.search,
            searchMeta: null,
            sortBy: query.sort_by || 'created_at',
            sortType: query.sort_type === 'ASC' ? 'ASC' : 'DESC',
            // A search ranks best matches first until the agent picks a column sort.
            sortExplicit: !!query.sort_by || !!query.sort_type,
            visibleColumns: this.$getData(COLUMNS_STORAGE_KEY, ['location', 'last_activity', 'created_at']),
            selectedCustomers: [],
            // Only the latest list request may update the table (tabs, search and paging can overlap).
            customersFetchRequestId: 0,
        };
    },
    computed: {
        componentKey() {
            return this.editing_customer && this.editing_customer.id
                ? this.editing_customer.id.toString()
                : (Math.random() * 1000).toFixed(0).toString();
        },
        views() {
            return {
                all: this.$t('All'),
                open_tickets: this.$t('Has open tickets'),
                active: this.$t('Active'),
                inactive: this.$t('Blocked'),
            };
        },
        toggleableColumns() {
            return {
                location: this.$t('Location'),
                last_activity: this.$t('Last Activity'),
                created_at: this.$t('Created at'),
            };
        },
        sortOptions() {
            return {
                created_at: this.$t('Created at'),
                last_response_at: this.$t('Last Activity'),
                first_name: this.$t('Name'),
            };
        },
        // Most customers have no address: drop the column when this page has none.
        showLocation() {
            return this.isVisible('location') && this.customers.some(customer => this.customerLocation(customer));
        },
        totalPages() {
            return Math.max(1, Math.ceil(this.pagination.total / this.pagination.per_page));
        },
        emptyText() {
            if (this.search || this.view !== 'all') {
                return this.$t('No customers match your filters');
            }
            return this.$t('No customers found');
        },
        canDeleteCustomers() {
            return canDeleteCustomers(this.appVars);
        },
        // Mirrors CustomerSearch::isRankable(): free text of 3+ characters, not an ID or field-only search.
        canRank() {
            const meta = this.searchMeta;
            return !!meta && !meta.ranked && this.sortExplicit
                && ['words', 'email'].includes(meta.mode) && (meta.text || '').length >= 3;
        },
    },
    mounted() {
        this.$setTitle(this.$t("Customers"));
        this.fetchCustomers();
    },
    methods: {
        fetchCustomers() {
            this.loading = true;
            this.syncQuery();
            const requestId = ++this.customersFetchRequestId;

            const params = {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search,
                sort_by: this.search && !this.sortExplicit ? 'relevance' : this.sortBy,
                sort_type: this.sortType,
                // field:value syntax (city:berlin, tickets:>2); see CustomerSearch.php.
                smart_search: 'yes',
                // Searching the list must not create customers from FluentCRM / WordPress users.
                import_contacts: 'no',
            };

            if (this.view === 'open_tickets') {
                params.has_open_tickets = 'yes';
            } else if (this.view !== 'all') {
                params.status = this.view;
            }

            this.$get("customers", params)
                .then((response) => {
                    if (requestId !== this.customersFetchRequestId) {
                        return;
                    }
                    this.customers = response.customers.data;
                    this.pagination.total = response.customers.total;
                    this.searchMeta = response.search_meta || null;
                })
                .catch((error) => {
                    if (requestId !== this.customersFetchRequestId) {
                        return;
                    }
                    this.$handleError(error);
                })
                .always(() => {
                    if (requestId !== this.customersFetchRequestId) {
                        return;
                    }
                    this.loading = false;
                    this.first_time_loading = false;
                });
        },

        // Keep page, view, search and sort in the URL so Back from a profile lands on the same list.
        syncQuery() {
            const query = {};
            if (this.view !== 'all') query.view = this.view;
            if (this.search) query.search = this.search;
            if (this.pagination.current_page > 1) query.page = String(this.pagination.current_page);
            if (this.pagination.per_page !== 20) query.per_page = String(this.pagination.per_page);
            if (this.sortExplicit) {
                query.sort_by = this.sortBy;
                query.sort_type = this.sortType;
            }

            this.$router.replace({ query }).catch(() => {});
        },

        resetAndFetch() {
            this.pagination.current_page = 1;
            this.clearSelection();
            this.fetchCustomers();
        },

        onPageChange() {
            this.clearSelection();
            this.fetchCustomers();
        },

        setView(view) {
            this.view = view;
            this.resetAndFetch();
        },

        applySort() {
            this.sortExplicit = true;
            this.resetAndFetch();
        },

        sortByBestMatch() {
            this.sortExplicit = false;
            this.sortBy = 'created_at';
            this.sortType = 'DESC';
            this.resetAndFetch();
        },

        toggleSearch() {
            if (this.searchOpen) {
                this.closeSearch();
                return;
            }
            this.searchOpen = true;
            this.$nextTick(() => this.$refs.searcher && this.$refs.searcher.focus());
        },

        closeSearch() {
            this.searchOpen = false;
            this.searchInput = '';
            if (this.search) {
                this.runSearch('');
            }
        },

        runSearch(text) {
            this.search = (text || '').trim();
            if (!this.search) {
                this.searchMeta = null;
            }
            this.resetAndFetch();
        },

        isVisible(column) {
            return this.visibleColumns.includes(column);
        },

        saveColumns() {
            this.$saveData(COLUMNS_STORAGE_KEY, this.visibleColumns);
        },

        customerLocation(customer) {
            return [customer.city, customer.country].filter(Boolean).join(', ');
        },

        openCustomer(row) {
            this.$router.push({ name: 'view_customer', params: { customer_id: row.id } });
        },

        handleRowCommand(command, row) {
            if (command === 'edit') {
                this.showAddCustomerModal({ ...row });
            } else if (command === 'tickets') {
                this.$router.push({ name: 'tickets', query: { search: 'customer_id:' + row.id } });
            } else if (command === 'toggle_block') {
                this.updateStatus([row.id], row.status === 'inactive' ? 'active' : 'inactive');
            } else if (command === 'delete') {
                this.confirmDelete([row.id]);
            }
        },

        showAddCustomerModal(customer) {
            this.editing_customer = customer;
            this.showEditModal = true;
        },

        createOrUpdateCustomer() {
            this.saving = true;
            const method = this.editing_customer.id ? this.$put : this.$post;
            const route = this.editing_customer.id ? `customers/${this.editing_customer.id}` : "customers";

            method(route, { ...this.editing_customer })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.fetchCustomers();
                    this.showEditModal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },

        handleImageError(event) {
            event.target.style.display = 'none';
        },

        handleSelectionChange(selection) {
            this.selectedCustomers = selection;
        },

        clearSelection() {
            if (this.$refs.customersTable) {
                this.$refs.customersTable.clearSelection();
            }
            this.selectedCustomers = [];
        },

        bulkSetStatus(status) {
            this.updateStatus(this.selectedCustomers.map(customer => customer.id), status);
        },

        updateStatus(customerIds, status) {
            this.bulkBusy = true;
            this.$put('customers/bulk-status', { customer_ids: customerIds, status })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.clearSelection();
                    this.fetchCustomers();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.bulkBusy = false;
                });
        },

        confirmBulkDelete() {
            this.confirmDelete(this.selectedCustomers.map(customer => customer.id));
        },

        confirmDelete(customerIds) {
            if (!customerIds.length) {
                return;
            }

            // translators: %s is the number of customers selected for deletion
            const message = this.$_n(
                'Are you sure you want to delete %s customer? All data associated with this customer will be deleted.',
                'Are you sure you want to delete %s customers? All data associated with these customers will be deleted.',
                customerIds.length,
                customerIds.length
            );

            this.$confirm({
                message,
                title: this.$t('Confirm Delete'),
                options: {
                    confirmButtonText: this.$t('Delete'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning',
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'el-button--danger fs_filled_btn'
                }
            })
                .then(() => this.deleteCustomers(customerIds))
                .catch(() => {});
        },

        deleteCustomers(customerIds) {
            this.bulkBusy = true;
            const request = customerIds.length === 1
                ? this.$del(`customers/${customerIds[0]}`)
                : this.$del('customers/bulk-delete', { customer_ids: customerIds });

            request
                .then((response) => {
                    this.$notify({
                        message: response.message || this.$t('Customers deleted successfully'),
                        type: "success",
                        position: "bottom-right",
                    });
                    this.clearSelection();
                    this.fetchCustomers();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.bulkBusy = false;
                });
        },
    }
};
</script>
