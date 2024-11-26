<template>
    <div class="fs_tk_sidebar_wrap">
        <div v-if="ticket && ticket.customer" class="fs_tk_card fs_tk_profile_card">
            <div class="fs_tk_card_header">
                <div class="fs_avatar">
                    <router-link :to="{name: 'view_customer', params: { customer_id: ticket.customer.id }}">
                        <img :src="ticket.customer.photo" :alt="ticket.customer.full_name"/>
                    </router-link>
                </div>
                <el-icon v-if="appVars.me.permissions.includes('fst_sensitive_data')" style="float:right;" @click="customerManagement">
                    <More />
                </el-icon>

            </div>
            <div class="fs_tk_card_body">
                <div class="fs_tk_line">
                    <div class="fs_tk_profile_name">
                        {{ ticket.customer.full_name }}
                        <span style="color: red;" v-if="ticket.customer.status == 'inactive'">({{$t('Blocked')}})</span>
                    </div>
                </div>
                <div class="fs_tk_line">
                    <div class="fs_tk_contact_details">
                        <a rel="noopener" target="_blank" v-if="ticket.customer.profile_edit_url"
                           :href="ticket.customer.profile_edit_url">
                            {{ ticket.customer.email }}
                        </a>
                        <span v-else> {{ ticket.customer.email }}</span>
                        <p class="fs_customer_address">{{ getCustomerAddress(ticket.customer) }}</p>
                    </div>
                </div>
                <div v-if="ticket.customer.note" class="fs_customer_note">
                    {{ ticket.customer.note }}
                </div>

                <fluent-crm-profile v-if="fluentcrm_profile" :crm_profile="fluentcrm_profile" />

            </div>
        </div>

        <div class="text-center fs_tk_card" style="height: 100px" v-if="loading">
            <el-skeleton :rows="1" animated/>
        </div>

      <div v-if="has_pro && watchers.length" class="fs_tk_card fs_tk_watchers_card">
        <div class="fs_tk_card_header">
          <h3>{{ $t('Bookmarks') }} ({{ watchers.length }})</h3>
        </div>
        <div class="fs_tk_card_body">
          <el-tag
              v-for="(watcher,watcher_key) in watchers"
              :key="watcher_key"
              class="mx-1"
              size="small"
              closable
              :disable-transitions="false"
              @close="handleClose(watcher.id)"
          >
            {{ watcher.full_name}}
          </el-tag>

          <el-popover
              placement="bottom"
              :width="400"
              :visible="add_watcher"
              trigger="manual"
          >
            <template #reference>
                <el-button @click="add_watcher = !add_watcher" style="height: 20px; width: 8px; margin-left: 3px;">
                    <el-icon style="vertical-align: middle; font-size: 10px;"><Plus /></el-icon>
                </el-button>
            </template>

            <h4>{{$t('Add Bookmark')}}</h4>

            <el-select multiple v-model="watcherIds"
                       size="small">
              <el-option
                  v-for="(agent,agent_key) in agents"
                  :key="agent_key" :value="agent.id"
                  :label="agent.full_name"></el-option>
            </el-select>

            <el-button @click="updateWatcher()" type="primary" size="small"
                       style="margin-top: 20px">{{$t('Update')}}
            </el-button>

          </el-popover>

        </div>
      </div>

        <div v-if="extra_widgets" v-for="(widget,widget_key) in extra_widgets" :key="widget_key"
             :class="'fs_tk_widget_' + widget_key" class="fs_tk_card fs_tk_extra_card">
            <template v-if="widget_key === 'woo_purchases'">
                <div class="fs_tk_card_header">
                    <h3 v-html="widget.title"></h3>
                </div>
                <div class="fs_tk_card_body">
                    <ul>
                        <li v-for="(order,order_key) in widget.orders" :key="order_key">
                            <el-tooltip :content="`Purchase date: `+order.date+`, Amount: `+order.currency+order.total" placement="top" :raw-content="true">
                                <a @click="getOrderDetails(order, widget.products)" class="fs_wc_order_link">#{{order.id}}</a>
                            </el-tooltip>
                            &nbsp; - <el-tag class="ml-2" :type="getType(order.status)">{{order.status}}</el-tag>
                        </li>
                    </ul>
                </div>

                <el-drawer custom-class="fs_wc_order_details" v-model="drawer" size="50%" :with-header="false">
                    <el-card class="fs_wc_order_box-card">
                        <template #header>
                            <div class="fs_wc_card-header">
                                <h3>{{$t('Order')}} #{{orders.orderInfo.id}}</h3>
                                <el-tag class="ml-2" :type="getType(orders.orderInfo.status)">{{orders.orderInfo.status}}</el-tag>
                            </div>
                        </template>
                        <div class="fs_wc_card-body">
                            <el-row :gutter="20">
                                <el-col :span="12">
                                    <div class="fs_wc-order-preview-address">
                                        <h2>{{$t('Billing details')}}</h2>
                                        <p v-html="orders.orderInfo.billing_address"></p>
                                        <p><strong>{{$t('Email')}}: </strong>{{orders.orderInfo.email}}</p>
                                        <p><strong>{{$t('Phone')}}: </strong>{{orders.orderInfo.phone}}</p>
                                        <p><strong>{{$t('Payment Via')}}: </strong>{{orders.orderInfo.payment_method}}</p>
                                        <p><strong>{{$t('Purchase Date')}}: </strong>{{orders.orderInfo.date}}</p>
                                    </div>
                                </el-col>
                                <el-col :span="12">
                                    <div class="fs_wc-order-preview-address">
                                        <h2>{{$t('Shipping details')}}</h2>
                                        <p v-html="orders.orderInfo.shipping_address"></p>
                                        <p><strong>{{$t('Shipping method')}} </strong> {{orders.orderInfo.shipping_method}}</p>
                                    </div>
                                </el-col>
                            </el-row>

                            <el-row>
                                <el-table
                                    :data="orders.products"
                                    style="width: 100%; margin-top:2%;">
                                    <el-table-column prop="post_title" :label="$t('Product')" width="60%"/>
                                    <el-table-column prop="product_qty" :label="$t('Quantity')" width="20%"  align="center"/>
                                    <el-table-column label="Total" width="20%" align="center">
                                        <template #default="scope">
                                            <span v-html="orders.orderInfo.currency"></span><span>{{scope.row.product_gross_revenue}}</span>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-row>
                        </div>

                        <div class="fs_wc_card_footer">
                            <a :href="orders.orderInfo.order_link" target="_blank" class="el-button el-button--primary" type="primary" >{{$t('Edit')}}</a>
                            <el-button @click="cancelClick" type="danger">{{$t('Close')}}</el-button>
                        </div>
                    </el-card>
                </el-drawer>

            </template>
            <div class="fs_tk_card_header" v-else>
                <h3 v-html="widget.header"></h3>
            </div>
            <div class="fs_tk_card_body">
                <div v-html="widget.body_html"></div>
            </div>
        </div>

        <div v-if="other_tickets && other_tickets.length" class="fs_tk_card fs_tk_other_tickets_card">
            <div class="fs_tk_card_header">
                <h3>{{ $t('Previous Conversations') }} ({{ other_tickets.length }})</h3>
            </div>
            <div class="fs_tk_card_body">
                <ul>
                    <li v-for="other_ticket in other_tickets" :key="'other_ticket_'+other_ticket.id">
                        <router-link :to="{
                            name: 'view_ticket',
                            params: { ticket_id: other_ticket.id },
                            query: {prev_ticket: ticket_id}
                        }">
                            <el-icon> <Message /> </el-icon> {{ other_ticket.title }} <span class="fs_badge"
                                                                                           :class="'fs_badge_'+other_ticket.status">{{
                                other_ticket.status
                            }}</span>
                        </router-link>
                    </li>
                </ul>
            </div>
        </div>
        <el-dialog v-model="customerManagementModal" :title="$t('Customer Management')" class="fs_dialog">
            <el-tabs v-model="activeTabName" @tab-click="handleClick">
                <el-tab-pane :label="$t('Update Customer')" name="update_customer_data">
                    <customer-form @updated="closeModal" :customer="ticket.customer"/>
                </el-tab-pane>

                <el-tab-pane :label="$t('Change Customer')" name="change_customer">
                    <el-form :data="ticket" label-position="top">
                        <el-form-item :label="$t('Select Customer')">
                            <remote-selector
                                v-model="ticket.customer_id"
                                response_key="customers"
                                api_path="customers"
                                value_selector="id"
                                label_joiner=" - "
                                :label_selectors="['full_name','email']"
                            />
                        </el-form-item>
                    </el-form>

                    <el-form-item>
                        <el-button @click="changeCustomer(ticket.customer_id)" :disabled="changing" v-loading="changing"
                                   type="primary" size="small">
                            {{ $t('Change Customer') }}
                        </el-button>
                    </el-form-item>
                </el-tab-pane>
            </el-tabs>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import CustomerForm from "../Customers/_CustomerForm";
