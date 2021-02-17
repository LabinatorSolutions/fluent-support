<template>
    <div class="dashboard">
        <div class="header">Dashboard</div>
        <p>Hello From Dashboard</p>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'Dashboard',
        data() {
            return {
                tickets: []
            }
        },
        methods: {
            getTickets() {
                this.$get('tickets', {})
                    .then(response => {
                        console.log(response);
                    })
            }
        },
        mounted() {
            console.log('OK');
            this.getTickets();
        }
    };
</script>
