<template>
    <div class="fc_box_email_settings">
        <el-skeleton v-if="!configs.length" :rows="5" animated/>
        <el-table v-else :data="configs" stripe>
            <el-table-column :label="$t('Title')" prop="title" width="400" />
            <el-table-column :label="$t('Status')">
                <template #default="scope">
                    <CircleCheck v-if="scope.row.status == 'yes'" style="width: 1.3em; height: 1.3em;" color="#67c23a" />
                    <CircleClose v-else style="width: 1.3em; height: 1.3em;" color="#f56c6c" />
                </template>
            </el-table-column>
            <el-table-column :label="$t('Manage')">
                <template #default="scope">
                    <el-button @click="editEmail(scope.row)" type="text" icon="EditPen" />
                </template>
            </el-table-column>
        </el-table>
    </div>

    <Teleport to="body">
        <modal :show="edit_modal" :title="active_email_settings.title" @close="edit_modal = false">
            <template #body>
                <h3>{{active_email_settings.description}}</h3>
                <el-form :data="active_email_settings" label-position="top">
                    <el-form-item :label="$t('Email Subject')">
                        <el-input :disabled="active_email_settings.can_edit_subject == 'no'"
                                  v-model="active_email_settings.email_subject" :placeholder="$t('Email Subject')"/>
                        <p v-if="active_email_settings.can_edit_subject == 'no'">{{$t('can_not_edit_subject')}}</p>
                    </el-form-item>
                    <el-form-item :label="$t('Email Body')">
                        <wp-editor :editor_id="active_email_settings.key" v-model="active_email_settings.email_body"/>
                    </el-form-item>
                    <el-row :gutter="20">
                        <el-col :sm="24" :md="12">
                            <el-form-item>
                                <el-checkbox true-label="yes" false-label="no" v-model="active_email_settings.status">
                                    {{$t('enable_email')}}
                                </el-checkbox>
                                <el-checkbox true-label="yes" false-label="no" v-if="active_email_settings.status=='yes' && allowed_mails_for_attachments.includes(active_email_settings.key)" v-model="active_email_settings.send_attachments">
                                    {{$t('Send Attachments')}}
                                </el-checkbox>
                            </el-form-item>
                            <el-button @click="saveSettings" :disabled="saving" v-loading="saving" type="success">{{$t('Save Settings')}}</el-button>
                        </el-col>
                        <el-col :sm="24" :md="12">
                            <h3>{{$t('Available Smartcodes')}}</h3>
                            <ul class="fs_listed">
                                <li v-for="(codeName, code) in currentSmartCodes" :key="code"><b>{{codeName}}:</b> {{code}}
                                </li>
                            </ul>
                        </el-col>
                    </el-row>
                </el-form>
            </template>
        </modal>
    </Teleport>

</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import Modal from "../../Pieces/Modal";
import { computed, onMounted, reactive, toRefs } from 'vue';
import { useFluentHelper, useNotify } from '../../Composable/composables';

export default {
    name: 'BoxEmailSettings',
    components: {
        WpEditor,
        Modal
    },
    props: ['box_id','mailbox'],

    setup(props){

        const {$get, $put, $t, handleError} = useFluentHelper();
        const { notify } = useNotify();

        const state = reactive({
            active_email: '',
            email_types: [],
            active_email_settings: false,
            configs: [],
            edit_modal:false,
            loading: false,
            saving: false,
            allowed_mails_for_attachments: [
                'ticket_created_email_to_customer',
                'ticket_created_email_to_admin',
                'ticket_replied_by_agent_email_to_customer',
                'ticket_replied_by_customer_email_to_admin'
            ]
        });

        function getConfigs() {

            state.loading = true;

            $get(`mailboxes/${props.box_id}/email_configs`)
                .then((response) => {
                    state.configs = response.email_configs;
                    state.email_types = response.email_keys;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        }

        function editEmail(email) {
            $get(`mailboxes/${props.box_id}/email_settings?email_type=${email.key}`)
                .then(response => {
                    state.active_email_settings = response.email_settings;
                    state.edit_modal = !state.edit_modal;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });

            state.active_email = email.key;
            state.active_email_settings = false;
        }

        function saveSettings() {

            state.saving = true;

            $put(`mailboxes/${props.box_id}/email_settings`, {
                email_type: state.active_email_settings.key,
                email_settings: state.active_email_settings
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    state.edit_modal=false;
                    state.loading = true;
                    getConfigs();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        }


        const currentSmartCodes = computed(() =>{
            if (
                state.active_email == 'ticket_created_email_to_customer' ||
                state.active_email == 'ticket_created_email_to_admin'
            ) {
                return {
                    '{{customer.first_name}}': $t('Customer First Name'),
                    '{{customer.last_name}}': $t('Customer Last Name'),
                    '{{customer.full_name}}': $t('Customer Full Name'),
                    '{{customer.email}}': $t('Customer Email'),
                    '{{ticket.admin_url}}': $t('Ticket Link(Agent)'),
                    '{{ticket.public_url}}': $t('Ticket Link(Customer)'),
                    '{{ticket.id}}': $t('Ticket ID'),
                    '{{ticket.title}}': $t('Ticket Title'),
                    '{{ticket.content}}': $t('Ticket Content'),
                    '{{business.name}}': $t('Business Name')
                }
            } else if (state.active_email== 'ticket_agent_on_change') {
                return {
                    '{{ticket.admin_url}}' : $t('Ticket Link(Agent)'),
                    '{{ticket.id}}': $t('Ticket ID'),
                    '{{ticket.title}}': $t('Ticket Title'),
                    '{{ticket.content}}': $t('Ticket Content'),
                    '{{business.name}}': $t('Business Name'),
                    '{{agent.first_name}}': $t('Assigned Agent First Name'),
                    '{{agent.last_name}}': $t('AssignedAgent Last Name'),
                    '{{agent.full_name}}': $t('Assigned Agent Full Name'),
                    '{{assigner.first_name}}': $t('Assigner First Name'),
                    '{{assigner.last_name}}': $t('Assigner Last Name'),
                    '{{assigner.full_name}}': $t('Assigner Full Name')
                }
            }
            else {
                return {
                    '{{customer.first_name}}':  $t('Customer First Name'),
                    '{{customer.last_name}}': $t('Customer Last Name'),
                    '{{customer.full_name}}': $t('Customer Full Name'),
                    '{{customer.email}}': $t('Customer Email'),
                    '{{ticket.admin_url}}' : $t('Ticket Link(Agent)'),
                    '{{ticket.public_url}}': $t('Ticket Link(Customer)'),
                    '{{ticket.id}}': $t('Ticket ID'),
                    '{{ticket.title}}': $t('Ticket Title'),
                    '{{ticket.content}}': $t('Ticket Content'),
                    '{{business.name}}': $t('Business Name'),
                    '{{agent.first_name}}': $t('Agent First Name'),
                    '{{agent.last_name}}': $t('Agent Last Name'),
                    '{{agent.full_name}}': $t('Agent Full Name'),
                    '{{response.title}}': $t('Response Title'),
                    '{{response.content}}': $t('Response Content')
                }
            }
        })
    onMounted(()=> {
        getConfigs();
    })


    return {
        ...toRefs(state),
        getConfigs,
        editEmail,
        saveSettings,
        currentSmartCodes
    }
    },
}
</script>
