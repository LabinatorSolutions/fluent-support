<template>
    <div class="fs_all_tickets">
        <div class="fs_box_wrapper">
            <h4>Filter ticket</h4>
            <hr/>
            <div class="fs_box_head">
                <el-row :gutter="20">
                    <el-col :span="8">
                        <label>{{$t('Select Customer')}}</label>
                        <el-select clearable filterable v-model="filters.customer" remote reserve-keyword
                                   @change="filterTicket"
                                   :placeholder="$t('Filter by Customer')">
                            <el-option v-for="customer in customers" :key="customer.id" :value="customer.id"
                                       :label="customer.name"></el-option>
                        </el-select>
                    </el-col>
                    <el-col :span="8">
                        <label>{{$t('Select Project')}}</label>
                        <el-select clearable filterable v-model="filters.product_id" remote reserve-keyword
                                   @change="filterTicket"
                                   :placeholder="$t('Filter By Project')">
                            <el-option v-for="product in products" :key="product.id" :value="product.id"
                                       :label="product.name"></el-option>
                        </el-select>
                    </el-col>
                    <el-col :span="7">
                        <label>{{$t('Ticket Title')}}</label>
                        <el-input @keyup="filterTicket" v-model="filters.ticket_title" placeholder="Filter by ticket title" />
                    </el-col>
                </el-row>
            </div>
            <div class="fs_box_body">
                <h4>Select ticket that you want to move</h4>
                <hr/>
                <el-table :data="filteredTicket" height="350" @selection-change="handleSelectionChange">
                    <el-table-column
                        type="selection"
                        width="55" :label="$t('Select All')">
                    </el-table-column>
                    <el-table-column min-width="300" :label="$t('Ticket Title and Customer')">
                        <template #default="scope">
                            <strong>{{ scope.row.title }}</strong>
                            <span  style="font-size: 10px;"> by {{ scope.row.customer.first_name }} {{ scope.row.customer.last_name }}</span>
                            <span style="margin-left: 5px; font-size: 10px;"

                                  class="fs_badge">
                                    {{ scope.row.product?.title }}
                                </span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>

    </div>
</template>

<script type="text/babel">
    import each from "lodash/each";

    export default {
        name: 'AllTickets',
        props:['mailbox_id', 'getSelectedTickets'],
        data() {
            return {
                filteredTicket: [],
                tickets: [],
                filters: {
                    status_type: 'open',
                    product_id: '',
                    mailbox_id: this.mailbox_id,
                    customer: '',
                    ticket_title: ''
                },
                customers: [],
                products: [],
                filtered: false
            }
        },
        methods: {
            fetchTicket: function () {
                let query = {
                    page: 1,
                    per_page: 10,
                    order_by: 'id',
                    order_type: 'ASC',
                    filter_type: 'simple',
                    filters: this.filters,
                };

                this.$get('tickets', query)
                    .then(response => {
                        this.tickets = this.filteredTicket = response.tickets.data;

                        each(this.tickets, (ticket) => {
                            let temp = (ticket.customer.first_name) ? ticket.customer.first_name : '';
                            temp += '#';
                            temp += (ticket.customer.last_name) ? ticket.customer.last_name : '';

                            const found = this.customers.some(el => el.id === temp);
                            if (!found && temp && temp !== '#'){
                                this.customers.push({id: temp, name: ticket.customer.first_name+' '+ticket.customer.last_name})
                            }

                            let tempId = (ticket.product && ticket.product.id) ? ticket.product.id : '';

                            if(tempId && !this.products.some(el => el.id === tempId)){
                                this.products.push({id: ticket.product.id, name: ticket.product.title})
                            }
                        });

                    })
                    .catch((errors) => {
                        this.$handleError(errors);
                    })
                    .always(() => {
                        this.loading = false;
                        this.first_time_loading = false;
                    })
            },
            handleSelectionChange: function (selections) {
                this.$emit('getSelectedTickets', selections)
            },
            filterTicket: function(){
                let customer = this.filters.customer.split('#'), first_name = '', last_name = '';

                if(typeof customer[0] !== 'undefined'){
                    first_name = customer[0];
                }

                if(typeof customer[1] !== 'undefined'){
                    last_name = customer[1];
                }

                this.filteredTicket = this.tickets.filter(
                    (data) =>
                        (!this.filters.customer || (data.customer.first_name.toLowerCase().includes(first_name.toLowerCase())
                        && data.customer.last_name.toLowerCase().includes(last_name.toLowerCase())))

                        && (this.filters.product_id && (data.product && data.product.id === this.filters.product_id) || !this.filters.product_id)

                        && (this.filters.ticket_title && (data.title.toLowerCase().includes(this.filters.ticket_title.toLowerCase())) || !this.filters.ticket_title)
                )
            }
        },
        mounted() {
            this.fetchTicket();
        }
    }

</script>

<style>
    .el-table .cell {
        min-height: 23px;
    }
</style>
