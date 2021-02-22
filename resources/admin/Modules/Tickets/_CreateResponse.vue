<template>
    <div class="fs_create_response" :class="'fs_reply_type_'+type">
        <wp-editor v-model="response_body" />
        <div class="fs_row">
            <div class="fs_half">
                <attachment-form :ticket="ticket" :attachments="attachments" />
            </div>
            <div class="fs_half">
                <div class="fs_response_actions">
                    <el-button v-loading="creating" @click="create()" size="small" type="success">
                        <span v-if="type== 'note'">Add Internal Note</span>
                        <span v-else>Reply</span>
                    </el-button>
                    <p v-if="type== 'note'">You are adding internal Note. Only support staffs can see this note</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import AttachmentForm from './_AttachmentForm';

export default {
    name: 'CreateResponse',
    props: ['ticket', 'type'],
    components: {
        WpEditor,
        AttachmentForm
    },
    data() {
        return {
            response_body: '',
            creating: false,
            close_ticket: 'no',
            attachments: []
        }
    },
    methods: {
        create() {
            this.creating = true;
            this.$post(`tickets/${this.ticket.id}/responses`, {
                content: this.response_body,
                conversation_type: this.type,
                close_ticket: this.close_ticket,
                attachments: this.attachments
            })
            .then(response => {
                this.$notify.success(response.message);
                this.response_body = '';
                this.$emit('created', response.response, response);
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
