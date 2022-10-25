<template>
    <div style="margin-top: 20px;" class="fs_agent_reports">
        <div v-if="!loading" class="fs_box_wrapper">
            <div class="fs_box">
                <div class="fs_box_header">
                    <div class="fs_box_head">
                        {{ $t('Individual Performance') }}
                    </div>
                    <div class="fs_box_actions">
                        <el-icon v-if="show_settings" @click="open_setting=true" class="fs_summary_settings_icon" :size="18" title="Filter Agent">
                            <Setting />
                        </el-icon>

                        <el-date-picker
                            @change="getReport()"
                            v-model="date_range"
                            type="daterange"
                            size="small"
                            :range-separator="$t('To')"
                            :disabledDate="onlyPastDates"
                            :start-placeholder="$t('Start')"
                            :end-placeholder="$t('End')"
                            :shortcuts="dateShortcuts"
                        />
                        <el-icon v-if="show_export_btn && has_pro" @click="open_export_options=true" class="fs_summary_export_icon" title="Export Report"><Download /></el-icon>
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
                        <el-table-column min-width="200px" :label="$t('Agent')">
                            <template #default="scope">
                                {{ scope.row.full_name }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="responses" :label="$t('Responses')">
                            <template #default="scope">
                                {{ scope.row.stats.responses }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="interactions" :label="$t('Interactions')">
                            <template #default="scope">
                                {{ scope.row.stats.interactions }}
                            </template>
                        </el-table-column>
                        <el-table-column sortable="custom" prop="opens" :label="$t('Open Tickets')">
                            <template #default="scope">
                                {{ scope.row.stats.opens }}
                            </template>
                        </el-table-column>

                        <el-table-column sortable="custom" prop="closed" :label="$t('Closed')">
                            <template #default="scope">
                                {{ scope.row.stats.closed }}
                            </template>
                        </el-table-column>

                        <el-table-column min-width="150px" :label="$t('Current Overall')">
                            <template #default="scope">
                                <template v-if="scope.row.active_stat">
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        <li>{{ $t('Waiting Tickets') }}: {{ scope.row.active_stat.waiting_tickets }}
                                        </li>
                                        <li>{{ $t('Average Waiting') }}: {{ scope.row.active_stat.average_waiting }}
                                        </li>
                                        <li>{{ $t('Max Waiting') }}: {{ scope.row.active_stat.max_waiting }}</li>
                                    </ul>
                                </template>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <Teleport to="body">
                    <modal :show="open_setting" :title="$t('exclude_or_include_in_summary')" @close="open_setting = false">
                        <template #body>
                            <div class="fs_summary_settings">
                                <el-row :gutter="20">
                                    <el-col :span="18">
                                        <span> If you don't select any agents then all the agents report will be displayed here otherwise it will show only selected agents report.</span>
                                    </el-col>
                                </el-row>

                                <el-transfer
                                    v-model="include_or_exclude_agents"
                                    :data="sortedAgents"
                                    :titles="['Available Agents', 'Selected Agents']"
                                    filterable
                                />
                            </div>
                        </template>
                        <template #footer>
                            <el-button @click="open_setting = false">{{ $t('Cancel') }}</el-button>
                            <el-button type="primary" @click="syncSummary">{{ $t('Save') }}</el-button>
                        </template>
                    </modal>
                </Teleport>

                <Teleport to="body">
                    <modal :show="open_export_options" :title="$t('exclude_or_include_summary_column')" @close="open_export_options = false">
                        <template #body>
                            <div class="fs_summary_settings">
                                <el-row :gutter="20">
                                    <el-col :span="18">
                                        <span> If you don't select any column, by default system will take all.</span>
                                    </el-col>
                                </el-row>
                                <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate" @change="handleCheckAllChange">
                                    <template v-if="!checkAll">Check all</template>
                                    <template v-else>Uncheck all</template>
                                </el-checkbox>
                                <el-checkbox-group v-model="selected_options" class="fs_summary_export_items" @change="handleColumnChanges">
                                    <el-checkbox v-for="(item, index) in repost_export_options" :key="index" :model-value="index" :label="item" />
                                </el-checkbox-group>

                            </div>
                        </template>
                        <template #footer>
                            <el-button @click="open_export_options = false">{{ $t('Cancel') }}</el-button>
                            <el-button type="primary" @click="exportReport">{{ $t('Export Agents Summary') }}</el-button>
                        </template>
                    </modal>
                </Teleport>

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
import Modal from '../../Pieces/Modal';

export default {
    name: 'AgentReports',
    components: {Modal},
    props: ['url', 'show_settings', 'show_export_btn'],
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
                    text: this.$t('Today'),
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        return [start, end]
                    })(),
                },
                {
                    text: this.$t('Yesterday'),
                    value: (() => {
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24);
                        return [start, start]
                    })(),
                },
                {
                    text: this.$t('Last Week'),
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 7)
                        return [start, end]
                    })(),
                },
                {
                    text: this.$t('Last Month'),
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 30)
                        return [start, end]
                    })(),
                },
                {
                    text: this.$t('Last 3 Months'),
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 90)
                        return [start, end]
                    })(),
                },
                {
                    text: this.$t('Last 6 Months'),
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 180)
                        return [start, end]
                    })(),
                },
                {
                    text: this.$t('Last 1 Year'),
                    value: (() => {
                        const end = new Date()
                        const start = new Date()
                        start.setTime(start.getTime() - 3600 * 1000 * 24 * 360)
                        return [start, end]
                    })(),
                }
            ],
            valueFormat: 'YYYY-MM-DD',
            open_setting: false,
            include_or_exclude_agents: [],
            include_or_exclude: 'include', // Define if we are including or excluding agents, default is include
            open_export_options: false,
            repost_export_options: this.appVars.repost_export_options,
            selected_options: [],
            checkAll: false,
            isIndeterminate: false,
        }
    },
    computed: {
        sortedAgents(){
            let agents = [];
            each(this.appVars.support_agents, (agent) => {
                agents.push({
                    key : parseInt(agent.id),
                    label : agent.full_name
                })
            })
            return agents;
        },
        handleCheckAllChange(){
            this.selected_options = this.checkAll ? Object.keys(this.repost_export_options) : [];
            this.isIndeterminate = true;
        },
        handleColumnChanges(){
            if(this.selected_options.length === Object.keys(this.repost_export_options).length){
                this.checkAll = true;
                this.isIndeterminate = false;
            }else if(this.selected_options.length === 0) {
                this.checkAll = false;
                this.isIndeterminate = false;
            }else{
                this.isIndeterminate = true;
            }
        },
        sortedReports() {
            let reports = this.reports;

            const settings = this.$getData('agents_summary_setting');

            if (this.url != 'my-reports/my-summary' && settings) {
                let reportsToInclude = [];
                each(reports, report => {
                    if (settings.agents.includes(report.id)){
                        reportsToInclude.push(report);
                    }
                })
                reports = reportsToInclude;
                console.log(reports);
            }

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

        showOrHideSummaries() {
            if (this.url == 'my-reports/my-summary') {
                return false;
            }
            return true;
        },
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

        fetchSummarySettings(){
            const settings = this.$getData('agents_summary_setting');
            return this.include_or_exclude_agents = settings.agents;
        },

        syncSummary() {
            this.loading = true;
            this.$saveData('agents_summary_setting', {
                agents: this.include_or_exclude_agents
            });
            this.open_setting = false;
            this.getReport();
        },

        handleSorting(props) {
            this.sort_column = props.prop;
            this.sort_type = props.order;
        },

        onlyPastDates(val) {
            return new Date() <= val;
        },

        getSummaries(param) {
            const {columns} = param;
            const sums = [];
            columns.forEach((column, index) => {
                if (index === 0) {
                    sums[index] = this.$t("Total Summaries");
                    return;
                }
                sums[index] = this.totals[column.property];
            });
            return sums;
        },

        exportReport(){
            let from = (this.date_range) ? dayjs(this.date_range[0]).format('YYYY-MM-DD') : '';
            let to = (this.date_range) ? dayjs(this.date_range[1]).format('YYYY-MM-DD') : '';
            let agents_summary_setting = this.$getData('agents_summary_setting');
            let agents = '';
            if(agents_summary_setting.agents.length){
                agents = agents_summary_setting.agents.toString();
            }

            location.href = window.ajaxurl + '?' + jQuery.param({
                action: 'fs_export_agent_report',
                columns: this.selected_options,
                from_date: from,
                to_date: to,
                agents: agents,
                format: 'csv'
            });
        }
    },
    mounted() {
        this.getReport();
        this.fetchSummarySettings();
    }
}
</script>

<style lang="scss" scoped>
    .fs_summary_settings div{
        margin: 10px 0;
    }
    .fs_summary_settings_icon{
        margin-right: 10px;
        margin-top: 2px;
        cursor: pointer;
    }
    .fs_summary_export_icon{
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
    .fs_summary_export_items label{
        margin-top: 10px;
        width: 45%;
    }
</style>
