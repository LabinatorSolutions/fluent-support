<template>
    <div class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("All Customers") }}</h3>
                    <el-button
                        @click="showAddCustomerModal({})"
                        size="small"
                        icon="Plus"
                        >{{ translate("Add Customer") }}
                    </el-button>
                </div>
                <div class="fs_box_actions fs_customer_filters">
                    <div class="fs_cs_status_filter">
                        <label>{{ translate("Status") }}</label>
                        <el-select
                            filterable
                            @change="fetchCustomers()"
                            v-model="status"
                            size="small"
                            style="padding-right: 0.7em"
                        >
                            <el-option
                                v-for="(statusKey, statusName) in statusFilters"
                                :key="statusName"
                                :value="statusName"
                                :label="statusKey"
                            ></el-option>
                        </el-select>
                    </div>
                    <el-input
                        @keyup.enter="fetchCustomers()"
                        clearable
                        @clear="fetchCustomers()"
                        size="small"
                        :placeholder="translate('Search Customers')"
                        v-model="search"
                    >
                        <template #append>
                            <el-button
                                @click="fetchCustomers()"
                                icon="Search"
                            ></el-button>
                        </template>
                    </el-input>
                </div>
            </div>
            <div class="fs_box_body fs_padded_20">
                <template v-if="!first_time_loading">
                    <el-table
                        :row-class-name="tableRowClassName"
                        v-loading="loading"
                        stripe
                        :data="customers"
                    >
                        <el-table-column
                            prop="id"
                            :label="translate('ID')"
                            width="100"
                        ></el-table-column>
                        <el-table-column :label="translate('Name')" width="260">
                            <template #default="scope">
                                <router-link
                                    :to="{
                                        name: 'view_customer',
                                        params: { customer_id: scope.row.id },
                                    }"
                                >
                                    {{ scope.row.full_name }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column :label="translate('Email')">
                            <template #default="scope">
                                {{ scope.row.email }}
                            </template>
                        </el-table-column>
                        <el-table-column
                            width="120"
                            :label="translate('Status')"
                        >
                            <template #default="scope">
                                <span v-if="scope.row.status == 'inactive'">{{
                                    translate("Blocked")
                                }}</span>
                                <span v-else>{{
                                    ucFirst(scope.row.status)
                                }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="translate('Last Activity')"
                            width="160"
                        >
                            <template #default="scope">
                                {{ humanDiffTime(scope.row.last_response_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="translate('Stats')"
                            width="180"
                        >
                            <template #default="scope">
                                <router-link
                                    :to="{
                                        name: 'tickets',
                                        query: {
                                            search:
                                                'customer_id:' + scope.row.id,
                                        },
                                    }"
                                >
                                    <el-button
                                        size="small"
                                        text
                                        icon="Folder"
                                        style="color: #409eff"
                                    >
                                        {{ scope.row.total_tickets }}
                                    </el-button>
                                </router-link>
                                <el-button
                                    size="small"
                                    text
                                    icon="ChatLineRound"
                                    style="color: #409eff"
                                >
                                    {{ scope.row.total_responses }}
                                </el-button>

                                <el-popconfirm
                                    v-loading="deleting"
                                    confirm-button-type="danger"
                                    :confirm-button-text="
                                        translate('Yes, Delete this Customer')
                                    "
                                    :cancel-button-text="translate('No')"
                                    icon-color="red"
                                    @confirm="deleteCustomer(scope.row.id)"
                                    :title="
                                        translate(
                                            'Are you sure to delete this customer? It will delete all associated data with this customer'
                                        )
                                    "
                                >
                                    <template #reference>
                                        <el-button
                                            size="small"
                                            text
                                            icon="Delete"
                                            style="color: red"
                                        />
                                        <span class="fs_badge"><Delete /></span>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="fframe_pagination_wrapper" v-if="customers.length">
                        <pagination
                            @fetch="fetchCustomers()"
                            :pagination="pagination"
                        />
                    </div>
                </template>

                <div v-else style="padding: 15px">
                    <el-skeleton :rows="10" animated />
                </div>
            </div>
        </div>
        <el-dialog
            :append-to-body="true"
            :title="
                editing_customer.id
                    ? translate('Update customer')
                    : translate('Add new customer')
            "
            v-model="showEditModal"
            v-if="editing_customer"
            width="60%"
        >
            <customer-form
                v-if="editing_customer"
                @updated="closeModal()"
                :customer="editing_customer"
                :key="componentKey"
            />
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import CustomerForm from "./_CustomerForm";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import { onMounted, reactive, toRefs, computed } from "vue";

export default {
    name: "Customers",
    components: {
        Pagination,
        CustomerForm,
    },
    setup() {
        const {
            appVars,
            get,
            del,
            translate,
            handleError,
            moment,
            setTitle,
            ucFirst,
            humanDiffTime,
        } = useFluentHelper();
        const { notify } = useNotify();
        const state = reactive({
            customers: [],
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0,
            },
            loading: false,
            deleting: false,
            first_time_loading: true,
            showEditModal: false,
            editing_customer: {},
            status: "",
            search: "",
            statusFilters: {
                active: translate("Active"),
                inactive: translate("Blocked"),
                pending: translate("Pending"),
            },
        });

        const fetchCustomers = async () => {
            get("customers", {
                per_page: state.pagination.per_page,
                page: state.pagination.current_page,
                search: state.search,
                status: state.status,
            })
                .then((response) => {
                    state.customers = response.customers.data;
                    state.pagination.total = response.customers.total;
                })
                .always(() => {
                    state.loading = false;
                    state.first_time_loading = false;
                })
                .catch((error) => {
                    handleError(error);
                });
        };

        const componentKey = computed(() => {
            return (Math.random() * 1000).toFixed(0).toString();
        });


        const showAddCustomerModal = (customer) => {
            state.editing_customer = customer;
            state.showEditModal = true;
        };
        const closeModal = () => {
            state.editing_customer = {};
            state.showEditModal = false;
            fetchCustomers();
        };

        const deleteCustomer = (customerId) => {
            state.deleting = true;
            del(`customers/${customerId}`)
                .then((response) => {
                    fetchCustomers();
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.deleting = false;
                });
        };

        const tableRowClassName = ({ row, rowIndex }) => {
            return "fs_status_" + row.status;
        };

        onMounted(() => {
            setTitle(translate("Customers"));
            fetchCustomers();
        });

        return {
            appVars,
            get,
            translate,
            handleError,
            moment,
            fetchCustomers,
            showAddCustomerModal,
            closeModal,
            deleteCustomer,
            setTitle,
            ucFirst,
            humanDiffTime,
            componentKey,
            tableRowClassName,
            ...toRefs(state),
        };
    },
};
</script>

<style scoped>
.fs_box_actions.fs_customer_filters {
    display: flex;
    align-items: flex-end;
}
</style>
