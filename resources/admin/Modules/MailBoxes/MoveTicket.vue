<template>
    <el-dialog
        :title="translate('Move all tickets to new Business Box')"
        v-model="move_ticket.show_modal"
        width="60%" @close="closeModal">
        <el-form label-position="top">
            <el-form-item :label="translate('Fallback Business')">
                <el-select v-model="move_ticket.fallback_box" :placeholder="translate('Select Business Box')">
                    <el-option :disabled="mailbox.id == move_ticket.box_id" v-for="mailbox in mailboxes"
                               :key="mailbox.id" :value="mailbox.id"
                               :label="mailbox.name"></el-option>
                </el-select>
                <p>{{ translate('select_new_business_to_move_tickets') }}</p>
            </el-form-item>
            <el-form-item :label="translate('All or Custom')">
                <el-select v-model="move_ticket.move_type" :placeholder="translate('Want to move all or custom selected')" @change='showTicket'>
                    <el-option v-for="_move_type in moveTypes"
                               :key="_move_type" :value="_move_type"
                               :label="_move_type"></el-option>
                </el-select>
            </el-form-item>
        </el-form>

        <template v-if="!!show_ticket_selection">
            <div class="fs_all_tickets" v-if="has_pro">
                <div class="fs_box_wrapper">
                    <h4>Filter ticket</h4>
                    <hr/>
                    <div class="fs_box_head">
                        <el-form :data="filters" label-position="top">
                            <el-row :gutter="20">
                                <el-col :span="8">
                                    <el-form-item :label="translate('Select Customer')">
                                        <remote-selector
                                            v-model="filters.customer_id"
                                            response_key="customers"
                                            api_path="customers"
                                            value_selector="id"
                                            label_joiner=" - "
                                            :label_selectors="['full_name','email']"
                                            @change="customerChangeHandler"
                                            clearable
                                        />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="8">
                                    <el-form-item :label="translate('Select Product')">
                                        <remote-selector
                                            v-model="filters.product_id"
                                            response_key="products"
                                            api_path="products"
                                            value_selector="id"
                                            :label_selectors="['title']"
                                            @change="productChangeHandler"
                                            clearable
                                        />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="7">
                                    <el-form-item :label="translate('Ticket Title')">
                                        <el-input v-model="filters.ticket_title" :placeholder="translate('Filter by ticket title')" clearable
                                        @keyup="showTicket('Custom')" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </el-form>
                    </div>
                    <div class="fs_box_body">
                        <h4>{{translate('Select ticket that you want to move')}}</h4>
                        <hr/>
                        <el-table  :v-infinite-scroll="load" class="infinite-list" style="overflow: auto" :data="tickets" height="350" @selection-change="handleSelectionChange">
                            <el-table-column
                                type="selection"
                                width="55" :label="translate('Select All')">
                            </el-table-column>
                            <el-table-column min-width="300" :label="translate('Tickets')">
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
            <div class="fs_narrow_promo" v-else>
                <h3>{{ translate('move_ticket_by_selecting') }}</h3>
                <p>{{ translate('pro_promo') }}</p>
                <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{ translate('Upgrade To Pro') }}</a>
            </div>
        </template>

        <template #footer v-if="has_pro">
                <span class="dialog-footer">
                  <el-button @click="move_ticket.show_modal = false">{{ translate('Cancel') }}</el-button>
                  <el-button v-loading="moving" :disabled="moving" type="success"
                             @click="moveTicketMailBox()">{{ translate('Move') }} <span v-if="move_ticket.selected_tickets.length > 0"> ( {{move_ticket.selected_tickets.length}} )</span></el-button>
                </span>
        </template>
    </el-dialog>
</template>

