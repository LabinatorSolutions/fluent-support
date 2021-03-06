<template>
    <div class="fs_mailboxes">
        <div v-if="mailbox" class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Settings: {{ mailbox.name }}</h3>
                </div>
                <div class="fs_box_actions">

                </div>
            </div>
            <div class="fs_box_body fs_padded_20">
                <router-view :mailbox="mailbox" />
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'MailBoxRoot',
    props: ['box_id'],
    data() {
        return {
            fetching: true,
            mailbox: false
        }
    },
    methods: {
        fetch() {
            this.fetching = true;
            this.$get(`mailboxes/${this.box_id}`)
                .then((response) => {
                    this.mailbox = response.mailbox
                })
                .catch((errors) => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.fetching = false;
                })
        }
    },
    mounted() {
        this.fetch();
    }
}
</script>
