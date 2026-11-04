<template>
    <div 
        class="fs_ticket_item"
        @click="handleTicketClick"
        style="cursor: pointer;"
    >
        <!-- Row 1: Title, Author, Ticket ID, Source -->
        <div class="fs_ticket_title_row">
            <el-checkbox
                v-if="showCheckbox"
                @click.stop
                :model-value="isSelected"
                @change="handleSelection"
                class="fs_ticket_checkbox"
            />
            <h3 class="fs_ticket_title">{{ ticket.title }}</h3>
            <span class="fs_ticket_author" v-if="ticket.customer">by {{ ticket.customer.first_name || ticket.customer.email }}</span>
            <span class="fs_ticket_id">#{{ ticket.id }}</span>
            <span v-if="ticket.status === 'new'" class="fs_status_dot fs_status_dot_new"></span>
            <span v-if="ticket.source" v-tip="$sourceLabel(ticket.source)" class="fs_ticket_source">
                <DynamicIcon
                    v-if="$sourceIconName(ticket.source)"
                    :name="$sourceIconName(ticket.source)"
                    :label="$sourceLabel(ticket.source)"
                />
                <img
                    v-else-if="$getSourceIcon(ticket.source)"
                    :src="$getSourceIcon(ticket.source)"
                    :alt="$sourceLabel(ticket.source)"
                >
                <span v-else class="fs_ticket_source_text">{{ $sourceLabel(ticket.source) }}</span>
            </span>
        </div>
        
        <!-- Row 2: Status, Priority, Waiting Time (left) | Tags (right) -->
        <div class="fs_ticket_meta_row">
            <div class="fs_ticket_meta_left">
                <!-- Status Badge -->
                <!-- translators: %s is a status label (a ticket status, or Published/Draft for a workflow) -->
                <span v-if="ticket.status !== 'new'" v-tip="$t('Status: %s', appVars.ticket_statuses[ticket.status] || ticket.status)" class="fs_status_badge" :class="`fs_status_${(ticket.status || '').toLowerCase()}`">
                {{ appVars.ticket_statuses[ticket.status] || ticket.status }}
                </span>

                <!-- Priority -->
                <!-- translators: %s is the priority the customer set on the ticket -->
                <span v-if="ticket.client_priority" v-tip="$t('Client Priority: %s', appVars.client_priorities[ticket.client_priority] || ticket.client_priority)" class="fs_priority_indicator">
                <span class="fs_priority_dot" :class="`fs_priority_dot_${(ticket.client_priority || 'normal').toLowerCase()}`"></span>
                <span class="fs_priority_text">{{ appVars.client_priorities[ticket.client_priority] || ticket.client_priority }}</span>
                </span>

                <!-- Waiting Time -->
                <!-- translators: %s is the date and time the ticket started waiting for a reply -->
                <span v-if="ticket.waiting_since" v-tip="$t('Waiting Time: %s', ticket.waiting_since)" class="fs_waiting_time">
                <IconPack iconKey="clock" :width="16" :height="16" />
                <span class="fs_waiting_text">{{ $timeDiff(ticket.waiting_since) }}</span>
                </span>
            </div>
            
            <div class="fs_ticket_meta_right">
                <!-- Tags -->
                <ticket-tags
                    v-if="ticket.tags && ticket.tags.length"
                    :tags="ticket.tags"
                    :ticket_id="ticket.id"
                    mode="ticket_list"
                    class="fs_ticket_tags"
                    @click.stop
                />
            </div>
        </div>
    </div>
</template>

<script>
import TicketTags from '../Modules/Tickets/parts/_Tags';
import IconPack from '../Components/IconPack';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

export default {
    name: 'Ticket',
    components: {
        DynamicIcon,
        TicketTags,
        IconPack
    },
    props: {
        ticket: {
            type: Object,
            required: true
        },
        showCheckbox: {
            type: Boolean,
            default: false
        },
        isSelected: {
            type: Boolean,
            default: false
        },
        assetUrl: {
            type: String,
            default: '/wp-content/plugins/fluent-support-dev/assets/'
        }
    },
    methods: {
        handleTicketClick() {
            this.$emit('ticket-click', this.ticket, event);
        },
        
        handleSelection(value) {
            this.$emit('ticket-select', this.ticket.id, value);
        }
    }
};
</script>