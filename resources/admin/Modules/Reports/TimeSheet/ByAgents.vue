<template>
    <el-table v-if="isLoaded" :data="agents" style="width: 100%">
        <el-table-column fixed="left" prop="user" label="User" min-width="120">
            <template #default="{ row }">
                <div class="fs_time_sheet_person">
                    <el-avatar :src="row.photo" size="small"></el-avatar>
                    <span>{{ row.full_name }}</span>
                </div>
            </template>
        </el-table-column>
        <el-table-column :min-width="100" v-for="date in dateLabels" :key="date" :prop="date" :label="date" min-width="100">
            <template #header="{ column }">
                <span>{{ smartDate(column.label) }}</span>
            </template>
            <template #default="{ row }">
                <AgentDateSheetPop :agent_id="row.id" :date="date" :timeSheets="timeSheets" />
            </template>
        </el-table-column>
        <el-table-column label="Total" width="160" fixed="right">
            <template #header="{ column }">
                <span>Total ({{ formatMinutes(totalMinutes) }})</span>
            </template>
            <template #default="{ row }">
                <strong>{{ getUserTotal(row.id) }}</strong>
            </template>
        </el-table-column>
    </el-table>
    <el-skeleton :animated="true" :rows="10" v-else />
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import AgentDateSheetPop from './_AgentDateSheetPop.vue';
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'ByAgents',
    components: { AgentDateSheetPop },
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
    setup(props) {
        const { get, translate, handleError, setTitle, appVars, smartDate } = useFluentHelper();
        const agents = ref([]);
        const totalMinutes = ref(0);
        const timeSheets = ref({});
        const dateLabels = ref([]);
        const isLoaded = ref(false);

        const fetchReportsByAgents = async () => {
            isLoaded.value = false;
            try {
                const response = await get('reports/timesheet/by-agents', {
                    mailbox_id: props.mailbox_id,
                    date_range: props.date_range
                });
                agents.value = response.agents;
                timeSheets.value = response.time_sheets;
                dateLabels.value = response.date_labels;
                totalMinutes.value = response.totalMinutes;
            } catch (error) {
                this.$handleError(error);
            } finally {
                isLoaded.value = true;
            }
        };

        const getUserTotal = (userId) => {
            const minutes = dateLabels.value.reduce((acc, date) => {
                const sheets = timeSheets.value?.[date]?.[userId] || [];
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

        onMounted(fetchReportsByAgents);

        return {
            agents,
            totalMinutes,
            timeSheets,
            dateLabels,
            isLoaded,
            fetchReportsByAgents,
            getUserTotal,
            formatMinutes,
            smartDate
        };
    }
};
</script>

<style>
.fs_time_sheet_person {
    display: flex;
    align-items: center;
    gap: 8px;
}


</style>

