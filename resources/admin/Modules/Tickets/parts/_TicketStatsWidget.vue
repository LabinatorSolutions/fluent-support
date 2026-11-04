<template>
    <collapsible-widget
        v-if="rows.length"
        :title="$t('Ticket Stats')"
        icon="DataAnalysis"
        :default-expanded="true"
        class="fs_sidebar_widget fs_ticket_stats_widget"
    >
        <dl class="fs_ticket_stats">
            <div v-for="row in rows" :key="row.key" class="fs_ticket_stat">
                <dt>{{ row.label }}</dt>
                <dd>
                    <span class="fs_ticket_stat_value">{{ row.value }}</span>
                    <span v-if="row.hint" class="fs_ticket_stat_hint">{{ row.hint }}</span>
                </dd>
            </div>
        </dl>
    </collapsible-widget>
</template>

<script>
import CollapsibleWidget from './_CollapsibleWidget.vue';
import { buildTicketStats } from './ticketStats';

export default {
    name: 'TicketStatsWidget',
    components: { CollapsibleWidget },
    props: {
        ticket: { type: Object, required: true }
    },
    computed: {
        rows() {
            return buildTicketStats(this.ticket, {
                $t: this.$t,
                sourceLabel: (source) => this.$sourceLabel(source),
                formatDate: (date) => this.dateTimeFormat(date, 'DD MMM YYYY, hh:mm A')
            });
        }
    }
};
</script>
