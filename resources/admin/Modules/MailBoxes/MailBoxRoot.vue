<template>
    <div class="fs_mailboxes">
        <div v-if="mailbox" class="fs_box_wrapper">
            <div style="margin-bottom: 20px;" class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Settings: {{ mailbox.name }}</h3>
                </div>
                <div class="fs_box_actions">

                </div>
            </div>
            <div class="fs_tickets_view fs_settings_view">
                <div class="inner_sidebar">
                    <ul>
                        <li>
                            <router-link :to="{ name: 'box_settings', params: { box_id: mailbox.id } }">
                                Business Settings
                            </router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'email_settings', params: { box_id: mailbox.id } }">
                                Email Settings
                            </router-link>
                        </li>
                    </ul>
                </div>
                <div class="inner_body fs_box_body fs_padded_20">
                    <router-view :mailbox="mailbox"/>
                </div>

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
