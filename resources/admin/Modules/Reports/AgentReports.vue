<template>
    <div style="margin-top: 20px;" class="fs_agent_reports">
        <div v-if="!loading" class="fs_box_wrapper">
            <div class="fs_box">
                <div class="fs_box_header">
                    <div class="fs_box_head">
                        Individual Performance
                    </div>
                    <div class="fs_box_actions">
                        <el-date-picker
                            @change="getReport()"
                            v-model="date_range"
                            type="daterange"
                            size="mini"
                            range-separator="To"
                            :disabledDate="onlyPastDates"
                            start-placeholder="Start"
                            end-placeholder="End"
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
                        <el-table-column min-width="200px" label="Agent">
                            <template #default="scope">
                                {{ scope.row.full_name }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="responses" label="Responses">
                            <template #default="scope">
                                {{ scope.row.stats.responses }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="interactions" label="Interactions">
                            <template #default="scope">
                                {{ scope.row.stats.interactions }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="opens" label="Open Tickets">
                            <template #default="scope">
                                {{ scope.row.stats.opens }}
                            </template>
                        </el-table-column>

                        <el-table-column sortable="custom" prop="closed" label="Closed">
                            <template #default="scope">
                                {{ scope.row.stats.closed }}
                            </template>
                        </el-table-column>

                        <el-table-column min-width="150px" label="Current Overall">
                            <template #default="scope">
                                <template v-if="scope.row.active_stat">
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        <li>Waiting Tickets: {{scope.row.active_stat.waiting_tickets}}</li>
                                        <li>Average Waiting: {{scope.row.active_stat.average_waiting}}</li>
                                        <li>Max Waiting: {{scope.row.active_stat.max_waiting}}</li>
                                    </ul>
                                </template>
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
import dayjs from 'dayjs';
import each from 'lodash/each';

export default {
    name: 'AgentReports',
    props:['url'],
    data() {
        return {
            reports: [],
            loading: false,
            sorts: {
                prop: 'responses',
                order: 'descending'
            },
            sort_column: 'response',
            sort_type: 'descending',
            date_range: [new Date(), new Date()],
            dateShortcuts: [
                {
                    text: 'Today',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        return [start, end]
                    })(),
                },
                {
                    text: 'Yesterday',
                    value: (() => {
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24);
                        return [start, start]
                    })(),
                },
                {
                    text: 'Last Week',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 7)
                        return [start, end]
                    })(),
                },
                {
                    text: 'Last Month',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 30)
                        return [start, end]
                    })(),
                },
                {
                    text: 'Last 3 Months',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 90)
                        return [start, end]
                    })(),
                },
                {
                    text: 'Last 6 Months',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 180)
                        return [start, end]
                    })(),
                },
                {
                    text: 'Last 1 Year',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 360)
                        return [start, end]
                    })(),
                }
            ],
            valueFormat: 'YYYY-MM-DD'
        }
    },
    computed: {
        sortedReports() {
            let reports = this.reports;
            if (this.sort_type == 'ascending') {
                return reports.sort((a, b) => (parseInt(a.stats[this.sort_column]) > parseInt(b.stats[this.sort_column])) ? 1 : -1)
            }
            return reports.sort((a, b) => (parseInt(a.stats[this.sort_column]) < parseInt(b.stats[this.sort_column])) ? 1 : -1)
        },
        totals() {
            const summary = {
                responses: 0,
                interactions: 0,
                opens: 0,
                closed: 0
            };
            each(this.reports, (report) => {
                summary.responses += parseInt(report.stats.responses);
                summary.interactions += parseInt(report.stats.interactions);
                summary.opens += parseInt(report.stats.opens);
                summary.closed += parseInt(report.stats.closed);
            });
            return summary;
        },
        showOrHideSummaries(){
          if(this.url=='my-reports/my-summary'){
            return false;
          }
          return true;
        }
    },
    methods: {
        getReport() {
            this.loading = true;
            this.$get(this.url, {
                from: (this.date_range) ? dayjs(this.date_range[0]).format('YYYY-MM-DD') : '',
                to: (this.date_range) ? dayjs(this.date_range[1]).format('YYYY-MM-DD') : '',
            })
                .then(response => {
                    this.reports = response.summary
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        handleSorting(props) {
            this.sort_column = props.prop;
            this.sort_type = props.order;
        },
        onlyPastDates(val) {
            return new Date() <= val;
        },
        getSummaries(param) {
            const { columns } = param;
            const sums = [];
            columns.forEach((column, index) => {
                if (index === 0) {
                    sums[index] = "Total Summaries";
                    return;
                }
                sums[index] = this.totals[column.property];
            });
            return sums;
        }
    },
    mounted() {
        this.getReport();
    }
}
</script>
