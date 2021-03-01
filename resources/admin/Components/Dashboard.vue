<template>
    <div class="dashboard">
        <h2>Good {{ greetingTime }}, <b>{{ me.first_name }}</b>. Here are your today's stats</h2>
        <div v-loading="loading" class="fs_dash_cards">
            <div v-for="(stat, stat_type) in stats" :key="stat_type" class="fs_dash_card">
                <div class="dash_card_title">{{stat.title}}</div>
                <div class="dash_card_count">
                    {{stat.count}}
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'Dashboard',
    data() {
        return {
            me: this.appVars.me,
            can_access_unassigned_tickets: false,
            loading: false,
            stats: {}
        }
    },
    computed: {
        greetingTime() {
            const m = this.moment();
            let g = null; //return g

            if (!m || !m.isValid()) {
                return;
            } //if we can't find a valid or filled moment, we return.

            const split_afternoon = 12 //24hr time to split the afternoon
            const split_evening = 17 //24hr time to split the evening
            const currentHour = parseFloat(m.format("HH"));

            if (currentHour >= split_afternoon && currentHour <= split_evening) {
                g = "afternoon";
            } else if (currentHour >= split_evening) {
                g = "evening";
            } else {
                g = "morning";
            }

            return g;
        }
    },
    methods: {
        fetchStat() {
            this.loading = true;
            this.$get('tickets/my_stats')
                .then(response => {
                    this.stats = response.stats;
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
        this.can_access_unassigned_tickets = this.appVars.me.permissions.indexOf('fst_manage_unassigned_tickets') > -1
        this.fetchStat();
    }
};
</script>
