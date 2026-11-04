<template>
    <ul class="fs_prev_ticket_list" role="list">
        <li v-for="ticket in tickets" :key="'prev_ticket_' + ticket.id" class="fs_prev_ticket_item">
            <router-link
                :to="{ name: 'view_ticket', params: { ticket_id: ticket.id } }"
                class="fs_prev_ticket_link"
                :title="ticket.title"
                :aria-label="ticketLabel(ticket)"
            >
                <span class="fs_prev_ticket_title">{{ ticket.title }}</span>
                <span class="fs_prev_ticket_meta">
                    <span class="fs_prev_ticket_status" :class="'fs_prev_status_' + ticket.status">
                        {{ statusLabel(ticket.status) }}
                    </span>
                    <span class="fs_prev_ticket_time" :title="$dayjs(ticketDate(ticket)).format('D MMM YYYY, HH:mm')">
                        {{ $dayjs(ticketDate(ticket)).format('MMM D, YYYY') }}
                    </span>
                </span>
            </router-link>
        </li>
    </ul>
</template>

<script type="text/babel">
// Ticket rows for the Previous Conversations card (ticket sidebar and customer page).
export default {
    name: 'PreviousTicketList',
    props: {
        tickets: {
            type: Array,
            default: () => []
        }
    },
    methods: {
        statusLabel(status) {
            return this.appVars.ticket_statuses?.[status] || this.ucFirst(status || '');
        },
        ticketDate(ticket) {
            return ticket.updated_at || ticket.created_at;
        },
        ticketLabel(ticket) {
            // translators: 1: ticket title, 2: ticket status, 3: ticket number, 4: relative time such as "2 days ago"
            return this.$t('%1$s, %2$s, ticket #%3$s, updated %4$s', ticket.title, this.statusLabel(ticket.status), ticket.display_ticket_number || ticket.id, this.$timeDiff(this.ticketDate(ticket)));
        }
    }
};
</script>
