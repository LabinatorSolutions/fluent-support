<template>
    <div class="fs_time_sheet"  v-if="isLoaded">
        <div class="fs_time_sheet_box_header">
            <div class="fs_time_sheet_box_head">
                {{translate("Time Sheet Report By Tickets")}}
            </div>
            <div class="fs_time_sheet_box_actions">
                <el-select
                    v-model="selectedMailBoxes"
                    @change="fetchReportsByTickets"
                    placeholder="Select Mailboxes"
                    clearable
                    filterable
                    multiple
                    class="fs_time_sheet_select"
                >
                    <el-option
                        v-for="mailBox in allMailBoxes"
                        :key="mailBox.key"
                        :label="mailBox.label"
                        :value="mailBox.key"
                    />
                </el-select>

                <div class="fs_time_sheet_export">
                    <el-button type="default" @click="exportReport">
                        <el-icon>
                            <Download/>
                        </el-icon>
                        {{ translate('Export') }}
                    </el-button>
                </div>
            </div>
        </div>
        <div class="fs_time_sheet_box_actions">
            <el-table :data="tasks" style="width: 100%">
                <el-table-column fixed="left" prop="ticket" label="Ticket" min-width="120">
                    <template #default="{ row }">
                        <router-link
                            :to="{ name: 'view_ticket', params: { ticket_id: row.id } }"
                            target="_blank"
                            style="text-decoration: none;">
                            <strong>{{ row.title }}</strong>
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
                <el-table-column label="Total" width="160" fixed="right">
                    <template #header="{ column }">
                        <span>Total ({{ formatMinutes(totalMinutes) }})</span>
                    </template>
                    <template #default="{ row }">
                        <strong>{{ getTicketTotal(row.id) }}</strong>
                    </template>
                </el-table-column>
            </el-table>
        </div>
    </div>
    <div class="fs_time_sheet_skeleton"  v-else>
        <el-skeleton :animated="true" :rows="10"  />
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import TicketDateSheetPop from './_TicketDateSheetPop.vue';
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import Modal from "@/admin/Pieces/Modal.vue";
import {timesheetUtils} from "@/admin/Modules/Reports/TimeSheet/Pieces/TimeSheetUtils";

export default {
    name: 'ByTickets',
    components: {Modal, TicketDateSheetPop },
    props: {
        date_range: {
            type: Array,
            required: true
        }
    },
    setup(props, { emit }) {
        const { get, translate, handleError, smartDate } = useFluentHelper();
        const tasks = ref([]);
        const allMailBoxes = ref([]);
        const totalMinutes = ref(0);
        const timeSheets = ref({});
        const dateLabels = ref([]);
        const isLoaded = ref(false);
        const selectedMailBoxes = ref([]);

        const fetchReportsByTickets = async () => {
            isLoaded.value = false;
            const params = {
                mailbox_id: selectedMailBoxes.value,
                date_range: props.date_range
            };
            try {
                const response = await get('reports/timesheet/by-tickets', params);
                tasks.value = response.tickets;
                timeSheets.value = response.time_sheets;
                dateLabels.value = response.date_labels;
                totalMinutes.value = response.total_minutes;

                allMailBoxes.value = response.mail_boxes.map(mailBox => ({
                    key: mailBox.id,
                    label: mailBox.name
                }));

            } catch (error) {
                handleError(error)
            } finally {
                isLoaded.value = true;
            }
        };

        const getTicketTotal = (ticket) =>
            timesheetUtils.calculateEntityTotalMinutes(dateLabels.value, timeSheets.value, ticket.id);


        const formatMinutes = (minutes) =>
            timesheetUtils.formatMinutes(minutes);


        const exportReport = () => {
            console.log("I am here")
            timesheetUtils.exportReport({
                selectedItems: selectedMailBoxes.value,
                dateRange: props.date_range,
                action: "fluent_support_export_tickets_timesheet",
                itemKey: "mailbox_id"
            });
        }

        onMounted(fetchReportsByTickets);

        return {
            tasks,
            totalMinutes,
            timeSheets,
            dateLabels,
            isLoaded,
            fetchReportsByTickets,
            getTicketTotal,
            formatMinutes,
            smartDate,
            allMailBoxes,
            selectedMailBoxes,
            exportReport,
            translate
        };
    }
};
</script>
