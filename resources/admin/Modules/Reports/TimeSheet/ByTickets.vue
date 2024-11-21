<template>
    <div class="fs_time_sheet_box_header">
        <div class="fs_time_sheet_box_head">
            {{"Time Sheet Report By Tickets"}}
        </div>
        <div class="fs_time_sheet_box_actions">
            <el-select
                v-model="selectedMailBoxes"
                @change="fetchReportsByTickets"
                placeholder="Select a Mailbox"
                clearable
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
                <el-button type="default" @click="openSettings = true">
                    <el-icon>
                        <Download/>
                    </el-icon>
                    {{ translate('Export') }}
                </el-button>
            </div>
        </div>
    </div>
    <div class="fs_time_sheet_box_actions">
        <el-table v-if="isLoaded" :data="tasks" style="width: 100%">
            <el-table-column fixed="left" prop="ticket" label="Ticket" min-width="120">
                <template #default="{ row }">
                    <strong>{{ row.ticket.title }}</strong>
                </template>
            </el-table-column>
            <el-table-column :min-width="100" v-for="date in dateLabels" :key="date" :prop="date" :label="date" min-width="100">
                <template #header="{ column }">
                    <span>{{ smartDate(column.label) }}</span>
                </template>
                <template #default="{ row }">
                    <TicketDateSheetPop :ticket_id="row.ticket.id" :date="date" :timeSheets="timeSheets" />
                </template>
            </el-table-column>
            <el-table-column label="Total" width="160" fixed="right">
                <template #header="{ column }">
                    <span>Total ({{ formatMinutes(totalMinutes) }})</span>
                </template>
                <template #default="{ row }">
                    <strong>{{ getTicketTotal(row.ticket) }}</strong>
                </template>
            </el-table-column>

            <Teleport to="body">
                <modal :show="openSettings" :title="translate('Include Customers in Time Sheet')"
                       @close="openSettings = false">
                    <template #body>
                        <div class="fs_summary_settings">
                            <el-row :gutter="20">
                                <el-col :span="18">
                                    <span> {{ translate("If you don't select any customer then all the customers report will be displayed here otherwise it will show only selected agents report.") }}</span>
                                </el-col>
                            </el-row>

                            <el-transfer
                                v-model="includeOrExcludeMailBoxes"
                                :data="allMailBoxes"
                                :titles="['Available Mail Boxes', 'Selected Mail Boxes']"
                                filterable
                            />
                        </div>
                    </template>
                    <template #footer>
                        <el-button @click="openSettings = false">{{ translate('Cancel') }}</el-button>
                        <el-button type="primary" @click="exportReport">{{ translate('Export') }}</el-button>
                    </template>
                </modal>
            </Teleport>
        </el-table>
        <el-skeleton :animated="true" :rows="10" v-else />
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
        const mailBoxes = ref([]);
        const allMailBoxes = ref([]);
        const totalMinutes = ref(0);
        const timeSheets = ref({});
        const dateLabels = ref([]);
        const isLoaded = ref(false);
        const openSettings = ref(false);
        const selectedMailBoxes = ref([]);
        const includeOrExcludeMailBoxes = ref([]);

        const fetchReportsByTickets = async () => {
            isLoaded.value = false;
            const params = {
                mailbox_id: selectedMailBoxes.value,
                date_range: props.date_range
            };
            try {
                const response = await get('reports/timesheet/by-tickets', params);
                tasks.value = response.tasks;
                mailBoxes.value = response.mail_boxes;
                timeSheets.value = response.time_sheets;
                dateLabels.value = response.date_labels;
                totalMinutes.value = response.total_minutes;

                if (!params.mailbox_id) {
                    allMailBoxes.value = response.mail_boxes.map(mailBox => ({
                        key: mailBox.id,
                        label: mailBox.name
                    }));
                }

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
            timesheetUtils.exportReport({
                selectedItems: includeOrExcludeMailBoxes.value,
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
            openSettings,
            mailBoxes,
            allMailBoxes,
            selectedMailBoxes,
            includeOrExcludeMailBoxes,
            exportReport,
            translate
        };
    }
};
</script>
