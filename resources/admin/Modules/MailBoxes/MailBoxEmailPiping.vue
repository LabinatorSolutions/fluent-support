<template>
    <div class="fc_box_email_settings">
        <el-skeleton v-if="loading_pipe" :rows="5" animated/>
        <div v-else-if="email_pipe">
            <div class="fs_pipe_box" v-if="email_pipe.status === 'not_issued'">
                <h3>{{translate("You're Almost Done")}}</h3>
                <p>{{translate('email_synced_with_support_portal')}}</p>
                <hr/>
                <el-checkbox style="margin-bottom: 20px; margin-top: 10px;" v-model="terms_agree">
                    {{translate('I agree with the Fluent Support email parsing')}} <a target="_blank" rel="noopener"
                                                    href="https://fluentsupport.com/privacy-policy/email-piping-data-policy/">
                        {{translate('terms and conditions')}}</a></el-checkbox>
                <br />
                <el-button @click="issueEmail()" type="primary" :disabled="!terms_agree">{{translate('Get Piping Email Details')}}</el-button>
            </div>
            <div class="fs_pipe_box" v-else-if="email_pipe.status == 'issued'">
                <h3>{{translate('Your masked mailbox email has been issued')}}</h3>
                <p>{{translate('Please enable auto-forwarding your emails from')}} {{mailbox.email}} {{translate('to this following address')}}</p>
                <el-input :readonly="true" v-model="email_pipe.mapped_email" />
                <el-button style="margin-top: 20px;" @click="loadPipeStatus()">{{translate('I have done it')}}</el-button>
                <p>{{translate('Once you activate the auto-forward for')}} {{mailbox.email}}, {{translate('the status will be active')}}</p>
                <p>{{translate('for_the_confirmation_email')}}</p>
                <p><a target="_blank" rel="noopener" href="https://fluentsupport.com/docs/email-piping-email-based-support-ticket/">{{translate('Read the email piping documentation')}}</a></p>
            </div>
            <div class="fs_pipe_box" v-else-if="email_pipe.status == 'active'">
                <h3>{{translate('Your MailBox is active')}}</h3>
                <p>{{translate('Your masked email address is')}}: </p>
                <el-input :readonly="true" v-model="email_pipe.mapped_email" />
                <p>{{translate('All the forwarded emails to this address will be synced with your tickets')}}</p>
            </div>
            <div v-else class="fs_pipe_box">
                <h3>{{error_message}}</h3>
                <p>{{translate('Please contact with')}} <a href="https://wpmanageninja.com/support-tickets/#/" target="_blank">WPManageNinja Support</a></p>
            </div>

            <div style="margin-top: 30px" v-if="is_custom_supported">
                <hr/>
                <p>{{translate('If you want to connect with your own email parser please use this following URL to send payload data')}}</p>
                <el-input type="text" :readonly="true" v-model="webhook_url"/>
            </div>

        </div>
        <div v-else>
            {{translate('Settings could not be loaded')}}
        </div>
    </div>
</template>

<script type="text/babel">
import {  onMounted, reactive, toRefs } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

import {useRouter} from "vue-router";
export default {
    name: 'MailBoxEmailPiping',
    props: ['mailbox'],
    setup(props) {
        const {
            appVars,
            get,
            post,
            translate,
            handleError,
            saveData,
            getData,
            moment,
        } = useFluentHelper();
        const router = useRouter();
        const state = reactive({
            status: false,
            email_pipe: false,
            loading_pipe: false,
            webhook_url: false,
            is_custom_supported: false,
            terms_agree: false,
            issuing: false,
            error_message: ''
        });

        const loadPipeStatus = async () => {
            state.loading_pipe = true;
            state.error_message = '';
            await get('email-box/' + props.mailbox.id + '/status')
                .then(response => {
                    state.email_pipe = response.email_pipe;
                    state.webhook_url = response.webhook_url;
                    state.is_custom_supported = response.is_custom_supported;
                    state.error_message = response.error_message;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading_pipe = false;
                });
        };

        const issueEmail = async () => {
            state.issuing = true;
            await post('email-box/' + props.mailbox.id + '/issue-email')
                .then(response => {
                    state.email_pipe = response.email_pipe;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.issuing = false;
                });
        };

        onMounted(() => {
            if(props.mailbox.box_type !== 'email'){
                router.push({name: 'box_settings', params: {box_id: props.mailbox.id}});
            }else{
                loadPipeStatus();
            }
        });

        return {
            ...toRefs(state),
            appVars,
            translate,
            handleError,
            saveData,
            getData,
            moment,
            loadPipeStatus,
            issueEmail
        }
    }
}
</script>
