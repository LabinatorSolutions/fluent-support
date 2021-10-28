<template>
    <div v-loading="loading" class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('All Customers')}}</h3>
                    <el-button
                        @click="showEditCustomerModal({})"
                        size="mini"
                        icon="el-icon-plus">{{$t('Add Customer')}}</el-button>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-input @keyup.enter="fetchCustomers()" clearable @clear="fetchCustomers()" size="mini"
                              :placeholder="$t('Search Customers')" v-model="search">
                        <template #append>
                            <el-button @click="fetchCustomers()" icon="el-icon-search"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>
            <div class="fs_box_body fs_padded_20">
                <el-table stripe :data="customers">
                    <el-table-column prop="id" :label="$t('ID')" width="100"></el-table-column>
                    <el-table-column :label="$t('Name')" width="160">
                        <template #default="scope">
                            <a v-if="scope.row.user_profile" :href="scope.row.user_profile">{{ scope.row.full_name }}</a>
                            <span v-else>{{scope.row.full_name}}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('Email')">
                        <template #default="scope">
                            {{ scope.row.email }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('Last Activity')" width="160">
                        <template #default="scope">
                            {{$timeDiff(scope.row.last_response_at)}}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('Stats')" width="180">
                        <template #default="scope">
                            <router-link :to="{ name: 'tickets', query: { search: 'customer_id:'+scope.row.id } }">
                                <span class="fs_badge"><i class="el-icon-folder"></i> {{scope.row.total_tickets}}</span>
                            </router-link>
                            <span class="fs_badge"><i class="el-icon-chat-line-round"></i> {{scope.row.total_responses}}</span>
                            <span @click="showEditCustomerModal(scope.row)" class="fs_badge"><i class="el-icon-edit"></i></span>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="fetchCustomers()" :pagination="pagination"/>
                </div>
            </div>
        </div>
        <el-dialog
            :append-to-body="true"
            :title="(editing_customer.id) ? $t('Update customer') : $t('Add new customer')"
            v-model="showEditModal"
            v-if="editing_customer"
            width="60%">
            <customer-form @updated="closeModal()" :customer="editing_customer" />
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
            search: '',
            loading: false,
            editing_customer: {},
            showEditModal: false
        }
    },
    methods: {
        fetchCustomers() {
            this.loading = true;
            this.$get('customers', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search
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
        }
    },
    mounted() {
        this.fetchCustomers();
        this.$setTitle('Customers');
    }
}
</script>
