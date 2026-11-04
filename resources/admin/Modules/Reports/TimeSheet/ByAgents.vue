<template>
    <div class="fs_time_sheet" v-if="has_loaded">
        <div v-loading="!isLoaded" class="fs_table_wrapper">
            
            <el-table :data="agents" 
                row-class-name="fs_table_row"
                header-row-class-name="fs_table_header_row"
                cell-class-name="fs_table_cell"
                header-cell-class-name="fs_table_header_cell"
                table-layout="fixed">
                <el-table-column fixed="left" prop="agent" :label="$t('Agent')" min-width="150">
                    <template #default="{ row }">
                        <div class="fs_time_sheet_person">
                            <el-avatar :src="row.photo" size="small"></el-avatar>
                            <a
                                :href="`/wp-admin/user-edit.php?user_id=${row.user_id}`"
                                target="_blank"
                                class="fs_time_sheet_agent_link">
                                {{ row.full_name }}
                            </a>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column
                    :min-width="100"
                    v-for="date in dateLabels"
                    :key="date"
                    :prop="date"
                    :label="date"
                >
                    <template #header="{ column }">
                        <span>{{ smartDate(column.label) }}</span>
                    </template>
                    <template #default="{ row }">
                        <UserAgentDateSheetPop
                            :user_id="row.id"
                            :date="date"
                            :timeSheets="timeSheets"
                        />
                    </template>
                </el-table-column>
                <el-table-column fixed="right" :label="$t('Total')" width="160">
                    <template #header="{ column }">
                        <span>{{ $t('Total') }} ({{ formatMinutes(totalMinutes) }})</span>
                    </template>
                    <template #default="{ row }">
                        <strong>{{ getUserTotal(row.id) }}</strong>
                    </template>
                </el-table-column>
            </el-table>
        </div>
    </div>

    <div class="fs_time_sheet_skeleton" v-else>
        <TableSkeleton :rows="8" :columns="['150px', 1, 1, 1, 1, 1, 1, '160px']" avatar />
    </div>
</template>


<script>
import UserAgentDateSheetPop from './_UserAgentDateSheetPop.vue'
import TableSkeleton from "@/admin/Components/TableSkeleton.vue";
import {timesheetUtils}from "@/admin/Modules/Reports/TimeSheet/Pieces/TimeSheetUtils"
import Modal from "@/admin/Pieces/Modal.vue";

export default {
    name: 'ByAgents',
    components: {Modal, UserAgentDateSheetPop, TableSkeleton},
    props: {
        date_range: {
            type: Array,
            required: true
        },
        agents: {
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
        selectedAgent: {
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
        getUserTotal(userId) {
            return timesheetUtils.calculateEntityTotalMinutes(this.dateLabels, this.timeSheets, userId);
        },

        formatMinutes(minutes) {
            return timesheetUtils.formatMinutes(minutes);
        },

        exportReport() {
            timesheetUtils.exportReport({
                selectedItems: this.selectedAgent,
                dateRange: this.date_range,
                action: "fluent_support_export_agents_timesheet",
                itemKey: "selectedAgents"
            });
        },
    },
};
</script>


