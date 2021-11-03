<template>
    <div class="fst_reply_box" :class=" (is_focused) ? 'fst_reply_box_focused' : '' ">
        <textarea @click="is_focused = true" v-if="!is_focused" class="fs_reply_text" :placeholder="$t('Click Here to Write a reply')"></textarea>

        <div v-else class="fs_reply_wrap">
            <h3>{{$t('Write a reply')}}</h3>
            <div>
                <wp-editor :autofocus="true" :mediaButtons="false" :height="150" v-model="response_body" />
                <error :error="errors.get('content')"/>
            </div>

            <div class="fs_row">
                <div class="fs_half">
                    <attachment-form :ticket="ticket" :attachments="attachments" />
                </div>
                <div class="fs_half">
                    <div class="fs_response_actions">
                        <el-checkbox class="fs_close_checkbox" v-model="close_ticket" true-label="yes" false-label="no">{{$t('Close Ticket')}}</el-checkbox>
                        <el-button @click="reply()" size="small" type="success">{{$t('Reply')}}</el-button>
                    </div>
                </div>
            </div>

            <error :error="errors.get('permission_error')"/>

            <p style="text-align: center;" v-if="ticket.privacy == 'private'">{{$t('This ticket is')}} <b>{{$t('Private')}}</b>. {{$t('agent_and_officials_can_see')}}</p>
            <p style="text-align: center;" v-else>This ticket is <b>{{$t('Public')}}</b>. {{$t('not_to_share_private_info')}}</p>
        </div>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../admin/Pieces/_wp_editor';
import AttachmentForm from '../../admin/Modules/Tickets/_AttachmentForm';
import Error from '../../admin/Pieces/Error';
import Errors from '../../admin/Bits/Errors';

export default {
    name: 'InlineReply',
    props: ['ticket'],
    components: {
        WpEditor,
        AttachmentForm,
        Error
    },
    data() {
        return {
            errors: new Errors(),
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
            this.errors.clear();
            this.$post(`tickets/${this.ticket.id}/responses`, {
                content: this.response_body,
                conversation_type: this.type,
                close_ticket: this.close_ticket,
                attachments: this.attachments,
                intended_ticket_hash: this.appVars.intended_ticket_hash
            })
                .then(response => {
                  //  this.$notify.success(response.message);
                    this.response_body = '';
                    this.is_focused = false;
                    this.attachments = [];
                    this.$emit('created', response.response, response.ticket);
                })
                .catch((errors) => {
                    if (errors.responseJSON && errors.responseJSON.message) {
                        errors.responseJSON.permission_error = [errors.responseJSON.message];
                    }

                    this.errors.record(errors);

                    console.log(errors.responseJSON);
                })
                .always(() => {
                    this.creating = false;
                });
        }
    }
}
</script>
