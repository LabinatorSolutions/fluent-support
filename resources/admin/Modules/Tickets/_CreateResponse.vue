<template>
    <div class="fs_create_response" :class="'fs_reply_type_'+type">
        <div class="fs_row">
            <el-form-item :label="translate('Cc')">
                <el-select v-model="selected_cc" multiple filterable remote
                           placeholder="Please enter email"
                           :remote-method="searchCcCustomerEmails"
                           :loading="loading"
                           style="padding-left: 10px"
                >
                    <el-option
                        v-for="item in cc_emails"
                        :key="item"
                        :label="item"
                        :value="item"
                    />
                </el-select>
            </el-form-item>
        </div>
        <div class="fs_row">
            <el-form-item :label="translate('Bcc')">
                <el-select v-model="selected_bcc" multiple filterable remote
                           placeholder="Please enter email"
                           :remote-method="searchBccCustomerEmails"
                           :loading="loading"
                           style="padding-left: 5px"
                >
                    <el-option
                        v-for="item in bcc_emails"
                        :key="item"
                        :label="item"
                        :value="item"
                    />
                </el-select>
            </el-form-item>
        </div>
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

import {onMounted, reactive, toRefs, watch} from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import WpEditor from '../../Pieces/_wp_editor';
import AttachmentForm from './_AttachmentForm';
import {debounce} from 'lodash'

export default {
    name: 'CreateResponse',
    props: ['ticket', 'type','draft'],
    components: {
        WpEditor,
        AttachmentForm
    },

    setup(props, {emit}) {

        const {
            post, translate, handleError, removeData, appVars, get
        } = useFluentHelper();
        const {notify} = useNotify();

        const state = reactive({
            response_body: '',
            selected_cc: [],
            selected_bcc: [],
            cc_emails: [],
            bcc_emails: [],
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
            },
            draftID:'',
            loading: false
        });
        let isCreatingResponse = false;

        if(appVars.enable_draft_mode === 'yes') {
            if (props.draft) {
                state.response_body = props.draft.value.content;
                state.draftID = props.draft.id;
                state.selected_cc = props.draft.value.selected_cc;
                state.selected_bcc = props.draft.value.selected_bcc;
            }
             const saveResponseDraft = debounce(() => {
                const data = {
                    content: state.response_body,
                    draftID: state.draftID,
                    selected_cc: state.selected_cc,
                    selected_bcc: state.selected_bcc,
                    conversation_type: props.type,
                };

                 if(state.response_body !== '' && isCreatingResponse === false ) {
                     let action = `tickets/${props.ticket.id}/draft`;

                     post(action, data)
                         .then((response) => {
                             state.draftID = response.draftID;
                         })
                         .catch((errors) => {
                             handleError(errors);
                         })
                 }
            }, 5000)

            watch([() => state.response_body, () => state.cc_emails, () => state.bcc_emails], () => {
                 if(state.response_body === '' && state.draftID ) {
                    removeDraft();
                }
                saveResponseDraft();
            });
        }

        const removeDraft = () => {
            emit('discardDraft', state.draftID);
            state.draftID = '';
        }

        const create = (closed = 'no') => {
            isCreatingResponse = true;
            const data = {
                content: state.response_body,
                conversation_type: props.type,
                close_ticket: closed,
                attachments: state.attachments,
                cc_emails: state.selected_cc,
                bcc_emails: state.selected_bcc,
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
                        position: 'bottom-right',
                        offset: 50,
                    });
                    isCreatingResponse = false;
                    if(state.draftID){
                        removeDraft();
                    }
                    state.response_body = '';
                    state.selected_cc = [];
                    state.selected_bcc = [];
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

        const searchCustomerEmails = (type, query) => {
            let emails = state.selected_cc;
            if(emails !== '' && state.selected_bcc !== ''){
                emails = emails.concat(state.selected_bcc)
            }
            state.loading = true;
            get('customers', {search: query})
                .then((response) => {
                    let customers = response.customers.data;
                    if (type === 'cc') {
                        customers.forEach((item) => {
                            if(item.email && !emails.includes(item.email)){
                                state.cc_emails.push(item.email);
                            }
                        });
                    } else {
                        customers.forEach((item) => {
                            if(item.email && !emails.includes(item.email)){
                                state.bcc_emails.push(item.email);
                            }
                        });
                    }
                    state.loading = false;
                })
                .catch((errors) => {
                    handleError(errors);
                })
        }

        const searchCcCustomerEmails = (query) => {
            //Need to call APi to get customer emails except selected and bcc
            state.cc_emails = [];
            if (query) {
                setTimeout(() => {
                    searchCustomerEmails('cc', query);
                }, 200)
            }
        }

        const searchBccCustomerEmails = (query) => {
            state.bcc_emails = [];
            if (query) {
                setTimeout(() => {
                    searchCustomerEmails('bcc', query);
                }, 200)
            }
        }

        onMounted(() => {
            if(props.ticket.responses.length === 0){
                if(props.ticket.carbon_copy && props.ticket.carbon_copy !== ''){
                    state.selected_cc = props.ticket.carbon_copy?.split(',') || [];
                }
                if(props.ticket.blind_carbon_copy && props.ticket.blind_carbon_copy !== ''){
                    state.selected_bcc = props.ticket.blind_carbon_copy?.split(',') || [];
                }
            }else{
                let conversation = props.ticket.responses[0];
                if(conversation.cc_info && state.selected_cc.length === 0){
                    state.selected_cc = conversation.cc_info.cc_email;
                    state.selected_bcc = conversation.cc_info?.bcc_email;
                }
            }
        })

        return {
            ...toRefs(state),
            create,
            searchCcCustomerEmails,
            searchBccCustomerEmails,
            translate,
        };
    }
}
</script>



