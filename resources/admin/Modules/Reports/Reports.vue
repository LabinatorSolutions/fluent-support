<template>
    <div class="fs_saved_replies">
        <div class="fs_box_wrapper">
            <h2>Overall Stats (Today)</h2>
            <div style="margin-top: 20px;" v-loading="loading" class="fs_dash_cards">
                <div v-for="(stat, stat_type) in overall_reports" :key="stat_type" class="fs_dash_card">
                    <div class="dash_card_title">{{stat.title}}</div>
                    <div class="dash_card_count">
                        {{stat.count}}
                    </div>
                </div>
            </div>

            <div v-for="report in agent_reports" class="fs_box_wrapper">
                <h2>Report for {{report.agent.full_name}} (today)</h2>
                <div style="margin-top: 20px;" v-loading="loading" class="fs_dash_cards">
                    <div v-for="(stat, stat_type) in report.reports" :key="stat_type" class="fs_dash_card">
                        <div class="dash_card_title">{{stat.title}}</div>
                        <div class="dash_card_count">
                            {{stat.count}}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'Reports',
    data() {
        return {
            agent_reports: [],
            loading: false,
            overall_reports: {}
        }
    },
    methods: {
        fetchReports() {
            this.loading = true;
            this.$get('reports')
                .then(response => {
                    this.overall_reports = response.overall_reports;
                    this.agent_reports = response.agent_reports;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        }
    },
    mounted() {
        this.fetchReports();
        this.$setTitle('Reports');
    }
}
</script>
