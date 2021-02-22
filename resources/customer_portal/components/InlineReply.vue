<template>
    <div class="fst_reply_box" :class=" (is_focused) ? 'fst_reply_box_focused' : '' ">
        <textarea @click="is_focused = true" v-if="!is_focused" class="fs_reply_text" placeholder="Click Here to Write a reply"></textarea>

        <div v-else class="fs_reply_wrap">
            <h3>Write a reply</h3>
            <wp-editor :mediaButtons="false" :height="150" v-model="response_body" />
            <div class="fs_row">
                <div class="fs_half">
                    <attachment-form :ticket="ticket" :attachments="attachments" />
                </div>
                <div class="fs_half">
                    <div class="fs_response_actions">
                        <el-checkbox class="fs_close_checkbox" v-model="close_ticket" true-label="yes" false-label="no">Close Ticket</el-checkbox>
                        <el-button v-loading="creating" @click="reply()" size="small" type="success">Reply</el-button>
                    </div>
                </div>
            </div>

            <p style="text-align: center;" v-if="ticket.privacy == 'private'">This ticket is <b>Private</b>. Only you and official support agents can view this conversations</p>
            <p style="text-align: center;" v-else>This ticket is <b>Public</b>. Please do not share any private information.</p>
        </div>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../admin/Pieces/_wp_editor';
import AttachmentForm from '../../admin/Modules/Tickets/_AttachmentForm';

export default {
    name: 'InlineReply',
    props: ['ticket'],
    components: {
        WpEditor,
        AttachmentForm
    },
    data() {
        return {
            is_focused: false,
            response_body: '',
            close_ticket: 'no',
            creating: false,
            attachments: []
        }
    },
    methods: {
        reply() {
            this.creating = true;
            this.$post(`tickets/${this.ticket.id}/responses`, {
                content: this.response_body,
                conversation_type: this.type,
                close_ticket: this.close_ticket,
                attachments: this.attachments
            })
                .then(response => {
                  //  this.$notify.success(response.message);
                    this.response_body = '';
                    this.is_focused = false;
                    this.$emit('created', response.response, response.ticket);
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        }
    }
}
</script>
