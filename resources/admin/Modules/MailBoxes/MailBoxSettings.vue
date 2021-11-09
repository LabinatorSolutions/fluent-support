<template>
    <div class="fs_mailbox_settings">
        <el-form :data="mailbox" label-position="top">
            <el-form-item :label="$t('Inbox Name')">
                <el-input type="text" v-model="mailbox.name"></el-input>
            </el-form-item>
            <el-form-item :label="$t('Support From Email')">
                <el-input :disabled="mailbox.mapped_email && mailbox.box_type == 'email'" type="email" v-model="mailbox.email"></el-input>
                <p>{{$t('email_can_be_send')}}</p>
            </el-form-item>
            <el-form-item :label="$t('Admin Email Address')">
                <el-input type="email" v-model="mailbox.settings.admin_email_address"></el-input>
                <p>{{$t('admin_get_email')}}</p>
                <p v-if="mailbox.box_type == 'email' && mailbox.settings.admin_email_address == mailbox.email" style="color: red;">Your Admin Email Address and Support From Email should not be same. Please use a different email address.</p>
            </el-form-item>

            <el-form-item v-if="mailbox.box_type == 'email'" :label="$t('Mapped Email')">
                <template v-if="mailbox.mapped_email">
                    <el-input :disabled="true" type="email" v-model="mailbox.mapped_email"></el-input>
                    <p>{{$t('mapped_webhook_email')}}</p>
                </template>
                <div v-else>
                    <h4>Please configure <router-link :to="{name: 'email_piping', params: { box_id: mailbox.id }}">your email piping settings first</router-link></h4>
                </div>
            </el-form-item>

            <el-form-item :label="$t('Email Footer For Customers')">
                <wp-editor :height="100" v-model="mailbox.email_footer"/>
                <div>
                    <el-button size="small" style="cursor: pointer;" @click="show_codes = !show_codes">{{$t('see_available_dynamic_shortcodes')}}:</el-button>
                    <ul v-if="show_codes">
                        <li v-for="(code, codeName) in smartCodes">{{code}}: {{codeName}}</li>
                    </ul>
                </div>
            </el-form-item>

            <el-form-item>
                <el-button v-loading="saving" :disabled="saving" @click="saveSettings()" type="primary">
                    {{$t('Save Settings')}}
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
                '{{customer.first_name}}': this.$t('Customer First name'),
                '{{customer.last_name}}': this.$t('Customer Last name'),
                '{{customer.email}}': this.$t('Customer Email'),
                '{{ticket.id}}': this.$t('Ticket ID'),
                '{{ticket.public_url}}': this.$t('Ticket Public URL'),
                '{{ticket.title}}': this.$t('Ticket Title')
            },
            saving: false
        }
    },
    computed: {
        filtered_client_notifications() {
            if(this.mailbox.box_type == 'email') {
                return {
                    ticket_created: this.$t('Ticket Received Welcome Email'),
                    ticket_closed_by_agent: this.$t('Ticket Closed By Agent')
                }
            }
            return this.client_notifications;
        }
    },
    methods: {
        saveSettings() {

            if(this.mailbox.box_type == 'email' && this.mailbox.settings.admin_email_address == this.mailbox.email) {
                this.$notify.error('Your Admin Email Address and Support From Email should not be same. Please use a different email address.');
                return false;
            }

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
