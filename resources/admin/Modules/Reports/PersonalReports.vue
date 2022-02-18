<template>
    <div class="fs_personal_report">
        <div class="fs_box_wrapper" v-loading="loading">
            <el-row :gutter="30">
                <el-col :sm="24" :md="16" :lg="18">
                    <div class="fs_box">
                        <div class="fs_box_header">
                            <div class="fs_header_title">
                                <el-dropdown style="display: inline-block; cursor: pointer; line-height: 100%;" @command="handleComponentChange" trigger="hover">
                                    <span class="el-dropdown-link">
                                        {{ chartMaps[currently_showing] }}
                                        <i class="el-icon-arrow-down el-icon--right"></i>
                                    </span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-for="(mapName, mapKey) in chartMaps"
                                                :key="mapKey"
                                                :command="mapKey"
                                            >
                                                {{ mapName }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <el-date-picker
                                    size="small"
                                    v-model="date_range"
                                    type="daterange"
                                    :range-separator="$t('To')"
                                    :start-placeholder="$t('Start')"
                                    :end-placeholder="$t('End')"
                                    :unlink-panels=true
                                    @change="filterReport"
                                    value-format="YYYY-MM-DD"
                                    style="float: right;"
                                >
                                </el-date-picker>
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <component v-if="showing_charts" :is="currently_showing" :date_range="date_range" :url="'my-reports'"></component>
                        </div>
                    </div>
                    <agent-reports :url="'my-reports/my-summary'" />
                </el-col>
                <el-col :sm="24" :md="8" :lg="6">
                    <div class="fs_box">
                        <div class="fs_box_header">
                            <div class="fs_header_title">
                                {{$t('Your Overall Stats')}}
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <ul v-if="!loading" class="fs_card_list">
                                <li style="padding: 15px;" v-for="(stat, stat_type) in overall_reports" :key="stat_type">
                                    <b>{{stat.title}}:</b>  {{stat.count}}
                                </li>
                            </ul>
                            <div class="fs_padded_20" v-else>
                                <el-skeleton :rows="3" animated/>
                            </div>
                        </div>
                    </div>

                    <div class="fs_box fs_today_stats" style="margin-top: 1.2500em">
                        <div class="fs_box_header">
                            <div class="fs_header_title">
                                {{$t('Today Stats')}}
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <ul v-if="!loading" class="fs_card_list">
                                <li style="padding: 15px;" v-for="(stat, stat_type) in today_reports" :key="stat_type">
                                    <b>{{stat.title}}:</b>  {{stat.count}}
                                </li>
                            </ul>
                            <div class="fs_padded_20" v-else>
                                <el-skeleton :rows="3" animated/>
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

export default {
    name: "PersonalReports",
    props: ['url'],
    components: {
        ResponseChart,
        ResolveChart,
        AgentReports
    },
    data() {
        return {
            loading: false,
            stat_loading: false,
            overall_reports: {},
            currently_showing: 'resolve-chart',
            date_range: ['', ''],
            showing_charts: true,
            chartMaps: {
                'resolve-chart': 'Resolve Stats',
                'response-chart': 'Response Stats'
            },
            me: this.appVars.me,
            today_reports: {}
        }
    },
    methods: {
        fetchReports() {
            this.loading = true;
            this.$get(this.url)
                .then(response => {
                    this.overall_reports = response.overall_reports;
                    this.today_reports = response.today_reports;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        handleComponentChange(item) {
            this.currently_showing = item;
        },
        filterReport() {
            const current = this.currently_showing;
            this.currently_showing = {
                render: () => {
                }
            };
            this.$nextTick(() => {
                this.currently_showing = current;
            });
        }
    },
    mounted() {
        this.fetchReports();
        this.$setTitle('Personal Reports');
    }
}
</script>
