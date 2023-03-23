<template>
    <div class="fs_create_response" :class="'fs_reply_type_'+type">
        <wp-editor :autofocus="true" v-if="editor_ready" v-model="response_body" :show-shortcodes="true"
                   :show-saved-replies="true"/>
        <div class="fs_row">
            <div class="fs_half">
                <div style="text-align: left" class="fs_response_actions">
                    <el-button v-loading="creating" :disabled="creating" @click="create('no')" size="large"
                               type="success">
                        <span v-if="type== 'note'">{{ translate('Add Internal Note') }}</span>
                        <span v-else>{{ translate('Add Reply') }}</span>
                    </el-button>
                    <el-button v-if="type != 'note'" :disabled="creating" @click="create('yes')" size="large"
                               type="danger">
                        {{ translate('Reply and Close') }}
                    </el-button>
                    <p v-if="type== 'note'">{{ translate('internal_note_warning') }}</p>
                </div>
            </div>
            <div class="fs_half">
                <attachment-form v-if="appVars.has_file_upload" :ticket="ticket" :attachments="attachments"/>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">

import {reactive, toRefs, watch} from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import WpEditor from '../../Pieces/_wp_editor';
import AttachmentForm from './_AttachmentForm';
import {debounce} from 'lodash'

export default {
    name: 'CreateResponse',
    props: ['ticket', 'type'],
    components: {
        WpEditor,
        AttachmentForm
    },

    setup(props, {emit}) {

        const {
            post, translate, handleError, saveData,
            getData, removeData
        } = useFluentHelper();
        const {notify} = useNotify();

        const state = reactive({
            response_body: '',
            creating: false,
            close_ticket: 'no',
            attachments: [],
            editor_ready: true,
            shortcodes: {
                '{{customer.first_name}}': 'Customer First Name',
                '{{customer.last_name}}': 'Customer Last Name',
                '{{customer.full_name}}': 'Customer Full Name',
                '{{customer.email}}': 'Customer Email',
                '{{agent.first_name}}': 'Agent First Name',
                '{{agent.last_name}}': 'Agent Last Name',
                '{{agent.full_name}}': 'Agent Full Name',
                '{{agent.email}}': 'Agent Email',
            }
        });

        let previousResponse = getData("ticket_no_" + props.ticket.id + "_response_draft");
        if (previousResponse) {
            state.response_body = previousResponse;
        }

        const saveResponseDraft = debounce(() => {
            saveData("ticket_no_" + props.ticket.id + "_response_draft", state.response_body);
        }, 5000)

        watch(state, saveResponseDraft)

        const create = (closed = 'no') => {

            const data = {
                content: state.response_body,
                conversation_type: props.type,
                close_ticket: closed,
                attachments: state.attachments
            };

            let action = `tickets/${props.ticket.id}/responses`;
            if (Array.isArray(props.ticket)) {
                data.ticket_ids = props.ticket;
                data.bulk_action = 'reply_tickets';
                action = 'tickets/bulk-reply';
            }

            state.creating = true;
            post(action, data)
                .then(response => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    removeData("ticket_no_" + props.ticket.id + "_response_draft");
                    state.response_body = '';
                    emit('created', response.response, response);
                    state.attachments = [];
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.creating = false;
                });
        }

        return {
            ...toRefs(state),
            create,
            translate,
        };
    }
}
</script>



