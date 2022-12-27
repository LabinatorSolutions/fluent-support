<template>
    <div class="fs_personal_report">
        <div class="fs_box_wrapper" v-loading="loading">
            <el-row :gutter="30">
                <el-col :sm="24" :md="16" :lg="18">
                    <div class="fs_box">
                        <div class="fs_box_header">
                            <div class="fs_header_title">
                                <el-dropdown
                                    style="
                                        display: inline-block;
                                        cursor: pointer;
                                        line-height: 100%;
                                    "
                                    @command="handleComponentChange"
                                    trigger="hover"
                                >
                                    <span class="el-dropdown-link">
                                        {{ chartMaps[currently_showing] }}
                                        <el-icon> <ArrowDown /> </el-icon>
                                    </span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-for="(
                                                    mapName, mapKey
                                                ) in chartMaps"
                                                :key="mapKey"
                                                :command="mapKey"
                                            >
                                                {{ mapName }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <el-date-picker
                                    v-model="date_range"
                                    type="daterange"
                                    :range-separator="translate('To')"
                                    :start-placeholder="translate('Start')"
                                    :end-placeholder="translate('End')"
                                    :unlink-panels="true"
                                    @change="filterReport"
                                    value-format="YYYY-MM-DD"
                                    style="max-width: 350px"
                                >
                                </el-date-picker>
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <component
                                v-if="showing_charts"
                                :is="currently_showing"
                                :date_range="date_range"
                                :url="'my-reports'"
                            ></component>
                        </div>
                    </div>
                    <agent-reports :url="'my-reports/my-summary'" />
                </el-col>
                <el-col :sm="24" :md="8" :lg="6">
                    <div class="fs_box">
                        <div class="fs_box_header">
                            <div class="fs_header_title">
                                {{ translate("Your Overall Stats") }}
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <ul v-if="!loading" class="fs_card_list">
                                <li
                                    style="padding: 15px"
                                    v-for="(stat, stat_type) in overall_reports"
                                    :key="stat_type"
                                >
                                    <b>{{ stat.title }}:</b> {{ stat.count }}
                                </li>
                            </ul>
                            <div class="fs_padded_20" v-else>
                                <el-skeleton :rows="3" animated />
                            </div>
                        </div>
                    </div>

                    <div
                        class="fs_box fs_today_stats"
                        style="margin-top: 1.25em"
                    >
                        <div class="fs_box_header">
                            <div class="fs_header_title">
                                {{ translate("Today Stats") }}
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <ul v-if="!loading" class="fs_card_list">
                                <li
                                    style="padding: 15px"
                                    v-for="(stat, stat_type) in today_reports"
                                    :key="stat_type"
                                >
                                    <b>{{ stat.title }}:</b> {{ stat.count }}
                                </li>
                            </ul>
                            <div class="fs_padded_20" v-else>
                                <el-skeleton :rows="3" animated />
                            </div>
                        </div>
                    </div>
                </el-col>
            </el-row>
        </div>
    </div>
</template>

<script type="text/babel">
import ResponseChart from "./Charts/ResponseGrowth";
import ResolveChart from "./Charts/ResolveGrowth";
import AgentReports from "./AgentReports";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import { reactive, toRefs, onMounted } from "vue";

export default {
    name: "PersonalReports",
    props: ["url"],
    components: {
        ResponseChart,
        ResolveChart,
        AgentReports,
    },

    setup(props) {
        const { get, translate, handleError, nextTick, setTitle, appVars } =
            useFluentHelper();

        const state = reactive({
            loading: false,
            stat_loading: false,
            overall_reports: {},
            currently_showing: "resolve-chart",
            date_range: ["", ""],
            showing_charts: true,
            chartMaps: {
                "resolve-chart": "Resolve Stats",
                "response-chart": "Response Stats",
            },
            me: appVars.me,
            today_reports: {},
        });

        const fetchReports = async () => {
            state.loading = true;
            await get(props.url)
                .then((response) => {
                    state.overall_reports = response.overall_reports;
                    state.today_reports = response.today_reports;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const handleComponentChange = (item) => {
            state.currently_showing = item;
        };

        const filterReport = () => {
            const current = state.currently_showing;
            state.currently_showing = {
                render: () => {},
            };
            nextTick(() => {
                state.currently_showing = current;
            });
        };

        onMounted(() => {
            fetchReports();
            setTitle("Personal Reports");
        });

        return {
            ...toRefs(state),
            translate,
            fetchReports,
            handleComponentChange,
            filterReport,
        };
    },
};
</script>
