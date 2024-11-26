<template>
    <div v-if="has_pro" class="fs_report_timesheet_wrap">
        <div class="fs_report fs_report_timesheet">
            <div class="fs_report-banner">
                <div class="fs_inner_nav">
                    <el-radio-group v-model="reportType">
                        <el-radio-button label="byTickets">{{ translate('By Tickets') }}</el-radio-button>
                        <el-radio-button label="byAgents">{{ translate('By Agents') }}</el-radio-button>
                        <el-radio-button label="byCustomers">{{ translate('By Customers') }}</el-radio-button>
                    </el-radio-group>
                </div>

                <div class="fs_report_filter_panel">
                    <el-date-picker
                        v-model="dateRange"
                        type="daterange"
                        @change="renderReport"
                        range-separator="To"
                        :disabled-date="disabledDate"
                        value-format="YYYY-MM-DD"
                        start-placeholder="Start date"
                        end-placeholder="End date"
                        :shortcuts="shortcuts"
                        popper-class="fs-daterange-popover"
                    />

                </div>
            </div>
        </div>

        <template v-if="appReady">
            <ByTickets  :date_range="dateRange" v-if="reportType === 'byTickets'"/>
            <ByAgents  :date_range="dateRange" v-else-if="reportType === 'byAgents'"/>
            <ByCustomers  :date_range="dateRange" v-else />
        </template>
    </div>

    <div class="fs_narrow_promo" style="background: white" v-else>
        <h3>{{ translate("get_overall_reports") }}</h3>
        <p>{{ translate("pro_promo") }}</p>
        <a
            target="_blank"
            rel="noopener"
            href="https://fluentsupport.com"
            class="el-button el-button--success"
        >{{ translate("Upgrade To Pro") }}</a
        >
    </div>
</template>

<script>
import {nextTick, onBeforeMount, onMounted, reactive, toRefs} from "vue";
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";
import ByTickets from "./ByTickets.vue";
import ByAgents from "./ByAgents.vue";
import ByCustomers from "./ByCustomers.vue";
import {Download} from '@element-plus/icons-vue';

export default {
    name: "TimeSheet",
    components: {
        Download,
        ByTickets,
        ByAgents,
        ByCustomers
    },
    props: ["url"],
    setup(props) {
        const {get, translate, handleError, setTitle, appVars, has_pro} = useFluentHelper();

        const state = reactive({
            reportType: "byTickets",
            dateRange: [],
            SelectedMailBoxID: "",
            boardLoading: false,
            appReady: false,
            shortcuts: [
                {
                    text: "Last week",
                    value: () => {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
                        return [start, end];
                    },
                },
                {
                    text: "Last month",
                    value: () => {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
                        return [start, end];
                    },
                },
                {
                    text: "This month",
                    value: () => {
                        const end = new Date();
                        const start = new Date();
                        start.setDate(1);
                        return [start, end];
                    },
                },
            ],
        });

        const renderReport = () => {
            state.appReady = false;
            nextTick(() => {
                state.appReady = true;
            });
        };

        const disabledDate = (time) => {
            return time.getTime() > Date.now();
        };

        onMounted(() => {
            if (appVars.has_pro) {
                state.appReady = true;
            }
        });

        onBeforeMount(() => {
            if (state.shortcuts.length > 0) {
                let values = state.shortcuts[0].value();
                state.dateRange = [
                    values[0].toISOString().slice(0, 10),
                    values[1].toISOString().slice(0, 10),
                ];
            }
        });

        return {
            ...toRefs(state),
            renderReport,
            disabledDate,
            has_pro,
            translate
        };
    },
};
</script>

<style lang="scss">
</style>
