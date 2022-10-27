<template>
    <div class="fs_box fs_dashboard_box">
        <el-collapse v-model="activeNames" @change="handleChange">
            <el-collapse-item name="dashboard_box" class="box_board">
                <template #title>
                    <div class="fs_box_header">
                        {{ $t("Your Overview for Today") }}
                    </div>
                </template>
                <div class="fs_box_body">
                    <template v-if="!loading">
                        <ul class="fs_card_list">
                            <li
                                style="padding: 15px"
                                v-for="(stat, statKey) in data.stats"
                                :key="statKey"
                            >
                                <b>{{ stat.title }}: </b> {{ stat.count }}
                            </li>
                        </ul>
                        <p
                            class="fs_padded_20 fs_stat_helper"
                            v-if="data.individual_stat"
                        >
                            <span class="fs_highlight"
                                >{{ data.individual_stat.waiting_tickets }}
                                {{ $t("tickets") }}</span
                            >
                            {{ $t("are waiting for reply with") }}
                            <span class="fs_highlight">
                                {{ $t("average") }}
                                {{ data.individual_stat.average_waiting }}
                                {{ $t("wait time") }}</span
                            >
                            & {{ $t("max wait time") }}
                            <span class="fs_highlight">{{
                                data.individual_stat.max_waiting
                            }}</span>

                            <span class="fs_highlight">{{
                                "individual_stat.max_waiting"
                            }}</span>
                        </p>
                    </template>
                    <div class="fs_padded_20" v-else>
                        <el-skeleton :rows="3" animated />
                    </div>
                </div>
            </el-collapse-item>
        </el-collapse>
    </div>
</template>
<script>
export default {
    props: ["data", "component_collapse_state"],
    emits: ["component_state"],
    name: "DashboardBox",
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
            if (val[1]) {
                this.component_collapse_state.dashboardBox = true;
            } else {
                this.component_collapse_state.dashboardBox = false;
            }
            this.$emit("component_state", this.component_collapse_state);
        },
        getCollapseData() {
            this.collapse_data = this.component_collapse_state;
            if (this.collapse_data.dashboardBox) {
                this.activeNames = ["dashboard_box"];
            } else {
                this.activeNames = null;
            }
        },
    },
    mounted() {
        this.getCollapseData();
    },
};
</script>
<style>
.box_board{
    max-width: 680px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 24px;
    border-radius: 5px;
}
.el-collapse-item__header{
    background-color: #f9f9f9;
    margin: auto;
    border-radius: 5px;
    height: auto;
}
</style>
