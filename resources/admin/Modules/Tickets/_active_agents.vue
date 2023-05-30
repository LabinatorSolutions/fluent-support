<template>
    <div v-if="ticket.live_activity && ticket.live_activity.length > 1" class="fs_active_agents">
        <ul>
            <li @click="activityOn()" v-loading="loading" class="fs_active_agent_title">
                {{ translate('Currently Viewing') }}
            </li>
            <li v-for="activity in ticket.live_activity" :key="activity.id">
                <span class="fs_agent_photo_icon"><img :src="activity.photo"/></span>
            </li>
        </ul>
    </div>
</template>

<script type="text/babel">
import {onBeforeUnmount, onMounted, reactive, toRefs} from "vue";
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'active_agents',
    props: ['ticket'],

    setup(props) {
        const {
            get,
            del,
            translate,
        } = useFluentHelper();
        const state = reactive({
            ticket_id: props.ticket.id,
            loading: false,
            app_off: false
        });

        const scheduleNextEvent = () => {
            const randTimer = Math.random() * 20000 + 30000;
            setTimeout(() => {
                if (!state.loading) {
                    activityOn();
                }
            }, randTimer);
        };

        const activityOn = () => {
            if (state.loading || state.app_off) {
                return;
            }
            state.loading = true;
            get(`tickets/${state.ticket_id}/live_activity`)
                .then(response => {
                    props.ticket.live_activity = response.live_activity;
                    scheduleNextEvent();
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const activityOff = () => {
            state.app_off = true;
            del(`tickets/${state.ticket_id}/live_activity`);
        }

        onMounted(() => {
            setTimeout(function () {
                activityOn();
            }, 30000);
        });

        onBeforeUnmount(() => {
            activityOff();
        });

        return {
            ...toRefs(state),
            scheduleNextEvent,
            activityOn,
            activityOff,
            translate,
        }
    }
}
</script>

<style>
.fs_active_agent_title {
    cursor: pointer;
}
</style>



