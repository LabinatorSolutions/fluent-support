<template>
    <div class="fc_box_email_settings">
        <ul class="fs_inner_menu_items">
            <li
                v-for="tab in tab_items"
                :key="tab.settings_key"
                :class="(tab.settings_key == active_tab) ? 'fs_active_item' : ''"
                @click="switchTab(tab)"
            >
                {{ tab.title }}
            </li>
        </ul>
        <div v-if="active_tab_settings" class="fc_settings_items_body">
            <h3>{{active_tab_fields.description}}</h3>
            <el-form :data="active_tab_settings" label-position="top">
                <el-form-item label="Email Subject">
                    <el-input :disabled="active_tab_settings.can_edit_subject == 'no'"
                              v-model="active_tab_settings.email_subject" placeholder="Email Subject"/>
                    <p v-if="active_tab_settings.can_edit_subject == 'no'">You can not edit subject for this email. This
                        subject patern is required for email reply parsing</p>
                </el-form-item>
                <el-form-item label="Email Body">
                    <wp-editor :editor_id="active_tab" v-model="active_tab_settings.email_body"/>
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :sm="24" :md="12">
                        <el-form-item>
                            <el-checkbox true-label="yes" false-label="no" v-model="active_tab_settings.status">Enable
                                This Email Notification
                            </el-checkbox>
                        </el-form-item>
                        <el-button @click="saveSettings()" :disabled="saving" v-loading="saving" type="success">Save Settings</el-button>
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

        </div>
        <el-skeleton v-else :rows="5" animated/>

    </div>
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
            active_tab: 'ticket_created_email_to_customer',
            tab_items: [
                {
                    settings_key: 'ticket_created_email_to_customer',
                    title: 'Ticket Created (To Customer)',
                    description: 'This email will be sent when a customer submit a support ticket'
                },
                {
                    settings_key: 'ticket_replied_by_agent_email_to_customer',
                    title: 'Replied by Agent (To Customer)',
                    description: 'This email will be sent when an agent reply to a ticket'
                },
                {
                    settings_key: 'ticket_closed_by_agent_email_to_customer',
                    title: 'Ticket Closed by Agent (To Customer)',
                    description: 'This email will be sent when an agent close a ticket'
                },
                {
                    settings_key: 'ticket_created_email_to_admin',
                    title: 'Ticket Created (To Admin)',
                    description: 'This email will be sent when the business when a new ticket has been submitted'
                },
                {
                    settings_key: 'ticket_replied_by_customer_email_to_admin',
                    title: 'Replied by Customer (To Agent/Admin)',
                    description: 'This email will be sent to Assigned Agent or Admin when a customer reply to a ticket',
                }
            ],
            active_tab_fields: false,
            active_tab_settings: false,
            loading: false,
            saving: false
        }
    },
    computed: {
        currentSmartCodes() {
            if (
                this.active_tab == 'ticket_created_email_to_customer' ||
                this.active_tab == 'ticket_created_email_to_admin'
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
                    '{{response.content}}': 'Response Content',
                    '{{response.full_content}}': 'Response Content with Attachment List'
                }
            }
        }
    },
    methods: {
        getSetting() {
            this.loading = true;
            this.$get(`mailboxes/${this.box_id}/email_settings`, {
                email_type: this.active_tab
            })
                .then(response => {
                    this.active_tab_settings = response.email_settings;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        saveSettings() {
            this.saving = true;
            this.$put(`mailboxes/${this.box_id}/email_settings`, {
                email_type: this.active_tab,
                email_settings: this.active_tab_settings
            })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                })
                .catch((errors) => {
                    this.handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        switchTab(tab) {
            this.active_tab_settings = false;
            this.active_tab = tab.settings_key;
            this.active_tab_fields = tab;
            this.getSetting();
        }
    },
    mounted() {
        this.active_tab_fields = this.tab_items[0];
        this.getSetting();
    }
}
</script>
