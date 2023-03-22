<template>
    <div class="fst_verify_email" v-if="appVars.customer_status==='unverified'">
        <verify-email />
    </div>
    <div class="fst_tickets" v-else>
        <tickets />
    </div>
</template>

<script type="text/babel">
import Tickets from './Tickets';
export default {
    name: 'Dashboard',
    components: {
        Tickets,
        VerifyEmail: () => true ? import('./VerifyEmail') : undefined
    },
    beforeCreate() {
        if(this.appVars.customer_status==='unverified') {
            this.$options.components.VerifyEmail = require('./VerifyEmail').default
        }
    }
}
</script>
