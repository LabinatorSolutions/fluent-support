<template>
    <div class="fs_box_body">
        <el-table
            :data="component_data"
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

<script>
export default {
    props: ['component_data'],
    name: 'AgentPerformance',
    setup() {
        return {};
    },
};
</script>

<style scoped>

</style>
