<template>
    <el-dialog
        :title="$t('Move all tickets to new Business Box')"
        v-model="move_ticket.show_modal"
        width="60%" @close="closeModal">
        <el-form label-position="top">
            <el-form-item :label="$t('Fallback Business')">
                <el-select v-model="move_ticket.fallback_box" :placeholder="$t('Select Business Box')">
                    <el-option :disabled="mailbox.id == move_ticket.box_id" v-for="mailbox in mailboxes"
                               :key="mailbox.id" :value="mailbox.id"
                               :label="mailbox.name"></el-option>
                </el-select>
                <p>{{ $t('select_new_business_to_move_tickets') }}</p>
            </el-form-item>
            <el-form-item :label="$t('All or Custom')">
                <el-select v-model="move_ticket.move_type" :placeholder="$t('Want to move all or custom selected')" @change='showTicket'>
                    <el-option v-for="_move_type in moveTypes"
                               :key="_move_type" :value="_move_type"
                               :label="_move_type"></el-option>
                </el-select>
            </el-form-item>
        </el-form>

        <template v-if="!!show_ticket_selection">
            <div class="fs_all_tickets">
                <div class="fs_box_wrapper">
                    <h4>Filter ticket</h4>
                    <hr/>
                    <div class="fs_box_head">
                        <el-form :data="filters" label-position="top">
                            <el-row :gutter="20">
                                <el-col :span="8">
                                    <el-form-item :label="$t('Select Customer')">
                                        <remote-selector
                                            v-model="filters.customer_id"
                                            response_key="customers"
                                            api_path="customers"
                                            value_selector="id"
                                            label_joiner=" - "
                                            :label_selectors="['full_name','email']"
                                            @change="CustomerChangeHandler"
                                            clearable
                                        />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="8">
                                    <el-form-item :label="$t('Select Product')">
                                        <remote-selector
                                            v-model="filters.product_id"
                                            response_key="products"
                                            api_path="products"
                                            value_selector="id"
                                            :label_selectors="['title']"
                                            @change="ProductChangeHandler"
                                            clearable
                                        />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="7">
                                    <el-form-item :label="$t('Ticket Title')">
                                        <el-input v-model="filters.ticket_title" placeholder="Filter by ticket title" clearable
                                        @keyup="showTicket('Custom')" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </el-form>
                    </div>
                    <div class="fs_box_body">
                        <h4>Select ticket that you want to move</h4>
                        <hr/>
                        <el-table  :v-infinite-scroll="load" class="infinite-list" style="overflow: auto" :data="tickets" height="350" @selection-change="handleSelectionChange">
                            <el-table-column
                                type="selection"
                                width="55" :label="$t('Select All')">
                            </el-table-column>
                            <el-table-column min-width="300" :label="$t('Tickets')">
                                <template #default="scope">
                                    <strong>{{ scope.row.title }}</strong>
                                    <span v-if="!filters.customer_id && scope.row.customer"  style="font-size: 10px;"> by {{ scope.row.customer.first_name }} {{ scope.row.customer.last_name }}</span>
                                    <span style="margin-left: 5px; font-size: 10px;"

                                          class="fs_badge" v-if="!!scope.row.product && !filters.product_id">
                                    {{ scope.row.product?.title }}
                                </span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div style="padding-bottom: 20px;" class="fframe_pagination_wrapper">
                            <pagination @fetch="showTicket('Custom')" :pagination="pagination"/>
                        </div>
                    </div>
                </div>

            </div>
        </template>

        <template #footer>
                <span class="dialog-footer">
                  <el-button @click="move_ticket.show_modal = false">{{ $t('Cancel') }}</el-button>
                  <el-button v-loading="moving" :disabled="moving" type="success"
                             @click="moveTicketMailBox()">{{ $t('Move') }} <span v-if="move_ticket.selected_tickets.length != 0"> ( {{move_ticket.selected_tickets.length}} )</span></el-button>
                </span>
        </template>
    </el-dialog>
</template>

<script type="text/babel">
    import Pagination from '../../Pieces/Pagination';
    import RemoteSelector from '../../Pieces/RemoteSelector';
    import each from "lodash/each";

    export default {
        name: 'MoveTicket',
        components: {
            Pagination,
            RemoteSelector
        },
        props:['mailbox_id', 'mailboxes'],
        emits:['update_mailbox', 'reset_me'],
        data() {
            return {
                count: 10,
                pagination: {
                    current_page: 1,
                    total: 0,
                    per_page: 10
                },
                move_ticket: {
                    show_modal: this.mailbox_id ? true : false,
                    box_id: this.mailbox_id,
                    fallback_box: '',
                    move_type: 'All',
                    selected_tickets: []
                },
                show_ticket_selection: false,
                tickets: [],
                filters: {
                    status_type: '',
                    product_id: '',
                    mailbox_id: this.mailbox_id,
                    customer_id: '',
                    ticket_title: ''
                },
                customers: [],
                products: [],
                filtered: false,
                moving: false,
                moveTypes: ['All', 'Custom']
            }
        },
        methods: {
            load(){
              this.count += 5;
            },
            closeModal(){
                this.$emit('reset_me');
            },
            CustomerChangeHandler(val){
              this.filters.customer_id = val;
              this.showTicket('Custom');
            },
            ProductChangeHandler(val){
                this.filters.product_id = val;
                this.showTicket('Custom');
            },
            showTicket(moveType){
                if(moveType === 'Custom'){
                    let query = {
                        order_by: 'id',
                        order_type: 'DESC',
                        filter_type: 'simple',
                        filters: this.filters,
                        page: this.pagination.current_page,
                        per_page: this.pagination.per_page,
                    };

                    this.$get(`mailboxes/${this.mailbox_id}/tickets`, query)
                        .then(response => {
                            this.tickets = response.tickets.data;
                            this.pagination.total = response.tickets.total;
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
                    this.pagination.total = 0;
                }

            },
            setPage (val) {
                this.page = val
            },
            handleSelectionChange: function (selections) {
                const selectionIds = [];
                each(selections, (selection) => {
                    selectionIds.push(selection.id);
                })
                this.move_ticket.selected_tickets = selectionIds;
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

                if (this.move_ticket.move_type == 'Custom' && Object.keys(this.move_ticket.selected_tickets).length < 1) {
                    alert(this.$t('Please select ticket first'));
                    return false;
                }

                this.moving = true;
                this.$put(`mailboxes/${this.move_ticket.box_id}/move_tickets`, {
                    fallback_id: this.move_ticket.fallback_box,
                    move_type: this.move_ticket.move_type,
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
                        this.$emit('update_mailbox');

                    })
                    .catch((errors) => {
                        this.handleError(errors);
                    })
                    .always(() => {
                        this.moving = false;
                    });
            }
        }
    }

</script>

<style>
    .el-table .cell {
        min-height: 23px;
    }
</style>
