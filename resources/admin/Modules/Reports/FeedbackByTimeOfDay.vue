<template>
    <div style="margin: 20px 0; background: white;" class="fs_wid_widget fs_wid_day_by_day">
        <div class="fs_header fs_widget_header">
            <h3>{{ $t('Ticket created by time of day') }}</h3>
            <div class="widget_actions fs_to_right">
                <el-select @change="fetchStats" size="big" v-model="last_day">
                    <el-option :value="7" :label="$t('Last 7 Days')"></el-option>
                    <el-option :value="30" :label="$t('Last 30 Days')"></el-option>
                    <el-option :value="0" :label="$t('All Time')"></el-option>
                </el-select>

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
            last_day: 30,
            appReady: false,
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
                last_day: state.last_day
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


    .fs_wid_level_5 {
        background: #1c00a6;
    }

    .fs_wid_level_4 {
        background: #2900f3;
    }

    .fs_wid_level_3 {
        background: #6040ff;
    }

    .fs_wid_level_2 {
        background: #a08dff;
    }

    .fs_wid_level_1 {
        background: #dfd9ff;
    }

    .fs_wid_level_0 {
        background: #f0f0f0;
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
    //width: 20%;
    float: right;
}
</style>
