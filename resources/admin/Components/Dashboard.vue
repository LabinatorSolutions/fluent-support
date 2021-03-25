<template>
    <div class="dashboard fs_box_wrapper">

        <div class="fs_box fs_dashboard_box">
            <div class="fs_box_header" style="padding: 20px 15px;font-size: 16px;">
                Good {{greetingTime}} {{me.full_name}}, Here are few tickets you may take a look
            </div>
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul v-if="suggested_tickets.length" class="fs_card_list">
                        <li v-for="ticket in suggested_tickets" :key="ticket.id">
                            <router-link tag="li" :to="{ name: 'view_ticket', params: { ticket_id: ticket.id } }">
                                <img class="fs_inline_photo_32" :src="ticket.customer.photo" />
                                <span>{{ticket.title}}</span>
                                <span style="padding: 0px 5px 2px;line-height: 100%;margin-left: 5px;margin-top: 7px;" class="fs_badge">{{ticket.status}}</span>
                            </router-link>
                        </li>
                    </ul>

                    <p class="fs_padded_20" v-else>Looks like you have done up everything for now. <b>Good Job, {{me.full_name}}!</b></p>

                </template>
                <div class="fs_padded_20" v-else>
                    <el-skeleton :rows="3" animated/>
                </div>
            </div>
        </div>

        <div class="fs_box fs_dashboard_box">
            <div class="fs_box_header" style="padding: 20px 15px;font-size: 16px;">
                Your Today's Overview
            </div>
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul class="fs_card_list">
                        <li style="padding: 15px;" v-for="(stat,statKey) in stats" :key="statKey">
                            <b>{{stat.title}}: </b> {{stat.count}}
                        </li>
                    </ul>
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
            suggested_tickets: []
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
                with: ['suggested_tickets']
            })
                .then(response => {
                    this.stats = response.stats;
                    this.suggested_tickets = response.suggested_tickets
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
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    }
};
</script>
