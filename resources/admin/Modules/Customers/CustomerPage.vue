<template>
    <div class="fs_customer_page" v-if="customer">
        <div class="fs_customer_page_header">
            <div class="fs_customer_photo">
                <img :src="customer.photo">
            </div>
            <div class="fs_customer_info">
                <h3>{{customer.full_name}}</h3>
                <p>{{customer.email}}</p>
            </div>
        </div>
        <div class="fs_customer_desc">
            <customer-form :customer="customer" />
            <div class="fs_customer_tickets">
                <h3>Tickets ({{tickets.length}})</h3>
                <ul>
                    <li v-for="ticket in tickets">
                        <router-link :to="{name: 'view_ticket', params:{'ticket_id': ticket.id}}">
                            <h4>{{ticket.title}}</h4>
                        </router-link>
                        <p>{{ getExcerpt(ticket) }}</p>
                    </li>
                </ul>
                <div style="padding-bottom: 20px" class="fframe_pagination_wrapper">
                    <pagination @fetch="fetchCustomersTickets" :pagination="pagination"/>
                </div>
            </div>
        </div>
    </div>
    <div class="fs_padded_20" v-else>
        <el-skeleton :rows="7" animated />
    </div>
</template>

<script>
import CustomerForm from './_CustomerForm';
import Pagination from "../../Pieces/Pagination";

export default {
    props: ['customer_user_id', 'customer_id', 'customer_email'],
    name: "CustomerPage",
    components: {CustomerForm, Pagination},
    data() {
        return {
            loading: false,
            customer: {},
            tickets: {},
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            }
        }
    },
    watch: {
        '$route.query.customer_user_id'(customerId) {
            if (customerId) {
                this.doAction('customer_user_id_view_exit', this.customer.id);
                this.customer = false;
                this.$nextTick(() => {
                    this.doAction('customer_user_id_view_entered', customerId);
                    this.fetchCustomer();
                });
            }
        },
        '$route.query.customer_email'(customerEmail) {
            if (customerEmail) {
                this.doAction('customer_email_view_exit', this.customer.email);
                this.customer = false;
                this.$nextTick(() => {
                    this.doAction('customer_email_view_entered', customerEmail);
                    this.fetchCustomer();
                });
            }
        },
        '$route.query.customer_id'(customer) {
            if (customer) {
                this.doAction('customer_id_view_exit', this.customer.id);
                this.customer = false;
                this.$nextTick(() => {
                    this.doAction('customer_id_view_entered', customer);
                    this.fetchCustomersTickets();
                });
            }
        }
    },
    methods: {
       fetchCustomer() {
           this.loading = !this.loading;
           this.$get('customers/customer_page',{
               customer_user_id: this.customer_user_id,
               customer_email: this.customer_email
           })
           .then(response => {
               this.loading = !this.loading
               this.customer = response;
           })
           .catch(errors => {
               this.$handleError(errors);
           })
           .always(() => {
               this.loading = !this.loading;
           })
       },
        fetchCustomersTickets() {
           this.loading = !this.loading;
           this.$get('tickets',{
               search: `customer_id:${this.customer_id}`,
               per_page: this.pagination.per_page,
               page: this.pagination.current_page
           })
            .then(response=>{
                this.tickets = response.tickets.data;
                this.pagination.total = response.tickets.total;
            })
            .catch(errors => {
                this.$handleError(errors)
            })
            .always(() => {
                this.loading = false;
            })
        },
        getExcerpt(row) {
            let text = (row.content) ? row.content : row.content;

            if (!text) {
                return '';
            }
            return text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "");
        },
    },
    mounted() {
        this.fetchCustomer();
        this.fetchCustomersTickets();
        this.doAction('customer_email_view_entered', this.customer_email, this);
        this.doAction('customer_id_view_entered', this.customer_id, this);
        this.doAction('customer_user_id_view_entered', this.customer_id, this);
    },
    beforeUnmount() {
        this.doAction('customer_email_view_exit', this.customer_email);
        this.doAction('customer_id_view_exit', this.customer_id);
        this.doAction('customer_user_id_exit', this.customer_id);
    }
}
</script>
