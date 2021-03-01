<template>
    <div v-if="ticket.live_activity && ticket.live_activity.length > 1" class="fs_active_agents">
        <ul>
            <li @click="activityOn()" v-loading="loading" class="fs_active_agent_title">Currently Viewing</li>
            <li v-for="activity in ticket.live_activity" :key="activity.id">
                <span class="fs_agent_photo_icon"><img :src="activity.photo"/></span>
            </li>
        </ul>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'active_agents',
    props: ['ticket'],
    data() {
        return {
            ticket_id: this.ticket.id,
            loading: false,
            app_off: false
        }
    },
    methods: {
        scheduleNextEvent() {
            const randTimer = Math.random() * 20000 + 30000;
            setTimeout(() => {
                if(!this.loading) {
                    this.activityOn();
                }
            }, randTimer);
        },
        activityOn() {
            if(this.loading || this.app_off) {
                return ;
            }
            this.loading = true;
            this.$get(`tickets/${this.ticket_id}/live_activity`)
                .then(response => {
                    this.ticket.live_activity = response.live_activity;
                    this.scheduleNextEvent();
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        activityOff() {
            this.app_off = true;
            this.$del(`tickets/${this.ticket_id}/live_activity`);
        }
    },
    mounted() {
        setTimeout(() => {
            this.activityOn();
        }, 30000);
    },
    beforeUnmount() {
        this.activityOff();
    }
}
</script>
