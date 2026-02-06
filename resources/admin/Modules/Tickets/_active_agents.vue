<template>
    <div v-if="ticket.live_activity && ticket.live_activity.length > 1" class="fs_active_agents">
        <div class="fs_active_agents_content">
            <div class="fs_active_agents_avatars">
                <div
                    v-for="(activity, index) in visibleAvatars"
                    :key="activity.id"
                    class="fs_active_agent_avatar"
                    :style="{ zIndex: index + 1 }"
                >
                    <img :src="activity.photo" :alt="activity.name || 'Agent'" />
                </div>
                <div v-if="remainingCount > 0" class="fs_active_agent_count">
                    +{{ remainingCount }}
                </div>
            </div>
            <div class="fs_active_agents_text">
                {{ totalCount }} {{ $t('People Watching') }}
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import IconPack from '../../Components/IconPack.vue';

export default {
    name: 'active_agents',
    components: {
        IconPack
    },
    props: {
        ticket: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            ticket_id: this.ticket.id,
            loading: false,
            app_off: false,
        };
    },
    computed: {
        totalCount() {
            return this.ticket.live_activity ? this.ticket.live_activity.length : 0;
        },
        visibleAvatars() {
            if (!this.ticket.live_activity) return [];
            return this.ticket.live_activity.slice(0, 3);
        },
        remainingCount() {
            return Math.max(0, this.totalCount - 3);
        }
    },
    mounted() {
        setTimeout(() => {
            this.activityOn();
        }, 30000);
    },
    beforeUnmount() {
        this.activityOff();
    },
    methods: {
        scheduleNextEvent() {
            const randTimer = Math.random() * 20000 + 30000;
            setTimeout(() => {
                if (!this.loading) {
                    this.activityOn();
                }
            }, randTimer);
        },

        activityOn() {
            if (this.loading || this.app_off) return;

            this.loading = true;

            this.$get(`tickets/${this.ticket_id}/live_activity`)
                .then((response) => {
                    this.ticket.live_activity = response.live_activity;
                    this.scheduleNextEvent();
                })
                .catch((errors) => {
                    console.error(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        activityOff() {
            this.app_off = true;
            this.$del(`tickets/${this.ticket_id}/live_activity`);
        },
    }
}
</script>



