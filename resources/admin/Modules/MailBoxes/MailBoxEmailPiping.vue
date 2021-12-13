<template>
    <div class="fc_box_email_settings">
        <el-skeleton v-if="loading_pipe" :rows="5" animated/>
        <div v-else-if="email_pipe">
            <div class="fs_pipe_box" v-if="email_pipe.status == 'not_issued'">
                <h3>{{$t("You're Almost Done")}}</h3>
                <p>{{$t('email_synced_with_support_portal')}}</p>
                <hr/>
                <el-checkbox style="margin-bottom: 20px; margin-top: 10px;" v-model="terms_agree">
                    {{$t('I agree with the Fluent Support email parsing')}} <a target="_blank" rel="noopener"
                                                    href="https://fluentsupport.com/privacy-policy/email-piping-data-policy/">
                        {{$t('terms and conditions')}}</a></el-checkbox>
                <br />
                <el-button @click="issueEmail()" type="primary" :disabled="!terms_agree">{{$t('Get Piping Email Details')}}
                </el-button>

                <div style="margin-top: 30px" v-if="is_custom_supported">
                    <hr/>
                    <p>{{$t('If you want to connect with your own email parser please use this following URL to send payload data')}}</p>
                    <el-input type="text" :readonly="true" v-model="webhook_url"/>
                </div>
            </div>
            <div class="fs_pipe_box" v-else-if="email_pipe.status == 'issued'">
                <h3>{{$t('Your masked mailbox email has been issued')}}</h3>
                <p>{{$t('Please enable auto-forwarding your emails from')}} {{mailbox.email}} {{$t('to this following address')}}</p>
                <el-input :readonly="true" v-model="email_pipe.mapped_email" />
                <el-button style="margin-top: 20px;" @click="loadPipeStatus()">{{$t('I have done it')}}</el-button>
                <p>{{$t('Once you activate the auto-forward for')}} {{mailbox.email}}, {{$t('the status will be active')}}</p>
                <p>{{$t('for_the_confirmation_email')}}</p>
                <p><a target="_blank" rel="noopener" href="https://fluentsupport.com/docs/email-piping-email-based-support-ticket/">{{$t('Read the email piping documentation')}}</a></p>
            </div>
            <div class="fs_pipe_box" v-else-if="email_pipe.status == 'active'">
                <h3>{{$t('Your MailBox is active')}}</h3>
                <p>{{$t('Your masked email address is')}}: </p>
                <el-input :readonly="true" v-model="email_pipe.mapped_email" />
                <p>{{$t('All the forwarded emails to this address will be synced with your tickets')}}</p>
            </div>
            <div v-else class="fs_pipe_box">
                <h3>{{error_message}}</h3>
                <p>{{$t('Please contact with')}} <a href="https://wpmanageninja.com/support-tickets/#/" target="_blank">WPManageNinja Support</a></p>
            </div>
        </div>
        <div v-else>
            {{$t('Settings could not be loaded')}}
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
