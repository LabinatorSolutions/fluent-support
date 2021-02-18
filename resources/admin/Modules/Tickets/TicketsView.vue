<template>
    <div class="fs_tickets_view">
        <div class="inner_sidebar">
            <ul>
                <li>
                    <router-link :to="{ name: 'tickets' }">All Tickets</router-link>
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
    }
}
</script>
