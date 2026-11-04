<template>
    <div class="fs_time_sheet" v-if="has_loaded">
        <div v-loading="!isLoaded" class="fs_table_wrapper fs_scrollable_table">
            
            <el-table 
                :data="tasks"
                row-class-name="fs_table_row"
                header-row-class-name="fs_table_header_row"
                cell-class-name="fs_table_cell"
                header-cell-class-name="fs_table_header_cell">
                <el-table-column fixed="left" prop="ticket" :label="$t('Ticket')" min-width="120">
                    <template #default="{ row }">
                        <router-link
                            :to="{ name: 'view_ticket', params: { ticket_id: row.id } }"
                            target="_blank"
                            class="fs_time_sheet_ticket_link">
                            #{{ row.id }}
                        </router-link>
                    </template>
                </el-table-column>
                <el-table-column :min-width="100" v-for="date in dateLabels" :key="date" :prop="date" :label="date" min-width="100">
                    <template #header="{ column }">
                        <span>{{ smartDate(column.label) }}</span>
                    </template>
                    <template #default="{ row }">
                        <TicketDateSheetPop :ticket_id="row.id" :date="date" :timeSheets="timeSheets" />
                    </template>
                </el-table-column>
                <el-table-column fixed="right" :label="$t('Total')" width="160">
                    <template #header="{ column }">
                        <span>{{ $t('Total') }} ({{ formatMinutes(totalMinutes) }})</span>
                    </template>
                    <template #default="{ row }">
                        <strong>{{ getTicketTotal(row.id) }}</strong>
                    </template>
                </el-table-column>
            </el-table>
        </div>
    </div>
    <div class="fs_time_sheet_skeleton" v-else>
        <TableSkeleton :rows="8" :columns="['120px', 1, 1, 1, 1, 1, 1, '160px']" />
    </div>
</template>

<script type="text/babel">
import TicketDateSheetPop from './_TicketDateSheetPop.vue';
import TableSkeleton from "@/admin/Components/TableSkeleton.vue";
import Modal from "@/admin/Pieces/Modal.vue";
import {timesheetUtils} from "@/admin/Modules/Reports/TimeSheet/Pieces/TimeSheetUtils";

export default {
    name: 'ByTickets',
    components: {Modal, TicketDateSheetPop, TableSkeleton},
    props: {
        date_range: {
            type: Array,
            required: true
        },
        tasks: {
            type: Array,
            default: () => []
        },
        timeSheets: {
            type: Object,
            default: () => ({})
        },
        dateLabels: {
            type: Array,
            default: () => []
        },
        totalMinutes: {
            type: Number,
            default: 0
        },
        isLoaded: {
            type: Boolean,
            default: false
        },
        selectedMailBoxes: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            // Stays true after the first load, so later fetches keep the table and show an overlay.
            has_loaded: this.isLoaded
        };
    },
    watch: {
        isLoaded(value) {
            if (value) {
                this.has_loaded = true;
            }
        }
    },
    methods: {
        getTicketTotal(ticket) {
            return timesheetUtils.calculateEntityTotalMinutes(this.dateLabels, this.timeSheets, ticket.id);
        },

        formatMinutes(minutes) {
            return timesheetUtils.formatMinutes(minutes);
        },

        exportReport() {
            timesheetUtils.exportReport({
                selectedItems: this.selectedMailBoxes,
                dateRange: this.date_range,
                action: "fluent_support_export_tickets_timesheet",
                itemKey: "mailbox_id"
            });
        },
    },
};
</script>
