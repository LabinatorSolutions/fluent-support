<template>
    <div class="fc_box_email_settings">
        <el-table v-if="configs" :data="configs" stripe>
            <el-table-column label="Title" prop="title" width="400" />
            <el-table-column label="Status">
                <template #default="scope">
                    <i v-if="scope.row.status == 'yes'" style="font-size: 20px; color: #67c23a;" class="el-icon-circle-check"></i>
                    <i v-else style="font-size: 20px; color: #f56c6c;" class="el-icon-circle-close"></i>
                </template>
            </el-table-column>
            <el-table-column label="Manage">
                <template #default="scope">
                    <el-button @click="editEmail(scope.row)" size="mini" type="primary" icon="el-icon-edit" />
                </template>
            </el-table-column>

        </el-table>
        <el-skeleton v-else :rows="5" animated/>
    </div>

    <el-dialog v-if="active_email_settings"
               :append-to-body="true"
               width="60%"
               :title="active_email_settings.title"
               v-model="edit_modal"
    >
        <h3>{{active_email_settings.description}}</h3>
        <el-form :data="active_email_settings" label-position="top">
            <el-form-item label="Email Subject">
                <el-input :disabled="active_email_settings.can_edit_subject == 'no'"
                          v-model="active_email_settings.email_subject" placeholder="Email Subject"/>
                <p v-if="active_email_settings.can_edit_subject == 'no'">You can not edit subject for this email. This
                    subject patern is required for email reply parsing</p>
            </el-form-item>
            <el-form-item label="Email Body">
                <wp-editor :editor_id="active_email_settings.key" v-model="active_email_settings.email_body"/>
            </el-form-item>
            <el-row :gutter="20">
                <el-col :sm="24" :md="12">
                    <el-form-item>
                        <el-checkbox true-label="yes" false-label="no" v-model="active_email_settings.status">Enable
                            This Email Notification
                        </el-checkbox>
                    </el-form-item>
                    <el-button @click="saveSettings" :disabled="saving" v-loading="saving" type="success">Save Settings</el-button>
                </el-col>
                <el-col :sm="24" :md="12">
                    <h3>Available Smartcodes</h3>
                    <ul class="fs_listed">
                        <li v-for="(codeName, code) in currentSmartCodes" :key="code"><b>{{codeName}}:</b> {{code}}
                        </li>
                    </ul>
                </el-col>
            </el-row>
        </el-form>
    </el-dialog>

</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';

export default {
    name: 'BoxEmailSettings',
    components: {
        WpEditor
    },
    props: ['box_id'],
    data() {
        return {
            active_email: '',
            email_types: [],
            active_email_settings: false,
            configs: [],
            edit_modal:false,
            loading: false,
            saving: false
        }
    },
    computed: {
        currentSmartCodes() {
            if (
                this.active_email == 'ticket_created_email_to_customer' ||
                this.active_email == 'ticket_created_email_to_admin'
            ) {
                return {
                    '{{customer.first_name}}': 'Customer First Name',
                    '{{customer.last_name}}': 'Customer Last Name',
                    '{{customer.full_name}}': 'Customer Full Name',
                    '{{customer.email}}': 'Customer Email',
                    '{{ticket.id}}': 'Ticket ID',
                    '{{ticket.title}}': 'Ticket Title',
                    '{{ticket.content}}': 'Ticket Content',
                    '{{business.name}}': 'Business Name'
                }
            } else {
                return {
                    '{{customer.first_name}}': 'Customer First Name',
                    '{{customer.last_name}}': 'Customer Last Name',
                    '{{customer.full_name}}': 'Customer Full Name',
                    '{{customer.email}}': 'Customer Email',
                    '{{ticket.id}}': 'Ticket ID',
                    '{{ticket.title}}': 'Ticket Title',
                    '{{ticket.content}}': 'Ticket Content',
                    '{{business.name}}': 'Business Name',
                    '{{agent.first_name}}': 'Agent First Name',
                    '{{agent.last_name}}': 'Agent Last Name',
                    '{{agent.full_name}}': 'Agent Full Name',
                    '{{response.title}}': 'Response Title',
                    '{{response.content}}': 'Response Content'
                }
            }
        }
    },
    methods: {
        getSetting() {
            this.loading = true;
            this.$get(`mailboxes/${this.box_id}/email_settings`, {
                email_type: this.active_email
            })
                .then(response => {
                    this.active_email_settings = response.email_settings;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
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
            this.edit_modal = true;
            this.active_email_settings = false;
            this.active_email = email.key;
            this.getSetting();
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
                    this.getConfigs();
                    this.getSetting();
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
