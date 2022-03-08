<template>
    <div class="fs_tickets_view">
        <div class="inner_sidebar">
            <ul>
                <li>
                    <router-link :class="{router_not_exactly_matched: isMine || isUnassigned}" :to="{ name: 'tickets' }"><el-icon style="vertical-align: middle;"> <Tickets/> </el-icon>{{$t('All Tickets')}}</router-link>
                </li>
                <li>
                    <router-link :class="{router_not_exactly_matched: !isMine}" :to="{ name: 'tickets', query: { agent_id: appVars.me.id } }"><el-icon> <User/> </el-icon>{{$t('My Tickets')}}</router-link>
                </li>
                <li>
                    <router-link :class="{router_not_exactly_matched: !isUnassigned }" :to="{ name: 'tickets', query: { agent_id: 'unassigned' } }"><el-icon> <View/> </el-icon>{{$t('Unassigned')}}</router-link>
                </li>
            </ul>
        </div>
        <div class="inner_body">
            <router-view key="tickets_view"></router-view>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'ticketsView',
    data() {
        return {

        }
    },
    computed: {
        isMine() {
            return this.appVars.me.id == this.$route.query.agent_id;
        },
        isUnassigned() {
            return this.$route.query.agent_id == 'unassigned';
        }
    }
}
</script>
