<template>
    <div class="fs_create_response">
        <wp-editor v-model="response_body" />
        <div class="fs_response_actions">
            <el-button v-loading="creating" @click="create()" size="small" type="success">Reply</el-button>
        </div>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';

export default {
    name: 'CreateResponse',
    props: ['ticket', 'type'],
    components: {
        WpEditor
    },
    data() {
        return {
            response_body: '',
            creating: false,
            close_ticket: 'no'
        }
    },
    methods: {
        create() {
            this.creating = true;
            this.$post(`tickets/${this.ticket.id}/responses`, {
                content: this.response_body,
                conversation_type: this.type,
                close_ticket: this.close_ticket
            })
            .then(response => {
                this.$notify.success(response.message);
                this.response_body = '';
                this.$emit('created', response.response, response.ticket);
            })
            .catch((errors) => {
                this.$handleError(errors);
            })
            .always(() => {
                this.creating = false;
            });
        }
    }
}
</script>
