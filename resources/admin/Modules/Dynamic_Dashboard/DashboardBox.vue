<template>
    <!-- <h1>DashBoardBox</h1>
    {{data}} -->
    <!-- {{data.individual_stat}}
<h1>Dashboard Box Test</h1> -->

    <!-- <div class="fs_box fs_dashboard_box">
                    <div class="fs_box_header">
                        {{$t('Your Overview for Today')}}
                    </div>
                    <div class="fs_box_body">
                        <template v-if="!loading">
                            <ul class="fs_card_list">
                                <li style="padding: 15px;" v-for="(stat,statKey) in data.stats" :key="statKey">
                                    <b>{{data.stat.title}}: </b> {{data.stat.count}}
                                </li>
                            </ul>
                            <p class="fs_padded_20 fs_stat_helper" v-if="data.individual_stat">
                                <span class="fs_highlight">{{data.individual_stat.waiting_tickets}} {{$t('tickets')}}</span> {{$t('are waiting for reply with')}}
                                <span class="fs_highlight"> {{$t('average')}} {{data.individual_stat.average_waiting}} {{$t('wait time')}}</span> & {{$t('max wait time')}}
                                <span class="fs_highlight">{{data.individual_stat.max_waiting}}</span>
                            </p>
                        </template>
                        <div class="fs_padded_20" v-else>
                            <el-skeleton :rows="3" animated/>
                        </div>
                    </div>
                </div> -->
    <!-- {{component_collapse_state}} -->

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
            // activeNames: ['dashboard_box'],
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
            // this.collapse_data = this.$getData('component_state');
            this.collapse_data = this.component_collapse_state;
            console.log(this.component_collapse_state);
            if (this.collapse_data.dashboardBox) {
                this.activeNames = ["dashboard_box"];
            } else {
                this.activeNames = null;
            }
            // console.log(this.component_collapse_state.dashboardBox);
            // if(this.component_collapse_state.dashboardBox){
            //     this.activeNames = ['dashboard_box']
            // }
            // else{
            //     this.activeNames = null
            // }
        },
    },
    mounted() {
        this.getCollapseData();
    },
};
</script>
<style>
/* .box_board{
    max-width: 680px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 24px;
    border-radius: 5px;
    border: 1px solid rgb(226, 228, 231); 
    box-shadow: 0 1px 4px rgb(18 25 97 / 8%)
}
.el-collapse-item__header{
    background-color: #f9f9f9;
    margin: auto;
    border-radius: 5px;
    height: auto;
} */
</style>
