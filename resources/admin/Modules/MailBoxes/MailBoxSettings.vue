<template>
    <div class="fs_mailbox_settings">
        <el-form :data="mailbox" label-position="top">
            <el-form-item label="Business Name">
                <el-input type="text" v-model="mailbox.name"></el-input>
            </el-form-item>
            <el-form-item label="Business Email">
                <el-input type="email" v-model="mailbox.email"></el-input>
                <p>Please make sure your website can send emails from this email address</p>
            </el-form-item>
            <el-form-item label="Admin Email Address">
                <el-input type="email" v-model="mailbox.settings.admin_email_address"></el-input>
                <p>Please provide the email address where admin will get email if enabled in email settings</p>
            </el-form-item>
            <el-form-item label="Support Channel">
                <el-radio-group v-model="mailbox.box_type">
                    <el-radio label="web">Web Based</el-radio>
                    <el-radio :disabled="!appVars.has_email_parser" label="email">Email Based (MailBox)</el-radio>
                </el-radio-group>
            </el-form-item>

            <el-form-item v-if="mailbox.box_type == 'email'" label="Mapped Email">
                <el-input type="email" v-model="mailbox.mapped_email"></el-input>
                <p>Please provide mapped webhook email from where you will send emails as webhook</p>
            </el-form-item>

            <el-form-item label="Email Footer For Customers">
                <wp-editor :height="100" v-model="mailbox.email_footer"/>
                <div>
                    <el-button size="small" style="cursor: pointer;" @click="show_codes = !show_codes">Click to see Available Dynamic Codes:</el-button>
                    <ul v-if="show_codes">
                        <li v-for="(code, codeName) in smartCodes">{{code}}: {{codeName}}</li>
                    </ul>
                </div>
            </el-form-item>

            <el-form-item>
                <el-button v-loading="saving" :disabled="saving" @click="saveSettings()" type="primary">Save Settings
                </el-button>
            </el-form-item>
        </el-form>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';

export default {
    name: 'MailBoxSettings',
    props: ['mailbox'],
    components: {
        WpEditor
    },
    data() {
        return {
            show_codes: false,
            smartCodes: {
                '{{customer.first_name}}': 'Customer First name',
                '{{customer.last_name}}': 'Customer Last name',
                '{{customer.email}}': 'Customer Email',
                '{{ticket.id}}': 'Ticket ID',
                '{{ticket.public_url}}': 'Ticket Public URL',
                '{{ticket.title}}': 'Ticket Title'
            },
            saving: false
        }
    },
    computed: {
        filtered_client_notifications() {
            if(this.mailbox.box_type == 'email') {
                return {
                    ticket_created: 'Ticket Received Welcome Email',
                    ticket_closed_by_agent: 'Ticket Closed By Agent'
                }
            }
            return this.client_notifications;
        }
    },
    methods: {
        saveSettings() {
            this.saving = true;
            this.$put(`mailboxes/${this.mailbox.id}`, {
                business: this.mailbox
            })
                .then(response => {
                    this.$notify({
                        type: 'success',
                        position: 'bottom-right',
                        message: response.message
                    });
                })
                .catch((errors) => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.saving = false;
                });
        }
    }
}
</script>
