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

export default {
    name: 'BoxEmailSettings',
    components: {
        WpEditor,
        Modal
    },
    props: ['box_id','mailbox'],
    data() {
        return {
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
        }
    },
    computed: {
        currentSmartCodes() {
            if (
                this.active_email == 'ticket_created_email_to_customer' ||
                this.active_email == 'ticket_created_email_to_admin'
            ) {
                return {
                    '{{customer.first_name}}': this.$t('Customer First Name'),
                    '{{customer.last_name}}': this.$t('Customer Last Name'),
                    '{{customer.full_name}}': this.$t('Customer Full Name'),
                    '{{customer.email}}': this.$t('Customer Email'),
                    '{{ticket.admin_url}}': this.$t('Ticket Link(Agent)'),
                    '{{ticket.public_url}}': this.$t('Ticket Link(Customer)'),
                    '{{ticket.id}}': this.$t('Ticket ID'),
                    '{{ticket.title}}': this.$t('Ticket Title'),
                    '{{ticket.content}}': this.$t('Ticket Content'),
                    '{{business.name}}': this.$t('Business Name')
                }
            } else if (this.active_email== 'ticket_agent_on_change') {
                return {
                    '{{ticket.admin_url}}' : this.$t('Ticket Link(Agent)'),
                    '{{ticket.id}}': this.$t('Ticket ID'),
                    '{{ticket.title}}': this.$t('Ticket Title'),
                    '{{ticket.content}}': this.$t('Ticket Content'),
                    '{{business.name}}': this.$t('Business Name'),
                    '{{agent.first_name}}': this.$t('Assigned Agent First Name'),
                    '{{agent.last_name}}': this.$t('AssignedAgent Last Name'),
                    '{{agent.full_name}}': this.$t('Assigned Agent Full Name'),
                    '{{assigner.first_name}}': this.$t('Assigner First Name'),
                    '{{assigner.last_name}}': this.$t('Assigner Last Name'),
                    '{{assigner.full_name}}': this.$t('Assigner Full Name')
                }
            }
            else {
                return {
                    '{{customer.first_name}}':  this.$t('Customer First Name'),
                    '{{customer.last_name}}': this.$t('Customer Last Name'),
                    '{{customer.full_name}}': this.$t('Customer Full Name'),
                    '{{customer.email}}': this.$t('Customer Email'),
                    '{{ticket.admin_url}}' : this.$t('Ticket Link(Agent)'),
                    '{{ticket.public_url}}': this.$t('Ticket Link(Customer)'),
                    '{{ticket.id}}': this.$t('Ticket ID'),
                    '{{ticket.title}}': this.$t('Ticket Title'),
                    '{{ticket.content}}': this.$t('Ticket Content'),
                    '{{business.name}}': this.$t('Business Name'),
                    '{{agent.first_name}}': this.$t('Agent First Name'),
                    '{{agent.last_name}}': this.$t('Agent Last Name'),
                    '{{agent.full_name}}': this.$t('Agent Full Name'),
                    '{{response.title}}': this.$t('Response Title'),
                    '{{response.content}}': this.$t('Response Content')
                }
            }
        }
    },
    methods: {
        getConfigs() {
            this.loading = true;
            this.$get(`mailboxes/${this.box_id}/email_configs`)
                .then((response) => {
                    this.configs = response.email_configs;
                    this.email_types = response.email_keys;
                })
                .catch((errors) => {
                    this.handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        editEmail(email) {
            this.$get(`mailboxes/${this.box_id}/email_settings?email_type=${email.key}`)
                .then(response => {
                    this.active_email_settings = response.email_settings;
                    this.edit_modal = !this.edit_modal;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });

            this.active_email = email.key;
            this.active_email_settings = false;
        },
        saveSettings() {
            this.saving = true;
            this.$put(`mailboxes/${this.box_id}/email_settings`, {
                email_type: this.active_email_settings.key,
                email_settings: this.active_email_settings
            })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.edit_modal=false;
                    this.loading = true;
                    this.getConfigs();
                })
                .catch((errors) => {
                    this.handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
    },
    mounted() {
        this.getConfigs();
    }
}
</script>
