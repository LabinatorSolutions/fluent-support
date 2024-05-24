<template>
    <div class="fs_box_body">
        <el-table
            :data="todayStats"
            style="width: 100%"
        >
            <el-table-column prop="agent_name" label="Agent" />
            <el-table-column label="Waiting">
                <template #default="scope">
                    <span> {{scope.row.stats.waiting_today.count}} </span>
                </template>
            </el-table-column>
            <el-table-column label="Responses">
                <template #default="scope">
                    <span> {{scope.row.stats.responses.count}} </span>
                </template>
            </el-table-column>
            <el-table-column label="Closed">
                <template #default="scope">
                    <span> {{scope.row.stats.closed_tickets.count}} </span>
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script type="text/babel">
import {onMounted, reactive, toRefs} from "vue";
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'AgentPerformance',
    setup(){
        const {
            appVars,
            translate,
            get,
        } = useFluentHelper();

        const state = reactive({
            fetching: false,
            todayStats: [],
        });

        const fetchAgentPerformance = async () => {
            state.fetching = true;
            await get('tickets/agent_performance')
                .then(response => {
                    state.todayStats = response.agent_today_stats;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {

                });
        }

        onMounted(() => {
            fetchAgentPerformance();
        });

        return {
            ...toRefs(state),
            appVars,
            translate,
        }
    }
}
</script>

<style scoped>

</style>
