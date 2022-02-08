<template>
    <el-dialog
        :title="$t('Move all tickets to new Business Box')"
        v-model="move_ticket.show_modal"
        width="60%" @close="$emit('reset_me')">
        <el-form :data="move_ticket" label-position="top">
            <el-form-item :label="$t('Fallback Business')">
                <el-select v-model="move_ticket.fallback_box" :placeholder="$t('Select Business Box')">
                    <el-option :disabled="mailbox.id == move_ticket.box_id" v-for="mailbox in mailboxes"
                               :key="mailbox.id" :value="mailbox.id"
                               :label="mailbox.name"></el-option>
                </el-select>
                <p>{{ $t('select_new_business_to_move_tickets') }}</p>
            </el-form-item>
            <el-form-item :label="$t('All or Custom')">
                <el-select id="select_type" v-model="move_ticket.move_type" clearable :placeholder="$t('Want to move all or custom ticket')" @change='showTicket'>
                    <el-option v-for="option in ['All', 'Custom']"
                               :key="option" :value="option"
                               :label="option"></el-option>
                </el-select>
            </el-form-item>

        </el-form>

        <template v-if="show_ticket_selection">
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

        <template #footer>
                <span class="dialog-footer">
                  <el-button @click="move_ticket.show_modal = false">{{ $t('Cancel') }}</el-button>
                  <el-button v-loading="moving" :disabled="moving" type="success"
                             @click="moveTicketMailBox()">{{ $t('Move') }}</el-button>
                </span>
        </template>
    </el-dialog>
</template>

<script type="text/babel">
    import each from "lodash/each";

    export default {
        name: 'AllTickets',
        props:['mailbox_id', 'mailboxes'],
        data() {
            return {
                move_ticket: {
                    show_modal: this.mailbox_id ? true : false,
                    box_id: this.mailbox_id,
                    fallback_box: '',
                    move_type: 'All',
                    selected_tickets: []
                },
                show_ticket_selection: false,
                filteredTicket: [],
                tickets: [],
                filters: {
                    status_type: '',
                    product_id: '',
                    mailbox_id: this.mailbox_id,
                    customer: '',
                    ticket_title: ''
                },
                customers: [],
                products: [],
                filtered: false,
                moving: false
            }
        },
        methods: {
            showTicket(moveType){
                if(moveType === 'Custom'){
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
                            this.show_ticket_selection = true;

                        })
                        .catch((errors) => {
                            this.$handleError(errors);
                        })
                        .always(() => {
                            this.loading = false;
                            this.first_time_loading = false;
                        })
                }else{
                    this.move_ticket.selected_tickets = [];
                    this.show_ticket_selection = false;
                }

            },
            fetchTicket: function () {

            },
            handleSelectionChange: function (selections) {
                const selectionIds = [];
                each(selections, (selection) => {
                    selectionIds.push(selection.id);
                })
                this.move_ticket.selected_tickets = selectionIds;
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
            },
            moveTicketMailBox: function () {
                if (!this.move_ticket.fallback_box || !this.move_ticket.box_id) {
                    alert(this.$t('Please provide fallback box'));
                    return false;
                }
                if (!this.move_ticket.move_type) {
                    alert(this.$t('Please provide You want to move all or custom selected tickets?'));
                    return false;
                }

                this.moving = true;
                this.$put(`mailboxes/${this.move_ticket.box_id}/move_tickets`, {
                    fallback_id: this.move_ticket.fallback_box,
                    tickets: this.move_ticket.selected_tickets
                })
                    .then(response => {
                        this.show_ticket_selection = false;
                        this.move_ticket = {
                            show_modal: false,
                            box_id: this.mailbox_id,
                            fallback_box: '',
                            move_type: 'All',
                            selected_tickets: []
                        };
                        this.$notify.success({
                            message: response.message,
                            position: 'bottom-right'
                        });
                        this.$emit('update_mailbox', response.mailboxes);

                    })
                    .catch((errors) => {
                        this.handleError(errors);
                    })
                    .always(() => {
                        this.moving = false;
                    });
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
