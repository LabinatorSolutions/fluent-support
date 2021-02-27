<template>
    <div class="fs_saved_replies">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Reports</h3>
                </div>
                <div class="fs_box_actions">

                </div>
            </div>
            <div class="fs_box_body fs_padded_20">

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
    }
}
</script>
