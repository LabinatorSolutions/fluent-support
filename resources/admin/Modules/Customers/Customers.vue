<template>
    <div class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('All Customers') }}</h3>
                    <el-button
                        @click="showEditCustomerModal({})"
                        size="small"
                        icon="Plus">{{ $t('Add Customer') }}
                    </el-button>
                </div>
                <div class="fs_box_actions fs_customer_filters">
                    <div class="fs_cs_status_filter">
                        <label>{{ $t('Status') }}</label>
                        <el-select filterable @change="fetchCustomers()" v-model="status" size="small"
                                   style="padding-right: 0.7em;">
                            <el-option
                                v-for="(statusKey, statusName) in statusFilters"
                                :key="statusName"
                                :value="statusName"
                                :label="statusKey"
                            ></el-option>
                        </el-select>
                    </div>
                    <el-input @keyup.enter="fetchCustomers()" clearable @clear="fetchCustomers()" size="small"
                              :placeholder="$t('Search Customers')" v-model="search">
                        <template #append>
                            <el-button @click="fetchCustomers()" icon="Search"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>
            <div class="fs_box_body fs_padded_20">
                <template v-if="!first_time_loading">
                    <el-table :row-class-name="tableRowClassName" v-loading="loading" stripe :data="customers">
                        <el-table-column prop="id" :label="$t('ID')" width="100"></el-table-column>
                        <el-table-column :label="$t('Name')" width="260">
                            <template #default="scope">
                                <router-link :to="{name: 'view_customer', params: { customer_id: scope.row.id }}">
                                    {{ scope.row.full_name }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('Email')">
                            <template #default="scope">
                                {{ scope.row.email }}
                            </template>
                        </el-table-column>
                        <el-table-column width="120" :label="$t('Status')">
                            <template #default="scope">
                                <span v-if="scope.row.status == 'inactive'">{{$t('Blocked')}}</span>
                                <span v-else>{{ ucFirst(scope.row.status) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('Last Activity')" width="160">
                            <template #default="scope">
                                {{ $timeDiff(scope.row.last_response_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('Stats')" width="180">
                            <template #default="scope">
                                <router-link :to="{ name: 'tickets', query: { search: 'customer_id:'+scope.row.id } }">
                                    <el-button size="small" type="text" icon="Folder" style="color:#409eff;">
                                        {{ scope.row.total_tickets }}
                                    </el-button>
                                </router-link>
                                <el-button size="small" type="text" icon="ChatLineRound" style="color:#409eff;">
                                    {{ scope.row.total_responses }}
                                </el-button>

                                <el-popconfirm
                                    v-loading="deleting"
                                    confirm-button-type="danger"
                                    :confirm-button-text="$t('Yes, Delete this Customer')"
                                    :cancel-button-text="$t('No')"
                                    icon-color="red"
                                    @confirm="deleteCustomer(scope.row.id)"
                                    :title="$t('Are you sure to delete this customer? It will delete all associated data with this customer')"
                                >
                                    <template #reference>
                                        <el-button size="small" type="text" icon="Delete" style="color:red;"/>
                                        <span class="fs_badge"><Delete /></span>
                                    </template>
                                </el-popconfirm>

                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="fframe_pagination_wrapper">
                        <pagination @fetch="fetchCustomers()" :pagination="pagination"/>
                    </div>
                </template>

                <div v-else style="padding: 15px;">
                    <el-skeleton :rows="10" animated/>
                </div>

            </div>
        </div>
        <el-dialog
            :append-to-body=true
            :title="(editing_customer.id) ? $t('Update customer') : $t('Add new customer')"
            v-model="showEditModal"
            v-if="editing_customer"
            width="60%">
            <customer-form v-if="editing_customer" @updated="closeModal()" :customer="editing_customer"/>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import CustomerForm from './_CustomerForm';

export default {
    name: 'Customers',
    components: {
        Pagination,
        CustomerForm
    },
    data() {
        return {
            customers: [],
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            },
            first_time_loading: true,
            search: '',
            loading: false,
            editing_customer: {},
            showEditModal: false,
            statusFilters: {
                all: 'All',
                active: 'Active',
                inactive: 'Blocked'
            },
            status: 'all',
            deleting: false
        }
    },
    methods: {
        fetchCustomers() {
            this.loading = true;
            this.$get('customers', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search,
                status: this.status
            })
                .then((response) => {
                    this.customers = response.customers.data;
                    this.pagination.total = response.customers.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                    this.first_time_loading = false;
                });
        },
        showEditCustomerModal(customer) {
            this.editing_customer = customer;
            this.showEditModal = true;
        },
        closeModal() {
            this.editing_customer = {};
            this.showEditModal = false;
            this.fetchCustomers();
        },
        deleteCustomer(customerId) {
            this.deleting = true;
            this.$del(`customers/${customerId}`)
                .then(response => {
                    this.fetchCustomers();
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    })
                })
                .catch(errors => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.deleting = false;
                });
        },
        tableRowClassName({ row, rowIndex }) {
            return 'fs_status_' + row.status;
        }
    },
    mounted() {
        this.fetchCustomers();
        this.$setTitle('Customers');
    }
}
</script>

<style scoped>
.fs_box_actions.fs_customer_filters {
    display: flex;
    align-items: flex-end;
}
</style>
