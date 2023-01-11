<template>
    <div class="fs_agents_report">
        <div v-if="has_pro" class="fs_box_wrapper" v-loading="loading">
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
                                <div class="fs_agent_report_rh_filter">
                                    <el-select
                                        clearable
                                        filterable
                                        placeholder="All Agent"
                                        @change="filterReport"
                                        v-model="agent"
                                        class="fs_report_by_agent"
                                    >
                                        <el-option
                                            v-for="agent in appVars.support_agents"
                                            :key="agent.id"
                                            :value="agent.id"
                                            :label="agent.full_name"
                                        ></el-option>
                                    </el-select>

                                    <el-date-picker
                                        v-model="date_range"
                                        type="daterange"
                                        :range-separator="translate('To')"
                                        :start-placeholder="translate('Start')"
                                        :end-placeholder="translate('End')"
                                        :unlink-panels="true"
                                        @change="filterReport"
                                        value-format="YYYY-MM-DD"
                                    >
                                    </el-date-picker>
                                </div>
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <component
                                v-if="showing_charts"
                                :is="currently_showing"
                                :date_range="date_range"
                                :url="'reports'"
                                :agent_id="agent"
                            ></component>
                        </div>
                    </div>
                    <agent-reports
                        :url="'reports/agents-summary'"
                        :show_settings="true"
                        :show_export_btn="true"
                    />
                </el-col>
                <el-col :sm="24" :md="8" :lg="6">
                    <div class="fs_box">
                        <div class="fs_box_header" style="margin-top: 1.25em">
                            <div class="fs_header_title">
                                {{ translate("Quick Stats") }}
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
                                        v-for="(
                                            stat, stat_type
                                        ) in today_reports"
                                        :key="stat_type"
                                    >
                                        <b>{{ stat.title }}:</b>
                                        {{ stat.count }}
                                    </li>
                                </ul>
                                <div class="fs_padded_20" v-else>
                                    <el-skeleton :rows="3" animated />
                                </div>
                            </div>
                        </div>
                    </div>
                </el-col>
            </el-row>
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
    </div>
</template>

<script type="text/babel">
import TicketsChart from "./Charts/TicketsGrowth";
import ResponseChart from "./Charts/ResponseGrowth";
import ResolveChart from "./Charts/ResolveGrowth";
import AgentReports from "./AgentReports";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import { reactive, toRefs, onMounted } from "vue";

export default {
    name: "Reports",
    components: {
        TicketsChart,
        ResponseChart,
        ResolveChart,
        AgentReports,
    },
    setup() {
        const { get, translate, handleError, nextTick, setTitle } =
            useFluentHelper();

        const state = reactive({
            loading: false,
            stat_loading: false,
            overall_reports: {},
            currently_showing: "tickets-chart",
            date_range: ["", ""],
            showing_charts: true,
            chartMaps: {
                "tickets-chart": "Ticket Stats",
                "resolve-chart": "Resolve Stats",
                "response-chart": "Response Stats",
            },
            agent: "",
            today_reports: {},
        });

        const fetchReports = () => {
            state.loading = true;
            get("reports")
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
            setTitle("Reports");
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

<style lang="scss">
.fs_agent_report_rh_filter {
    display: flex;
    align-items: center;
    .fs_report_by_agent {
        width: auto;
        margin-right: 0.3em;
    }
    @media (max-width: 767px) {
        display: flex;
        flex-direction: column;
        flex-wrap: nowrap;
        justify-content: center;
        padding: 0.7em;
        .fs_report_by_agent {
            width: auto;
            margin-right: 0;
            margin-bottom: 0.4em;
        }
    }
}
</style>
