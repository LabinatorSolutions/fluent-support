<template>
    <div class="fs_create_response" :class="'fs_reply_type_'+type">
        <div class="fc_template_box" v-if="appVars.has_pro">
            <template-inserter @insert="insertTemplate"/>
        </div>
        <wp-editor :autofocus="true" v-if="editor_ready" v-model="response_body"/>
        <div class="fs_row">
            <div class="fs_half">
                <div style="text-align: left" class="fs_response_actions">
                    <el-button v-loading="creating" :disabled="creating" @click="create('no')" size="large" type="success">
                        <span v-if="type== 'note'">{{ $t('Add Internal Note') }}</span>
                        <span v-else>{{ $t('Add Reply') }}</span>
                    </el-button>
                    <el-button v-if="type != 'note'" :disabled="creating" @click="create('yes')" size="large" type="danger">
                        {{ $t('Reply and Close') }}
                    </el-button>
                    <p v-if="type== 'note'">{{ $t('internal_note_warning') }}</p>
                </div>
            </div>
            <div class="fs_half">
                <attachment-form v-if="appVars.has_file_upload" :ticket="ticket" :attachments="attachments"/>
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

            const data = {
                content: this.response_body,
                conversation_type: this.type,
                close_ticket: closed,
                attachments: this.attachments
            };

            let action = `tickets/${this.ticket.id}/responses`;
            if (Array.isArray(this.ticket)) {
                data.ticket_ids = this.ticket;
                data.bulk_action = 'reply_tickets';
                action = 'tickets/bulk-reply';
            }

            this.creating = true;
            this.$post(action, data)
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
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
            this.editor_ready = false;
            this.response_body = this.response_body + content;
            this.$nextTick(() => {
                this.editor_ready = true;
            });
        }
    }
}
</script>
