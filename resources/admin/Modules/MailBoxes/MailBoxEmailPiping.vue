<template>
    <div class="fc_box_email_settings">
        <el-skeleton v-if="loading_pipe" :rows="5" animated/>
        <div v-else-if="email_pipe">
            <div class="fs_pipe_box" v-if="email_pipe.status == 'not_issued'">
                <h3>You're Almost Done</h3>
                <p>Connect your email address with Fluent Support Email parser, so when your customers send an email or
                    reply from email it will be synced with your support portal</p>
                <hr/>
                <el-checkbox style="margin-bottom: 20px; margin-top: 10px;" v-model="terms_agree">I agree with the
                    Fluent Support email parsing <a target="_blank" rel="noopener"
                                                    href="https://fluentsupport.com/about/email-parsing">terms and
                        conditions</a></el-checkbox>
                <br />
                <el-button @click="issueEmail()" type="primary" :disabled="!terms_agree">Get Piping Email Details
                </el-button>

                <div style="margin-top: 30px" v-if="is_custom_supported">
                    <hr/>
                    <p>If you want to connect with your own email parser please use this following URL to send payload
                        data</p>
                    <el-input type="text" :readonly="true" v-model="webhook_url"/>
                </div>
            </div>
            <div class="fs_pipe_box" v-else-if="email_pipe.status == 'issued'">
                <h3>Your masked mailbox email has been issued</h3>
                <p>Please enable auto-forwarding your emails from {{mailbox.email}} to this following address</p>
                <el-input :readonly="true" v-model="email_pipe.mapped_email" />
                <el-button style="margin-top: 20px;" @click="loadPipeStatus()">I have done it</el-button>
                <p>Once you activate the auto-forward for {{mailbox.email}}, the status will be active</p>
            </div>
            <div class="fs_pipe_box" v-else-if="email_pipe.status == 'active'">
                <h3>Your MailBox is active</h3>
                <p>Your masked email address is: </p>
                <el-input :readonly="true" v-model="email_pipe.mapped_email" />
                <p>All the forwarded emails to this address will be synced with your tickets</p>
            </div>
            <div v-else class="fs_pipe_box">
                <h3>{{error_message}}</h3>
                <p>Please contact with <a href="https://wpmanageninja.com/support-tickets/#/" target="_blank">WPManageNinja Support</a></p>
            </div>
        </div>
        <div v-else>
            Settings could not be loaded
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'MailBoxEmailPiping',
    props: ['mailbox'],
    data() {
        return {
            status: false,
            email_pipe: false,
            loading_pipe: false,
            webhook_url: false,
            is_custom_supported: false,
            terms_agree: false,
            issuing: false,
            error_message: ''
        }
    },
    methods: {
        loadPipeStatus() {
            this.loading_pipe = true;
            this.error_message = '';
            this.$get('email-box/' + this.mailbox.id + '/status')
                .then(response => {
                    this.email_pipe = response.email_pipe;
                    this.webhook_url = response.rest_web_url;
                    this.is_custom_supported = response.is_custom_supported;
                    this.error_message = response.error_message;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading_pipe = false;
                });
        },
        issueEmail() {
            this.issuing = true;
            this.$post('email-box/' + this.mailbox.id + '/issue-email')
                .then(response => {
                    this.email_pipe = response.email_pipe;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.issuing = false;
                });
        }
    },
    mounted() {
        if (this.mailbox.box_type != 'email') {
            this.$router.push({name: 'box_settings', params: {box_id: this.mailbox.id}})
        } else {
            this.loadPipeStatus();
        }
    }
}
</script>
