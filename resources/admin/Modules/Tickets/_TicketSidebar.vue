<template>
    <div class="fs_tk_sidebar_wrap">
        <div v-if="ticket && ticket.customer" class="fs_tk_card fs_tk_profile_card">
            <div class="fs_tk_card_header">
                <div class="fs_avatar">
                    <router-link :to="{name: 'view_customer', params: { customer_id: ticket.customer.id }}">
                        <img :src="ticket.customer.photo" :alt="ticket.customer.full_name"/>
                    </router-link>
                </div>
                <i class="el-icon-more" style="float:right;" @click="customerManagement"></i>
            </div>
            <div class="fs_tk_card_body">
                <div class="fs_tk_line">
                    <div class="fs_tk_profile_name">
                        {{ ticket.customer.full_name }}
                        <span style="color: red;" v-if="ticket.customer.status == 'inactive'">(Blocked)</span>
                    </div>
                </div>
                <div class="fs_tk_line">
                    <div class="fs_tk_contact_details">
                        <a rel="noopener" target="_blank" v-if="ticket.customer.profile_edit_url"
                           :href="ticket.customer.profile_edit_url">
                            {{ ticket.customer.email }}
                        </a>
                        <span v-else> {{ ticket.customer.email }}</span>
                        <p class="fs_customer_address">{{getCustomerAddress(ticket.customer)}}</p>
                    </div>
                </div>
                <div v-if="ticket.customer.note" class="fs_customer_note">
                    {{ ticket.customer.note }}
                </div>
            </div>
        </div>

        <div class="text-center fs_tk_card" style="height: 100px" v-if="loading">
            <el-skeleton :rows="1" animated/>
        </div>

        <div v-if="extra_widgets" v-for="(widget,widget_key) in extra_widgets" :key="widget_key"
             :class="'fs_tk_widget_' + widget_key" class="fs_tk_card fs_tk_extra_card">
            <div class="fs_tk_card_header">
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
                            <i class="el-icon-message"></i> {{ other_ticket.title }} <span class="fs_badge"
                                                                                           :class="'fs_badge_'+other_ticket.status">{{ other_ticket.status }}</span>
                        </router-link>
                    </li>
                </ul>
            </div>
        </div>
        <el-dialog v-model="customerManagementModal" :title="$t('Customer Management')">
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
                                   type="primary" size="mini">
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

export default {
    name: 'TicketSidebar',
    components: {
        CustomerForm, RemoteSelector
    },
    props: ['ticket_id', 'ticket'],
    data() {
        return {
            loading: true,
            extra_widgets: false,
            other_tickets: [],
            customerManagementModal: false,
            changing: false,
            activeTabName: 'update_customer_data'
        }
    },
    methods: {
        fetchWidgets() {
            this.loading = true;
            this.$get(`tickets/${this.ticket_id}/widgets`, {
                with: ['other_tickets', 'extra_widgets']
            })
                .then(response => {
                    this.other_tickets = response.other_tickets;
                    this.extra_widgets = response.extra_widgets;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                })
        },
        customerManagement() {
            this.customerManagementModal = !this.customerManagementModal;
        },
        changeCustomer(customer_id) {
            this.$put(`tickets/${this.ticket_id}/change-customer`, {
                customer: customer_id
            })
                .then((response) => {
                    this.$notify.success(response.message);
                    this.closeModal();
                })
                .catch((errors) => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.loading = false;
                })
        },
        handleClick(tab, event) {
            this.activeTabName = tab.props.name;
        },
        closeModal() {
            this.customerManagementModal = false;
            window.location.reload();
        },
        getCustomerAddress(customer) {
            let address = [
                customer.address_line_1,
                customer.address_line_2,
                customer.city,
                customer.state,
                customer.zip,
                customer.country
            ];

            address = address.filter((item) => {
                return !!item;
            });

            return address.join(', ');
        }
    },
    mounted() {
        this.fetchWidgets();
    }
}
</script>
