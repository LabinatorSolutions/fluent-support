<template>
    <div style="margin: 20px 0; background: white; padding: 20px" class="fs_wid_widget fs_wid_day_by_day">
        <div class="fs_header fs_widget_header">
            <h3>{{ $t('Ticket created by time of day') }}</h3>
            <div class="fs_select_options">
                <div class="widget_actions fs_to_right">
                    <el-select @change="fetchStats" size="small" placeholder="Select Report Of" v-model="reportOf">
                        <el-option value="agent" :label="$t('Agent')"></el-option>
                        <el-option value="customer" :label="$t('Customer')"></el-option>
                    </el-select>
                </div>

                <div  v-if="reportOf == 'agent'" class="widget_actions fs_to_right">
                    <el-select
                        clearable
                        filterable
                        placeholder="All Agent"
                        @change="fetchStats"
                        v-model="agentId"
                        class="fs_report_by_agent"
                    >
                        <el-option
                            v-for="agent in appVars.support_agents"
                            :key="agent.id"
                            :value="agent.id"
                            :label="agent.full_name"
                        ></el-option>
                    </el-select>
                </div>

                <div class="widget_actions fs_to_right">
                    <el-select @change="fetchStats" size="small" v-model="reportType">
                        <el-option v-if="reportOf == 'customer'" value="ticket" :label="$t('Ticket')"></el-option>
                        <el-option value="response" :label="$t('Response')"></el-option>
                    </el-select>
                </div>

                <div class="widget_actions fs_to_right">
                    <el-select @change="fetchStats"  placeholder="Select Date" size="small" v-model="lastDay">

                        <el-option :value="7" :label="$t('Last 7 Days')"></el-option>
                        <el-option :value="30" :label="$t('Last 30 Days')"></el-option>
                        <el-option :value="0" :label="$t('All Time')"></el-option>
                    </el-select>
                </div>

            </div>
        </div>
        <div class="fs_content">
            <div v-if="appReady" class="fcraft_time_widget">
                <div class="fcraft_time_widget_header">
                    <div class="fcraft_time_day"></div>
                    <div v-for="day in days" :key="day" class="fcraft_time_day">{{ day }}</div>
                </div>
                <div class="fcraft_time_widget_body">
                    <div class="fs_wid_sub_headers">
                        <div v-for="tipIndex in tipIndexes" :key="tipIndex" class="fs_wid_sub_header">{{ tipIndex }}</div>
                    </div>
                    <div v-for="day in days" :key="day" class="fcraft_time_day">
                        <div v-for="keyItem in filledSlots" :key="keyItem" :class="'fs_wid_' + getLevel(dataItems[day][keyItem])" class="fcraft_time_hour">
                            <el-tooltip v-if="dataItems[day][keyItem]" :content="dataItems[day][keyItem] + ' tickets created '" placement="top">
                                <div class="fcraft_time_hour_value">
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
</template>

<script>
import {ref, reactive, computed, onMounted, toRefs} from 'vue';
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'FeedbackByTimeOfDay',
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
            reportType: 'ticket',
            reportOf: 'agent',
            dateRange: ["", ""],
            agentId: '',
            customerId: '',
            dataItems: {
            },
            days:['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            filledSlots: ['0:00', '1:00', '2:00', '3:00', '4:00', '5:00', '6:00', '7:00', '8:00', '9:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
            showing_charts: true,
            chartMaps: {
                "tickets-chart": "Ticket Stats",
                "resolve-chart": "Resolve Stats",
                "response-chart": "Response Stats",
            },
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
                    console.log(res.stats)
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
            has_pro
        };
    }
};
</script>

<style lang="scss">
.fs_wid_day_by_day {
    max-width: 100%;
    overflow-x: auto;

    .fs_wid_widget_body {
        min-width: 750px;
    }

    .fs_wid_label_info {
        margin-top: 20px;
        display: flex;
        align-items: center;
        color: #6080a0;
        gap: 10px;

        .fs_wid_level {
            width: 20px;
            height: 20px;
        }
    }

    .fs_wid_sub_headers {
        display: block;
        text-align: center;
        margin-bottom: 5px;

        .fs_wid_sub_header {
            width: 12.5%;
            float: left;
            text-align: left;
            font-size: 11px;

            &:first-child {
                text-align: center;
            }
        }
    }

    /* Lighter Shade for Label 0 */
    .fs_wid_level_0 {
        background: #F0F0F0; /* Lighter shade of #24CC8F */
    }

    /* Lighter Shade 1 */
    .fs_wid_level_1 {
        background: #9be9a8; /* Lighter shade of #24CC8F */
    }

    /* Darker Shade 2 */
    .fs_wid_level_2 {
        background: #40c463; /* Darker shade of #44f5b0 */
    }

    /* Darker Shade 3 */
    .fs_wid_level_3 {
        background: #30a14e; /* Darker shade of #3be0a2 */
    }

    /* Darker Shade 4 */
    .fs_wid_level_4 {
        background: #1aa274; /* Darker shade of #32cc95 */
    }

    /* Darker Shade 5 */
    .fs_wid_level_5 {
        background: #216e39 /* Darker shade of #29b888 */
    }

}

.fcraft_time_widget {
    display: flex;
    flex-direction: row;

    .fcraft_time_widget_header {
        display: flex;
        flex-direction: column;
        padding: 0px 10px;
        justify-content: space-between;

        > div {
            height: 20px;
            border: 2px solid white;
        }
    }

    .fcraft_time_widget_body {
        display: flex;
        flex-direction: column;

        .fcraft_time_day {
            display: flex;
            flex-direction: row;
            justify-content: space-between;

            .fcraft_time_hour {
                display: flex;
                flex-direction: column;
                height: 22px;
                width: 22px;
                text-align: center;
                border: 3px solid white;
                opacity: 0.9;

                .fcraft_time_hour_value {
                    font-size: 10px;
                    font-weight: 300;
                    opacity: 0;
                    cursor: pointer;
                }
            }
        }
    }
}

.fs_to_right {
    width: 20%;
    margin-right: 10px;
}

.fs_select_options {
    width: 50%;
    display: flex;
    margin-bottom: 10px;
}


</style>
