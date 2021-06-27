<template>
    <div style="margin-top: 20px;" class="fs_agent_reports">
        <div class="fs_box_wrapper">
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
                    </el-table>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import dayjs from 'dayjs';

export default {
    name: 'AgentReports',
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
                    text: 'Last week',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 7)
                        return [start, end]
                    })(),
                },
                {
                    text: 'Last month',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 30)
                        return [start, end]
                    })(),
                },
                {
                    text: 'Last 3 months',
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 90)
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
        }
    },
    methods: {
        getReport() {
            this.loading = true;
            this.$get('reports/agents-summary', {
                from: (this.date_range) ? dayjs(this.date_range[0]).format('YYYY-MM-DD') : '',
                to: (this.date_range) ? dayjs(this.date_range[1]).format('YYYY-MM-DD') : ''
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
        }
    },
    mounted() {
        this.getReport();
    }
}
</script>