<script type="text/babel">
    import Pagination from '../../Pieces/Pagination';
    import RemoteSelector from '../../Pieces/RemoteSelector';
    import each from "lodash/each";
    import { computed, onMounted, reactive, toRefs } from 'vue';
    import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

    export default {
        name: 'MoveTicket',
        components: {
            Pagination,
            RemoteSelector
        },
        props:['mailbox_id', 'mailboxes'],
        emits:['update_mailbox', 'reset_me'],

        setup(props, context){
            const {get, put, translate, handleError} = useFluentHelper();
            const { notify } = useNotify();

            const emit = context.emit;
            const state = reactive({
                count: 10,
                pagination: {
                    current_page: 1,
                    total: 0,
                    per_page: 10
                },
                move_ticket: {
                    show_modal: props.mailbox_id ? true : false,
                    box_id: props.mailbox_id,
                    fallback_box: '',
                    move_type: 'All',
                    selected_tickets: []
                },
                show_ticket_selection: false,
                tickets: [],
                filters: {
                    status_type: '',
                    product_id: '',
                    mailbox_id: props.mailbox_id,
                    customer_id: '',
                    ticket_title: ''
                },
                customers: [],
                products: [],
                filtered: false,
                moving: false,
                moveTypes: ['All', 'Custom']
            });

            const load = () => {
              state.count += 5;
            }

            const closeModal = () => {
                emit('reset_me');
            }

            const customerChangeHandler = (val) => {
              state.filters.customer_id = val;
              showTicket('Custom');
            }

            const productChangeHandler = (val) => {
                state.filters.product_id = val;
                showTicket('Custom');
            }
            const  showTicket = (moveType) => {
                if(moveType === 'Custom'){
                    let query = {
                        order_by: 'id',
                        order_type: 'DESC',
                        filter_type: 'simple',
                        filters: state.filters,
                        page: state.pagination.current_page,
                        per_page: state.pagination.per_page,
                    };

                    get(`mailboxes/${props.mailbox_id}/tickets`, query)
                        .then(response => {
                            state.tickets = response.tickets.data;
                            state.pagination.total = response.tickets.total;
                            state.show_ticket_selection = true;
                        })
                        .catch((errors) => {
                            handleError(errors);
                        })
                        .always(() => {
                            state.loading = false;
                            state.first_time_loading = false;
                        })
                }else{
                    state.move_ticket.selected_tickets = [];
                    state.show_ticket_selection = false;
                    state.pagination.total = 0;
                }

            }

            const setPage = (val) => {
                state.page = val
            }

            const handleSelectionChange = (selections) => {
                const selectionIds = [];
                each(selections, (selection) => {
                    selectionIds.push(selection.id);
                })
                state.move_ticket.selected_tickets = selectionIds;
            }

            const moveTicketMailBox = () => {
                if (!state.move_ticket.fallback_box || !state.move_ticket.box_id) {
                    alert(translate('Please provide fallback box'));
                    return false;
                }
                if (!state.move_ticket.move_type) {
                    alert(translate('Please provide You want to move all or custom selected tickets?'));
                    return false;
                }

                if (state.move_ticket.move_type == 'Custom' && Object.keys(state.move_ticket.selected_tickets).length < 1) {
                    alert(translate('Please select ticket first'));
                    return false;
                }

                state.moving = true;
                put(`mailboxes/${state.move_ticket.box_id}/move_tickets`, {
                    new_box_id: state.move_ticket.fallback_box,
                    move_type: state.move_ticket.move_type,
                    ticket_ids: state.move_ticket.selected_tickets
                })
                    .then(response => {
                        state.show_ticket_selection = false;
                        state.move_ticket = {
                            show_modal: false,
                            box_id: state.mailbox_id,
                            fallback_box: '',
                            move_type: 'All',
                            selected_tickets: []
                        };
                        notify({
                            type: 'success',
                            message: response.message,
                            position: 'bottom-right'
                        });
                        emit('update_mailbox');
                    })
                    .catch((errors) => {
                        handleError(errors);
                    })
                    .always(() => {
                        state.moving = false;
                    });
            }

            return{
                translate,
                ...toRefs(state),
                moveTicketMailBox
            }

        }

    }

</script>

<style>
    .el-table .cell {
        min-height: 23px;
    }
</style>
