<template>
    <div class="fs_box fs_dashboard_box">
        <el-collapse v-model="activeNames" @change="handleChange">
            <el-collapse-item name="dashboard_box" class="fs_box_board">
                <template #title>
                    <div class="fs_box_header">
                        {{ $t("Your Overview for Today") }}
                    </div>
                </template>
                <div class="fs_box_body">
                    <ul class="fs_card_list">
                        <li
                            style="padding: 15px"
                            v-for="(stat, statKey) in component_data.stats"
                            :key="statKey"
                        >
                            <b>{{ stat.title }}: </b> {{ stat.count }}
                        </li>
                    </ul>
                    <p
                        class="fs_padded_20 fs_stat_helper"
                        v-if="component_data.individual_stat"
                    >
                        <span class="fs_highlight"
                            >{{
                                component_data.individual_stat.waiting_tickets
                            }}
                            {{ $t("tickets") }}</span
                        >
                        {{ $t("are waiting for reply with") }}
                        <span class="fs_highlight">
                            {{ $t("average") }}
                            {{ component_data.individual_stat.average_waiting }}
                            {{ $t("wait time") }}</span
                        >
                        & {{ $t("max wait time") }}
                        <span class="fs_highlight">{{
                            component_data.individual_stat.max_waiting
                        }}</span>

                        <span class="fs_highlight">{{
                            "individual_stat.max_waiting"
                        }}</span>
                    </p>
                </div>
            </el-collapse-item>
        </el-collapse>
    </div>
</template>
<script>
export default {
    props: ["component_data"],
    name: "TicketStatistics",
    data() {
        return {
            activeComponent: {
                dashboardBox: [],
            },
            activeNames: [],
            collapse_data: [],
        };
    },
    methods: {
        handleChange(val) {
        },
        getCollapseData() {
        },
    },
    mounted() {
        this.getCollapseData();
    },
};
</script>
<style>
</style>
