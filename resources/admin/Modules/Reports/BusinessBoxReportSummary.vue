<template>
    <div style="margin-top: 20px;" class="fs_agent_reports">
        <div v-if="!loading" class="fs_box_wrapper">
            <div class="fs_box">
                <div class="fs_box_header">
                    <div class="fs_box_head">
                        {{ translate('Business Box Summary') }}
                    </div>
                    <div class="fs_box_actions">
                        <el-date-picker
                            @change="getReport()"
                            v-model="date_range"
                            type="daterange"
                            size="small"
                            :range-separator="translate('To')"
                            :disabledDate="onlyPastDates"
                            :start-placeholder="translate('Start')"
                            :end-placeholder="translate('End')"
                            :shortcuts="dateShortcuts"
                        />
                    </div>
                </div>
                <div class="fs_box_body">
                    <el-table
                        :data="sortedReports"
                        stripe
                        :summary-method="getSummaries"
                        :show-summary="showOrHideSummaries"
                        @sort-change="handleSorting"
                        v-loading="loading"
                        style="width: 100%">
                        <el-table-column min-width="200px" :label="translate('MailBox')">
                            <template #default="scope">
                                {{ scope.row.name }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="responses" :label="translate('Responses')">
                            <template #default="scope">
                                {{ scope.row.stats.responses }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="interactions" :label="translate('Interactions')">
                            <template #default="scope">
                                {{ scope.row.stats.interactions }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="opens" :label="translate('Open Tickets')">
                            <template #default="scope">
                                {{ scope.row.stats.opens }}
                            </template>
                        </el-table-column>

                        <el-table-column sortable="custom" prop="closed" :label="translate('Closed')">
                            <template #default="scope">
                                {{ scope.row.stats.closed }}
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
        </div>
        <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated/>
        </div>
    </div>
</template>

<script type="text/babel">
import dayjs from "dayjs";
import each from "lodash/each";
import Modal from "../../Pieces/Modal";
import { computed, onMounted, reactive, toRefs } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "BusinessBoxReportSummary",
    components: { Modal },
    props: ["url", "show_settings", "show_export_btn"],

    setup(props) {
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
            reports: [],
            loading: false,
            sorts: {
                prop: "responses",
                order: "descending",
            },
            sort_column: "response",
            sort_type: "descending",
            date_range: [new Date(), new Date()],
            dateShortcuts: [
                {
                    text: translate("Today"),
                    value: (() => {
                        const end = new Date();
                        const start = new Date();
                        return [start, end];
                    })(),
                },
                {
                    text: translate("Yesterday"),
                    value: (() => {
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24);
                        return [start, start];
                    })(),
                },
                {
                    text: translate("Last Week"),
                    value: (() => {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
                        return [start, end];
                    })(),
                },
                {
                    text: translate("Last Month"),
                    value: (() => {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
                        return [start, end];
                    })(),
                },
                {
                    text: translate("Last 3 Months"),
                    value: (() => {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 90);
                        return [start, end];
                    })(),
                },
                {
                    text: translate("Last 6 Months"),
                    value: (() => {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 180);
                        return [start, end];
                    })(),
                },
                {
                    text: translate("Last 1 Year"),
                    value: (() => {
                        const end = new Date();
                        const start = new Date();
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 360);
                        return [start, end];
                    })(),
                },
            ],
            valueFormat: "YYYY-MM-DD",
            open_setting: false,
            include_or_exclude_agents: [],
            include_or_exclude: "include", // Define if we are including or excluding agents, default is include
            open_export_options: false,
            repost_export_options: appVars.repost_export_options,
            selected_options: [],
            checkAll: false,
            isIndeterminate: false,
        });

        const sortedReports = computed(() => {
            let reports = state.reports;

            const settings = getData("agents_summary_setting");

            if (props.url != "my-reports/my-summary" && settings) {
                let reportsToInclude = [];
                each(reports, (report) => {
                    if (settings.agents.includes(report.id)) {
                        reportsToInclude.push(report);
                    }
                });
                reports = reportsToInclude;
            }

            if (state.sort_type == "ascending") {
                return reports.sort((a, b) =>
                    parseInt(a.stats[state.sort_column]) >
                    parseInt(b.stats[state.sort_column])
                        ? 1
                        : -1
                );
            }
            return reports.sort((a, b) =>
                parseInt(a.stats[state.sort_column]) <
                parseInt(b.stats[state.sort_column])
                    ? 1
                    : -1
            );
        });

        const totals = computed(() => {
            const summary = {
                responses: 0,
                opens: 0,
                closed: 0,
                interactions: 0
            };
            each(state.reports, (report) => {
                summary.responses += parseInt(report.stats.responses);
                summary.interactions += parseInt(report.stats.interactions);
                summary.opens += parseInt(report.stats.opens);
                summary.closed += parseInt(report.stats.closed);
            });
            return summary;
        });

        const showOrHideSummaries = computed(() => {
            if (props.url == "my-reports/my-summary") {
                return false;
            }
            return true;
        });

        const getReport = async () => {
            state.loading = true;
            await get(props.url, {
                from: (state.date_range) ? dayjs(state.date_range[0]).format('YYYY-MM-DD') : '',
                to: (state.date_range) ? dayjs(state.date_range[1]).format('YYYY-MM-DD') : '',
            })
                .then(response => {
                    state.reports = response.summary
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const handleSorting = (props) => {
            state.sort_column = props.prop;
            state.sort_type = props.order;
        };

        const onlyPastDates = (val) => {
            return new Date() <= val;
        };

        const getSummaries = (param) => {
            const { columns } = param;
            const sums = [];

            columns.forEach((column, index) => {
                if (index === 0) {
                    sums[index] = translate("Total Summaries");
                    return;
                }
                sums[index] = totals.value[column.property];
            });

            return sums;
        };

        onMounted(() => {
            getReport();
        });

        return {
            ...toRefs(state),
            translate,
            sortedReports,
            totals,
            showOrHideSummaries,
            getReport,
            handleSorting,
            onlyPastDates,
            getSummaries,
            has_pro
        }
    },
};
</script>

<style lang="scss" scoped>
.fs_summary_settings div {
    margin: 10px 0;
}

.fs_summary_settings_icon {
    margin-right: 10px;
    margin-top: 2px;
    cursor: pointer;
}

.fs_summary_export_icon {
    margin-left: 17px;
    margin-top: 2px;
    cursor: pointer;
    color: #bd0909;
    font-size: 18px;
}

.fs_summary_export_items {
    display: flex;
    flex-wrap: wrap;
}

.fs_summary_export_items label {
    margin-top: 10px;
    width: 45%;
}
</style>
