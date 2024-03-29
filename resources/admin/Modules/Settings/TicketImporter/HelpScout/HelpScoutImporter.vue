<template>
    <Teleport to="body">
        <modal :show="show" title="Import Tickets From HelpScout in Fluent Support" @close="$emit('close')">
            <template #body>
                <div class="fs_helpscout_intro">
                    <p> {{$t('Please copy and paste this url:')}} <code><strong>{{appVars.rest.url + '/public/authorize'}}</strong></code> {{$t('in your HelpScout Redirection URL. This is required as you have to authorize your app before start importing.')}} </p>
                </div>
                <el-form :data="settings" label-position="top">
                    <el-form-item :label="$t('App ID')">
                        <el-input v-model="settings.app_id" :placeholder="$t('App ID')" />
                    </el-form-item>
                    <el-form-item :label="$t('App Secret')">
                        <el-row :gutter="20" >
                            <el-col :span="20">
                                <el-input v-model="settings.app_secret" :placeholder="$t('App Secret')" />
                                <span>
                                    {{$t('Click on the')}} <code>{{$t('Get Authorized')}}</code> {{$t('button to get the authorization code.')}}
                                </span>
                            </el-col>
                            <el-col :span="4">
                                <el-button type="primary" @click="authorize" :disabled="!settings.app_secret || !settings.app_id">
                                    {{$t('Get Authorized')}}
                                </el-button>
                            </el-col>
                        </el-row>
                    </el-form-item>

                    <el-form-item :label="$t('Authorization Code')">
                        <el-row :gutter="20" >
                            <el-col :span="20">
                                <el-input v-model="settings.code" :placeholder="$t('Authorization Code')" />
                                <span>{{$t('Paste the authorization code here once you get it.')}}</span>
                            </el-col>
                            <el-col :span="4">
                                <el-button type="primary" @click="getAccessToken" :disabled="!settings.code">
                                    {{$t('Request Token')}}
                                </el-button>
                            </el-col>
                        </el-row>
                    </el-form-item>

                    <el-form-item :label="$t('Access Token')" v-if="settings.access_token">
                        <el-input v-model="settings.access_token" :placeholder="$t('Access Token')" disabled/>
                    </el-form-item>

                    <el-form-item :label="$t('Choose Mailbox')" v-if="mailboxes.length">
                        <el-select v-model="settings.mailbox_id" :placeholder="$t('Please select a mailbox')">
                            <el-option
                                v-for="item in mailboxes"
                                :key="item.id"
                                :label="item.name"
                                :value="item.id">
                            </el-option>
                        </el-select>
                    </el-form-item>
                </el-form>
            </template>
            <template #footer>
            <span class="dialog-footer">
                <el-checkbox v-if="Object.keys(previously_imported).length > 0" v-model="start_from_previous_migration" :label="`An incomplete migration exists. Would you like to resume from the previous one? (${previously_imported.completed}% completed)`" size="large" /><br>
                <el-button type="primary" @click="$emit('close')">{{ $t('Cancel') }}</el-button>
                <el-button v-if="!start_from_previous_migration" type="success" @click="$emit('import')" :disabled="!settings.mailbox_id">
                    {{$t('Import Tickets')}}
                </el-button>
                <el-button v-if="start_from_previous_migration" type="success" @click="$emit('restart_previous_migration')" :disabled="!settings.mailbox_id">
                    {{$t('Resume Previous Migration')}}
                </el-button>
          </span>
            </template>
        </modal>
    </Teleport>
</template>

<script>
import Modal from '../../../../Pieces/Modal';

export default {
    name: 'HelpScoutImporter',
    props:['show', 'settings', 'previously_imported'],
    emits:['import', 'close', 'restart_previous_migration'],
    components: {
        Modal
    },
    data() {
        return {
            mailboxes: {},
            start_from_previous_migration: false,
        }
    },
    methods: {
        authorize() {
            let url = 'https://secure.helpscout.net/authentication/authorizeClientApplication?client_id='+ this.settings.app_id +'&state=' + this.settings.app_secret;
            window.open(url, '_blank');
        },

        getAccessToken() {
            let url = 'https://api.helpscout.net/v2/oauth2/token?grant_type=authorization_code&code=' + this.settings.code +'&client_id='+ this.settings.app_id +'&client_secret=' + this.settings.app_secret;
            const request = {
                url: url,
                method: 'POST',
                body: JSON.stringify({
                    grant_type: 'authorization_code',
                    code: this.settings.code,
                    client_id: this.settings.app_id,
                    client_secret: this.settings.app_secret,
                }),
            };

            jQuery.ajax(request).done(response=> {
                this.settings.access_token = response.access_token;
                this.settings.refresh_token = response.refresh_token;
                this.settings.token_type = response.token_type;
                this.settings.expires_in = response.expires_in;
                if (this.settings.access_token) {
                    this.getMailboxes();
                }
            }).fail(error => {
                this.$handleError(error);
            })
        },
        getMailboxes() {
            const that = this;
            const settings = {
                url: 'https://api.helpscout.net/v2/mailboxes',
                method: "GET",
                headers: {
                    Authorization: "Bearer " + this.settings.access_token
                },
            };

            jQuery.ajax(settings).done(response => {
                that.mailboxes = response._embedded.mailboxes;
            }).fail(error => {
                this.$handleError(error);
            });
        },
        handleCancel() {
            this.show = false;
        },
    }
}
</script>

<style lang="scss" scoped>
.fs_helpscout_intro{
    p{
        background: #f6f4f4;
        padding: 10px;
        line-height: 22px;
    }
}
</style>
