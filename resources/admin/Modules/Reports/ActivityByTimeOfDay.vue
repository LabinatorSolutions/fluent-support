<template>
    <div v-if="has_pro">
        <div class="fs_inside_menu_component_header">
            <div class="fs_component_head">
                <h3 class="fs_page_title">{{ $t("Activity Reports") }}</h3>
            </div>
            <div class="fs_box_actions">
                <el-select @change="fetchStats" v-model="reportType" class="fs_report_by_product fs_select_field fs_staff_filter fs_select_field_min_width">
                    <el-option value="ticket" :label="$t('All Tickets')"></el-option>
                    <el-option value="agent_response" :label="$t('Agent Response')"></el-option>
                    <el-option value="customer_response" :label="$t('Customer Response')"></el-option>
                </el-select>

                <el-select
                    clearable
                    filterable
                    v-if="reportType === 'agent_response'"
                    :placeholder="$t('All Agent')"
                    @change="fetchStats"
                    v-model="agentId"
                    class="fs_select_field fs_staff_filter fs_select_field_min_width"
                >
                    <el-option
                        v-for="agent in appVars.support_agents"
                        :key="agent.id"
                        :value="agent.id"
                        :label="agent.full_name"
                    ></el-option>
                </el-select>
                <div class="fs_date_button_group">
                    <div class="fs_date_button_group_item fs_date_picker_wrapper">
                        <div class="fs_date_display">
                            <IconPack icon-key="calendar" :width="20" :height="20" class="fs_calendar_icon" />
                            <span v-if="formattedDateRange" class="fs_date_text">{{ formattedDateRange }}</span>
                            <span v-else class="fs_date_placeholder">{{ $t('Select date range') }}</span>
                        </div>
                        <el-date-picker
                            v-model="dateRange"
                            type="daterange"
                            range-separator="To"
                            :disabled-date="onlyPastDates"
                            value-format="YYYY-MM-DD"
                            :start-placeholder="$t('Start date')"
                            :end-placeholder="$t('End date')"
                            :shortcuts="shortcuts"
                            @change="handleDateChange"
                            class="fs_date_range_picker"
                        />
                    </div>
                </div>

            </div>
        </div>

        <div class="fs-activity-by-time-of-day">
            <div class="fs_wid_widget fs_wid_day_by_day">
                <div class="fs_table_header">
                    <div class="fs_box_actions">
                        <span class="fs_table_title">{{ $t('Activity Trends by Time of Day') }}</span>
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
        </div>

    </div>

    <NarrowPromo
        v-else
        :heading="$t('get_overall_reports')"
        :description="$t('pro_promo')"
        :button-text="$t('Upgrade To Pro')"
    />
</template>

<script>
import SideBar from "@/admin/Modules/Reports/Parts/_SideBar";
import NarrowPromo from "@/admin/Components/NarrowPromo";
import {shortcuts} from "./Utils/dateShortCuts";
import IconPack from "@/admin/Components/IconPack";
import { formatDateRangeForDisplay, getDefaultDateRange } from "./Utils/reportHelpers";

export default {
    name: 'FeedbackByTimeOfDay',
    props: ["date_range"],
    components: {
        SideBar,
        NarrowPromo,
        IconPack
    },

    data() {
        return {
            appReady: false,
            reportType: 'ticket',
            dateRange: this.date_range,
            shortcuts: shortcuts,
            agentId: '',
            customerId: '',
            dataItems: {},
            days:['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            filledSlots: ['0:00', '1:00', '2:00', '3:00', '4:00', '5:00', '6:00', '7:00', '8:00', '9:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'],
            showing_charts: true,
            tipIndexes: ['1am', '4am', '7am', '10am', '1pm', '4pm', '7pm', '10pm'],
        };
    },

    computed: {
        formattedDateRange() {
            return formatDateRangeForDisplay(this.dateRange);
        },
        maxValue() {
            let max = 0;
            for (let day in this.dataItems) {
                for (let key in this.dataItems[day]) {
                    if (this.dataItems[day][key] > max) {
                        max = this.dataItems[day][key];
                    }
                }
            }
            return max < 5 ? 5 : max;
        },
    },

    watch: {
        date_range: {
            handler(newVal) {
                this.dateRange = newVal;
                this.fetchStats();
            },
            deep: true
        }
    },

    methods: {
        getLevel(value) {
            value = parseInt(value);
            if (!value) {
                return 'level_0';
            }

            const itemValue = Math.round((value / this.maxValue) * 100);

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
        },

        getTooltipContent(day, keyItem) {
            if (this.reportType === 'ticket') {
                return this.dataItems[day][keyItem] + ' tickets created';
            } else {
                return this.dataItems[day][keyItem] + ' response created';
            }
        },

        handleDateChange() {
            this.$emit('date-change', this.dateRange);
            this.fetchStats();
        },

        onlyPastDates(val) {
            return new Date() <= val;
        },

        fetchStats() {
            this.appReady = false;
            this.$get('reports/day-time-stats', {
                report_type: this.reportType,
                agent_id: this.agentId,
                date_range: this.dateRange
            })
                .then(res => {
                    this.dataItems = res.stats;
                })
                .fail(error => {
                    console.log(error);
                })
                .always(() => {
                    this.appReady = true;
                });
        },
    },

    mounted() {
        this.fetchStats();
    },
};
</script>