import RemoteSelector from "../../Pieces/RemoteSelector";
import FluentCrmProfile from './parts/_CrmProfile';
import {useFluentHelper, useNotify, useConfirm} from "@/admin/Composable/FluentFrameworkHelper";
import {onMounted, reactive, toRefs, watch} from "vue";

export default {
    name: 'TicketSidebar',
    components: {
        CustomerForm,
        RemoteSelector,
        FluentCrmProfile
    },
    props: ['ticket_id', 'ticket', 'fluentcrm_profile', 'watchers', 'watcher_ids', 'fetch_other_tickets'],
    emits: ['refresh'],
    setup(props, context){
        const {
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
        } = useFluentHelper();
        const emit = context.emit;
        const { notify } = useNotify();
        const { confirm } = useConfirm();
        const state = reactive({
            drawer: false,
            loading: true,
            extra_widgets: false,
            other_tickets: [],
            watcherIds: [],
            customerManagementModal: false,
            changing: false,
            activeTabName: 'update_customer_data',
            add_watcher: false,
            ticketSummary: '',
            ticketTone: '',
            agents: appVars.support_agents,
            orders: [],
        });

        const fetchWidgets = () => {
            state.loading = true;
            get(`tickets/${props.ticket_id}/widgets`, {
                with: ['other_tickets', 'extra_widgets']
            })
            .then(response => {
                state.other_tickets = response.other_tickets;
                state.extra_widgets = response.extra_widgets;
            })
            .catch((errors) => {
                handleError(errors);
            })
            .always(() => {
                state.loading = false;
            })
        }

        const customerManagement = () =>{
            state.customerManagementModal = !state.customerManagementModal;
        }

        const changeCustomer = (customer_id) => {
            put(`tickets/${props.ticket_id}/change-customer`, {
                customer: customer_id
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    closeModal();
                })
                .catch((errors) => {
                    handleError(errors)
                })
                .always(() => {
                    state.loading = false;
                })
        }

        const handleClick = (tab, event) => {
            state.activeTabName = tab.props.name;
        }

        const cancelClick = () =>{
            state.drawer = false;
        }

        const closeModal = () =>{
            state.customerManagementModal = false;
            window.location.reload();
        }

        const getCustomerAddress = (customer) => {
            if(!customer.custom_field_keys) {
                return '';
            }
            let address = [];

            customer.custom_field_keys.forEach((key) => {
                const value = customer[key];

                if (value) {
                    address.push(value);
                }
            });

            address = address.filter((item) => {
                return !!item;
            });

            return address.join(', ');
        }

        const handleClose = (watcherId) => {
            confirm({
                message: translate('watcher_remove_warning'),
                title: translate('Warning'),
                options: {
                    confirmButtonText: translate('Yes'),
                    cancelButtonText: translate('No'),
                    type: 'warning'
                }
            })
            .then(() => {
                const index = state.watcherIds.indexOf(watcherId.toString());
                if (index > -1) {
                    state.watcherIds.splice(index, 1);
                }
                state.saving = true;
                updateWatcher();
            })
            .catch(errors => {
                handleError(errors)
            })
        }

        const updateWatcher = () => {
            state.saving = true;
            post(`tickets/${props.ticket.id}/sync-watchers`, {
                watchers: state.watcherIds,
            })
                .then(response => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });

                    emit('refresh');

                    state.add_watcher = false;
                })
                .catch((errors) => {
                    handleError(errors);
                })
        }

        const getOrderDetails = (current_order, products) => {
            state.drawer = true;
            state.orders = {
                orderInfo: current_order,
                products: products[current_order.id],
            }
        }

        const getType = (status) => {
            switch(status.toLocaleString()) {
                case 'on-hold':
                    return 'warning';
                case 'processing':
                    return 'primary';
                case 'completed':
                    return 'success';
                default:
                    return 'info';
            }
        }

        watch(() => props.watcher_ids, (newIds) => {
            state.watcherIds = newIds;
        });

        watch(() => props.fetch_other_tickets, (newVal) => {
            fetchWidgets();
        });

        onMounted(() => {
            fetchWidgets();
            state.watcherIds = props.watcher_ids;
            if (has_pro) {
                state.watcherIds = props.ticket.watchers.map((watcher) => {
                    return watcher.tag_id.toString();
                });
            }
        });


        return {
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
            ...toRefs(state),
            fetchWidgets,
            customerManagement,
            changeCustomer,
            handleClick,
            cancelClick,
            closeModal,
            getCustomerAddress,
            handleClose,
            updateWatcher,
            getOrderDetails,
            getType,
        }
    }
}
</script>

<style lang="scss">
.fs_wc_order_box-card{
    margin-top: 20px;
}
.fs_wc_card-header, .fs_wc_card_footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.fs_wc_card_footer {
    margin-top: 50px;
    border-top: 1px solid #e4e7ed;
    padding-top: 30px;
}
.fs_wc_order_link{
    cursor: pointer;
}
</style>
