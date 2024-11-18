<template>
    <el-table v-if="isLoaded" :data="tasks" style="width: 100%">
        <el-table-column fixed="left" prop="task" label="Task" min-width="120">
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
                <strong>{{ getTaskTotal(row.ticket) }}</strong>
            </template>
        </el-table-column>
    </el-table>
    <el-skeleton :animated="true" :rows="10" v-else />
</template>

<script>
import { ref, onMounted } from 'vue';
import TicketDateSheetPop from './_TicketDateSheetPop.vue';
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'ByTickets',
    components: { TicketDateSheetPop },
    props: {
        mailbox_id: {
            type: String,
            required: true
        },
        date_range: {
            type: Array,
            required: true
        }
    },
    setup(props, { emit }) {
        const { get, translate, handleError, setTitle, appVars, smartDate } = useFluentHelper();
        const tasks = ref([]);
        const totalMinutes = ref(0);
        const timeSheets = ref({});
        const dateLabels = ref([]);
        const isLoaded = ref(false);

        const fetchReportsByTickets = async () => {
            isLoaded.value = false;
            try {
                const response = await get('reports/timesheet/by-tickets', {
                    mailbox_id: props.mailbox_id,
                    date_range: props.date_range
                });
                tasks.value = response.tasks;
                timeSheets.value = response.time_sheets;
                dateLabels.value = response.date_labels;
                totalMinutes.value = response.totalMinutes;
            } catch (error) {
                emit('handleError', error);
            } finally {
                isLoaded.value = true;
            }
        };

        const getTaskTotal = (task) => {
            const minutes = dateLabels.value.reduce((acc, date) => {
                const sheets = timeSheets.value?.[date]?.[task.id] || [];
                return acc + sheets.reduce((acc, sheet) => acc + sheet.billable_minutes, 0);
            }, 0);
            return formatMinutes(minutes);
        };

        const formatMinutes = (minutes) => {
            let intMinutes = parseInt(minutes);
            if (!intMinutes) return '--';

            const hours = Math.floor(intMinutes / 60);
            intMinutes %= 60;

            if (!hours) return `${intMinutes}m`;
            if (!intMinutes) return `${hours}h`;
            return `${hours}h ${intMinutes}m`;
        };

        onMounted(fetchReportsByTickets);

        return {
            tasks,
            totalMinutes,
            timeSheets,
            dateLabels,
            isLoaded,
            fetchReportsByTickets,
            getTaskTotal,
            formatMinutes,
            smartDate
        };
    }
};
</script>
