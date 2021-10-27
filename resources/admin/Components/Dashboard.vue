<template>
    <div class="dashboard fs_box_wrapper">

        <div class="fs_box fs_dashboard_box">
            <div class="fs_box_header" style="padding: 20px 15px;font-size: 16px;">
                Good {{greetingTime}} {{me.full_name}},
                <span style="font-weight: normal;">
                    {{$t('dashboard_sub_heading')}}
                </span>
            </div>
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul v-if="suggested_tickets.length" class="fs_card_list">
                        <li v-for="ticket in suggested_tickets" :key="ticket.id">
                            <router-link tag="li" :to="{ name: 'view_ticket', params: { ticket_id: ticket.id } }">
                                <img class="fs_inline_photo_32" :src="ticket.customer.photo" />
                                <span>{{ticket.title}}</span>
                                <span style="padding: 0px 5px 2px;line-height: 100%;margin-left: 5px;margin-top: 7px;" class="fs_badge" :class="'fs_badge_'+ticket.status">{{ticket.status}}</span>
                            </router-link>
                        </li>
                    </ul>

                    <p class="fs_padded_20" v-else>{{$t('dashboard_all_catch_up')}} <b>Good Job, {{me.full_name}}!</b></p>

                    <p class="fs_padded_20" v-if="overall_stats">
                        <span class="fs_highlight">{{overall_stats.waiting_tickets}} tickets</span> are waiting for reply with
                        <span class="fs_highlight"> average {{overall_stats.average_waiting}} wait time</span> & max wait time
                        <span class="fs_highlight">{{overall_stats.max_waiting}}</span>
                    </p>

                </template>
                <div class="fs_padded_20" v-else>
                    <el-skeleton :rows="3" animated/>
                </div>
            </div>
        </div>

        <div class="fs_box fs_dashboard_box">
            <div class="fs_box_header" style="padding: 20px 15px;font-size: 16px;">
                {{$t('Your Overview for Today')}}
            </div>
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul class="fs_card_list">
                        <li style="padding: 15px;" v-for="(stat,statKey) in stats" :key="statKey">
                            <b>{{stat.title}}: </b> {{stat.count}}
                        </li>
                    </ul>
                    <p class="fs_padded_20" v-if="individual_stat">
                        <span class="fs_highlight">{{individual_stat.waiting_tickets}} tickets</span> are waiting for your reply with
                        <span class="fs_highlight"> average {{individual_stat.average_waiting}} wait time</span> & max wait time
                        <span class="fs_highlight">{{individual_stat.max_waiting}}</span>
                    </p>
                </template>
                <div class="fs_padded_20" v-else>
                    <el-skeleton :rows="3" animated/>
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
            stats: {},
            suggested_tickets: [],
            overall_stats: false,
            individual_stat: false
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
            this.$get('tickets/my_stats', {
                with: ['suggested_tickets', 'overall_stats', 'individual_stat']
            })
                .then(response => {
                    this.stats = response.stats;
                    this.suggested_tickets = response.suggested_tickets;
                    this.overall_stats = response.overall_stats;
                    this.individual_stat = response.individual_stat;
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
        if (!this.appVars.mailboxes.length) {
            this.$router.push({name: 'setup', query: { t: Date.now() }});
        }
        this.can_access_unassigned_tickets = this.appVars.me.permissions.indexOf('fst_manage_unassigned_tickets') > -1
        this.fetchStat();
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    }
};
</script>
