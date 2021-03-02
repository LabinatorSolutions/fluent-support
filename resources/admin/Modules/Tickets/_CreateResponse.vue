<template>
    <div class="fs_create_response" :class="'fs_reply_type_'+type">
        <div class="fc_template_box">
            <template-inserter @insert="insertTemplate" />
        </div>
        <wp-editor v-if="editor_ready" v-model="response_body" />
        <div class="fs_row">
            <div class="fs_half">
                <div style="text-align: left" class="fs_response_actions">
                    <el-button v-loading="creating" @click="create('no')" size="large" type="success">
                        <span v-if="type== 'note'">Add Internal Note</span>
                        <span v-else>Add Reply</span>
                    </el-button>
                    <el-button v-if="type != 'note'" @click="create('yes')" size="large" type="danger">Relay and Close</el-button>
                    <p v-if="type== 'note'">You are adding internal Note. Only support staffs can see this note</p>
                </div>
            </div>
            <div class="fs_half">
                <attachment-form :ticket="ticket" :attachments="attachments" />
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import AttachmentForm from './_AttachmentForm';
import TemplateInserter from './_templateInserter';

export default {
    name: 'CreateResponse',
    props: ['ticket', 'type'],
    components: {
        WpEditor,
        AttachmentForm,
        TemplateInserter
    },
    data() {
        return {
            response_body: '',
            creating: false,
            close_ticket: 'no',
            attachments: [],
            editor_ready: true
        }
    },
    methods: {
        create(closed = 'no') {
            this.creating = true;
            this.$post(`tickets/${this.ticket.id}/responses`, {
                content: this.response_body,
                conversation_type: this.type,
                close_ticket: closed,
                attachments: this.attachments
            })
            .then(response => {
                this.$notify.success(response.message);
                this.response_body = '';
                this.$emit('created', response.response, response);
                this.attachments = [];
            })
            .catch((errors) => {
                this.$handleError(errors);
            })
            .always(() => {
                this.creating = false;
            });
        },
        insertTemplate(content) {
            console.log(content);
            this.editor_ready = false;
            this.response_body = content;
            this.$nextTick(() => {
                this.editor_ready = true;
            });
        }
    }
}
</script>
