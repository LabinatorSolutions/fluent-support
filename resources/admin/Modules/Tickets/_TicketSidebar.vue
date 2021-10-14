<template>
    <div class="fs_tk_sidebar_wrap">
        <div v-if="ticket && ticket.customer" class="fs_tk_card fs_tk_profile_card">
            <div class="fs_tk_card_header">
                <div class="fs_avatar">
                    <a v-if="ticket.customer.profile_edit_url" :href="ticket.customer.profile_edit_url">
                        <img :src="ticket.customer.photo" :alt="ticket.customer.full_name"/>
                    </a>
                    <img v-else :src="ticket.customer.photo" :alt="ticket.customer.full_name"/>
                </div>
            </div>
            <div class="fs_tk_card_body">
                <div class="fs_tk_line">
                    <div class="fs_tk_profile_name">{{ ticket.customer.full_name }}</div>
                </div>
                <div class="fs_tk_line">
                    <div class="fs_tk_contact_details">
                        {{ ticket.customer.email }}
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center fs_tk_card" style="height: 100px" v-if="loading">
            <el-skeleton :rows="1" animated />
        </div>

        <div v-if="extra_widgets" v-for="(widget,widget_key) in extra_widgets" :key="widget_key" :class="'fs_tk_widget_' + widget_key" class="fs_tk_card fs_tk_extra_card">
            <div class="fs_tk_card_header">
                <h3 v-html="widget.header"></h3>
            </div>
            <div class="fs_tk_card_body">
                <div v-html="widget.body_html"></div>
            </div>
        </div>

        <div v-if="other_tickets.length" class="fs_tk_card fs_tk_other_tickets_card">
            <div class="fs_tk_card_header">
                <h3>Previous Conversations ({{other_tickets.length}})</h3>
            </div>
            <div class="fs_tk_card_body">
                <ul>
                    <li v-for="other_ticket in other_tickets" :key="'other_ticket_'+other_ticket.id">
                        <router-link :to="{
                            name: 'view_ticket',
                            params: { ticket_id: other_ticket.id },
                            query: {prev_ticket: ticket_id}
                        }">
                            <i class="el-icon-message"></i> {{ other_ticket.title }} <span class="fs_badge" :class="'fs_badge_'+other_ticket.status">{{other_ticket.status}}</span>
                        </router-link>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</template>

<script type="text/babel">
export default {
    name: 'TicketSidebar',
    props: ['ticket_id', 'ticket'],
    data() {
        return {
            loading: true,
            extra_widgets: false,
            other_tickets: []
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
        }
    },
    mounted() {
        this.fetchWidgets();
    }
}
</script>
