<template>
    <div class="dashboard fs_box_wrapper">

        <div v-html="dashboard_notice"></div>
        <div class="fs_dash_mentioned_ticket" v-if="mentioned_tickets.length">
            <div class="fs_box fs_dashboard_box">
                <div class="fs_box_header">
                    {{$t('Good')}} {{greetingTime}} {{me.full_name}},
                    <span style="font-weight: normal;">
                    {{$t('dashboard_sub_heading2')}}
                </span>
                </div>
                <div class="fs_box_body">
                    <template v-if="!loading">
                        <ul v-if="mentioned_tickets.length" class="fs_card_list">
                            <li v-for="ticket in mentioned_tickets" :key="ticket.id">
                                <div class="fs_suggested_ticket">
                                    <div class="fs_ticket_info">
                                        <router-link tag="li" :to="{ name: 'view_ticket', params: { ticket_id: ticket.id } }">
                                            <img class="fs_inline_photo_40" :src="ticket.customer.photo" />
                                            <span style="color: #3C434A; font-size: 15px; font-weight: 400;">{{ticket.title}}</span>
                                        </router-link>
                                    </div>
                                    <div class="fs_ticket_status">
                                    <span style="padding: 3px 6px;line-height: 100%;margin-left: 5px;margin-top: 7px;font-weight: 500;font-size:14px;"
                                          class="fs_badge" :class="'fs_badge_'+ticket.status">
                                        {{ticket.status}}
                                    </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </template>
                    <div class="fs_padded_20" v-else>
                        <el-skeleton :rows="3" animated/>
                    </div>
                </div>
            </div>
        </div>

        <div class="fs_dash_suggested_ticket">
            <div class="fs_box fs_dashboard_box">
                <div class="fs_box_header">
                    <span v-if="mentioned_tickets.length <= 0">{{$t('Good')}} {{greetingTime}} {{me.full_name}},</span>
                    <span style="font-weight: normal;">
                    {{$t('dashboard_sub_heading')}}
                </span>
                </div>
                <div class="fs_box_body">
                    <template v-if="!loading">
                        <ul v-if="suggested_tickets.length" class="fs_card_list">
                            <li v-for="ticket in suggested_tickets" :key="ticket.id">
                                <div class="fs_suggested_ticket">
                                    <div class="fs_ticket_info">
                                        <router-link tag="li" :to="{ name: 'view_ticket', params: { ticket_id: ticket.id } }">
                                            <img class="fs_inline_photo_40" :src="ticket.customer.photo" />
                                            <span style="color: #3C434A; font-size: 15px; font-weight: 400;">{{ticket.title}}</span>
                                        </router-link>
                                    </div>
                                    <div class="fs_ticket_status">
                                    <span style="padding: 3px 6px;line-height: 100%;margin-left: 5px;margin-top: 7px;font-weight: 500;font-size:14px;"
                                          class="fs_badge" :class="'fs_badge_'+ticket.status">
                                        {{ticket.status}}
                                    </span>
                                    </div>
                                </div>
                            </li>
                        </ul>

                        <p class="fs_padded_20 fs_stat_helper" v-else>{{$t('dashboard_all_catch_up')}} <b>{{$t('Good Job')}}, {{me.full_name}}!</b></p>

                        <p class="fs_padded_20 fs_stat_helper" v-if="overall_stats">
                            <span class="fs_highlight">{{overall_stats.waiting_tickets}} {{$t('tickets')}}</span> {{$t('are waiting for reply with')}}
                            <span class="fs_highlight"> {{$t('average')}} {{overall_stats.average_waiting}} {{$t('wait time')}}</span> & {{$t('max wait time')}}
                            <span class="fs_highlight">{{overall_stats.max_waiting}}</span>
                        </p>

                    </template>
                    <div class="fs_padded_20" v-else>
                        <el-skeleton :rows="3" animated/>
                    </div>
                </div>
            </div>
        </div>

        <div class="fs_box fs_dashboard_box">
            <div class="fs_box_header">
                {{$t('Your Overview for Today')}}
            </div>
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul class="fs_card_list">
                        <li style="padding: 15px;" v-for="(stat,statKey) in stats" :key="statKey">
                            <b>{{stat.title}}: </b> {{stat.count}}
                        </li>
                    </ul>
                    <p class="fs_padded_20 fs_stat_helper" v-if="individual_stat">
                        <span class="fs_highlight">{{individual_stat.waiting_tickets}} {{$t('tickets')}}</span> {{$t('are waiting for reply with')}}
                        <span class="fs_highlight"> {{$t('average')}} {{individual_stat.average_waiting}} {{$t('wait time')}}</span> & {{$t('max wait time')}}
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
            mentioned_tickets: [],
            overall_stats: false,
            individual_stat: false,
            dashboard_notice: ''
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
                g = this.$t("afternoon");
            } else if (currentHour >= split_evening) {
                g = this.$t("evening");
            } else {
                g = this.$t("morning");
            }

            return g;
        }
    },
    methods: {
        fetchStat() {
            this.loading = true;
            this.$get('tickets/my_stats', {
                with: ['suggested_tickets', 'overall_stats', 'individual_stat', 'mentioned_tickets']
            })
                .then(response => {
                    this.stats = response.stats;
                    this.suggested_tickets = response.suggested_tickets;
                    this.overall_stats = response.overall_stats;
                    this.individual_stat = response.individual_stat;
                    this.dashboard_notice = response.dashboard_notice;
                    this.mentioned_tickets = response.mentioned_tickets;
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

<style>
    .dashboard .fs_box_header{
        padding: 20px 15px;
        font-size: 16px;
        border-radius: 5px 5px 0 0;
    }
</style>
