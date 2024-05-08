<template>
    <div v-if="has_pro" class="fs_feedback_box_wrapper">
        <el-row :gutter="30">
            <el-col :sm="24" :md="16" :lg="18">
                <div style=" background: white;" class="fs_wid_widget fs_wid_day_by_day">
                    <div class="fs_header fs_widget_header">
                        <div class="fs_header_actions">
                            <h3>{{ $t('Activity Trends by Time of Day') }}</h3>
                            <div class="fs_box_actions">
                                <el-radio-group @change="reportFilter()" v-model="reportOf">
                                    <el-radio-button label="agent">Agent</el-radio-button>
                                    <el-radio-button label="customer">Customer</el-radio-button>
                                </el-radio-group>
                            </div>
                        </div>

                        <div class="fs_feedback_filter_options">
                            <div  v-if="reportOf == 'agent'" class="fs_filter_actions">
                                <el-select
                                    clearable
                                    filterable
                                    placeholder="All Agent"
                                    @change="fetchStats"
                                    v-model="agentId"
                                >
                                    <el-option
                                        v-for="agent in appVars.support_agents"
                                        :key="agent.id"
                                        :value="agent.id"
                                        :label="agent.full_name"
                                    ></el-option>
                                </el-select>
                            </div>

                            <div class="fs_filter_actions">
                                <el-select @change="fetchStats" v-model="reportType">
                                    <el-option v-if="reportOf == 'customer'" value="ticket" :label="$t('Ticket')"></el-option>
                                    <el-option value="response" :label="$t('Response')"></el-option>
                                </el-select>
                            </div>

                            <div class="fs_filter_actions">
                                <el-select @change="fetchStats" placeholder="Select Date" v-model="lastDay">
                                    <el-option :value="0" :label="$t('All Time')"></el-option>
                                    <el-option :value="7" :label="$t('Last 7 Days')"></el-option>
                                    <el-option :value="30" :label="$t('Last 30 Days')"></el-option>
                                </el-select>
                            </div>
                        </div>
                    </div>
                    <div class="fs_content">
                        <div v-if="appReady" class="fs_time_widget">
                            <div class="fs_time_widget_header">
                                <div class="fs_time_day"></div>
                                <div v-for="day in days" :key="day" class="fs_time_day">{{ day }}</div>
                            </div>
                            <div class="fs_time_widget_body">
                                <div class="fs_wid_sub_headers">
                                    <div v-for="tipIndex in tipIndexes" :key="tipIndex" class="fs_wid_sub_header">{{ tipIndex }}</div>
                                </div>
                                <div v-for="day in days" :key="day" class="fs_time_day">
                                    <div v-for="keyItem in filledSlots" :key="keyItem" :class="'fs_wid_' + getLevel(dataItems[day][keyItem])" class="fs_time_hour">
                                        <el-tooltip v-if="dataItems[day][keyItem]" :content="getTooltipContent(day, keyItem)" placement="top">
                                            <div class="fs_time_hour_value">
                                                <span>{{ dataItems[day][keyItem] }}</span>
                                            </div>
                                        </el-tooltip>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <el-skeleton v-else class="fs_content" :rows="5"></el-skeleton>
                        <div class="fs_wid_label_info">
                            <span class="fs_wid_dir">{{ $t('Less') }}</span>
                            <span class="fs_wid_level fs_wid_level_1"></span>
                            <span class="fs_wid_level fs_wid_level_2"></span>
                            <span class="fs_wid_level fs_wid_level_3"></span>
                            <span class="fs_wid_level fs_wid_level_4"></span>
                            <span class="fs_wid_level fs_wid_level_5"></span>
                            <span class="fs_wid_dir">{{ $t('More') }}</span>
                        </div>
                    </div>
                </div>
            </el-col>

            <el-col :sm="24" :md="8" :lg="6">
                <div class="fs_box_wrapper">
                    <SideBar/>
                </div>
            </el-col>
        </el-row>
    </div>
</template>

<script>
import {reactive, computed, onMounted, toRefs} from 'vue';
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import SideBar from "@/admin/Modules/Reports/Parts/_SideBar.vue";

export default {
    name: 'FeedbackByTimeOfDay',
    components: {SideBar},

    setup() {
        const {
            appVars,
            get,
            translate,
            handleError,
            saveData,
            getData,
            has_pro
        } = useFluentHelper();

        const state = reactive({
            lastDay: 30,
            appReady: false,
            reportType: 'response',
            reportOf: 'agent',
            dateRange: ["", ""],

            agentId: '',
            customerId: '',
            dataItems: {
            },
            days:['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            filledSlots: ['0:00', '1:00', '2:00', '3:00', '4:00', '5:00', '6:00', '7:00', '8:00', '9:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
            showing_charts: true,
            tipIndexes: ['1am', '4am', '7am', '10am', '1pm', '4pm', '7pm', '10pm'],
        });

        const maxValue = computed(() => {
            let max = 0;
            for (let day in state.dataItems) {
                for (let key in state.dataItems[day]) {
                    if (state.dataItems[day][key] > max) {
                        max = state.dataItems[day][key];
                    }
                }
            }
            return max < 5 ? 5 : max;
        });

        const getLevel = (value) => {
            value = parseInt(value);
            if (!value) {
                return 'level_0';
            }

            const itemValue = Math.round((value / maxValue.value) * 100);

            if (itemValue > 80) {
                return 'level_5';
            } else if (itemValue > 60) {
                return 'level_4';
            } else if (itemValue > 40) {
                return 'level_3';
            } else if (itemValue > 20) {
                return 'level_2';
            } else {
                return 'level_1';
            }
        };

        const reportFilter = () => {
            state.agentId = '';
            state.reportType = 'response';
            fetchStats();
        };

        const getTooltipContent = (day, keyItem) => {
            if (state.reportType === 'response') {
                return state.dataItems[day][keyItem] + ' response created';
            } else {
                return state.dataItems[day][keyItem] + ' tickets created';
            }
        }

        const fetchStats = () => {
            state.appReady = false;
            get('reports/day-time-stats', {
                last_day: state.lastDay,
                report_type: state.reportType,
                report_of: state.reportOf,
                agent_id: state.agentId,
                date_range: state.dateRange
            })
                .then(res => {
                    state.dataItems = res.stats;
                })
                .fail(error => {
                    console.log(error);
                })
                .always(() => {
                    state.appReady = true;
                });
        }

        onMounted(() => {
            fetchStats();
        });

        return {
            ...toRefs(state),
            maxValue,
            getLevel,
            fetchStats,
            appVars,
            get,
            translate,
            handleError,
            saveData,
            getData,
            reportFilter,
            getTooltipContent,
            has_pro
        };
    }
};
</script>

<style lang="scss">

</style>
