<template>
    <div class="fs_narrow_promo" v-if="!has_pro">
        <h3>{{ current_integration.promo_heading }}</h3>
        <p>{{translate('pro_promo')}}</p>
        <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{translate('Upgrade To Pro')}}</a>
    </div>
    <div class="fs_integration" v-else-if="has_pro">
        <div v-if="!loading" class="fs_box_wrapper">
            <div v-if="fields">
                <div class="fs_g_drive_redirect_url">
                    <strong>{{ translate('oAuth2 Authorised redirect URI') }}</strong>: <code>{{redirect_uri}}</code>
                </div>
                <form-builder :fields="fields.fields" :formData="settings"/>
                <el-button v-if="!settings.access_token" size="default" v-loading="saving" :disabled="saving" type="success" @click="verify()">
                    {{ translate('Connect') }}
                </el-button>
                <el-button v-else-if="settings.access_token && settings.refresh_token" size="default" v-loading="saving" :disabled="saving" type="success" @click="verify()">
                    {{ translate('Reconnect') }}
                </el-button>
                <el-button v-if="settings.access_token && settings.refresh_token" v-loading="saving" :disabled="saving" type="success" @click="saveSettings()">
                    {{fields.button_text}}
                </el-button>
                <el-button v-if="hasSettingsMeta && !settings.refresh_token" v-loading="deleting" type="danger" @click="deleteSettings()">
                    {{ translate('Delete Settings') }}
                </el-button>
            </div>
            <div  v-else>
                <h3>{{translate('Settings could not be found')}}!</h3>
            </div>
        </div>
        <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated/>
        </div>
    </div>
</template>

<script>
import { onMounted,computed, reactive, toRefs } from "vue";
import FormBuilder from '../../../Pieces/FormElements/_FormBuilder';
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import { useRouter } from 'vue-router';

export default {
    name: "GoogleDriveSettings",
    components: {
        FormBuilder
    },

    setup() {

        const {get, post, del, translate, handleError, setTitle, appVars} =
            useFluentHelper();

        const {notify} = useNotify();
        const router = useRouter();

        const state = reactive({
            integration_key: router.currentRoute.value.query.integration_key,
            loading: false,
            settings: false,
            hasSettingsMeta: false,
            fields: false,
            saving: false,
            deleting: false,
            drivers: appVars.upload_drivers,
            redirect_uri: appVars.rest.url + '/public/google_auth',
        });

        const current_integration = computed(() => {
            return state.drivers.find((driver) => {
                return driver.key == state.integration_key;
            })
        });

        const fetchSettings = async () => {
            if (!current_integration) {
                return;
            }
            state.loading = true;
            await get('settings/upload_integration', {
                integration_key: state.integration_key
            })
                .then(response => {
                    if( response.settings.client_id && response.settings.client_secret) {
                        state.hasSettingsMeta = true;
                    }
                    state.settings = response.settings;
                    state.fields = response.fields;
                    state.component = response.fields.component;
                    if (response.fields) {
                        setTitle(response.fields.title);
                    }
                })
                .catch((errors) => {
                    handleError(errors)
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const saveSettings = () => {
            state.saving = true;
            post('settings/upload_integration', {
                integration_key: state.integration_key,
                settings: state.settings
            })
                .then(response => {
                    state.settings = response.settings;
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    fetchSettings();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        const deleteSettings = () => {
            state.deleting = true;
            del('settings/upload_integration', {
                integration_key: state.integration_key,
            })
                .then(response => {
                    state.hasSettingsMeta = false;
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    fetchSettings();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.deleting = false;
                });
        }

        const verify = () => {
            saveSettings();
            const client_id = state.settings.client_id;
            const access_token_url = 'https://accounts.google.com/o/oauth2/auth';

// Set the headers and body for the POST request to get the access token
            const body = new URLSearchParams({
                'client_id': client_id,
                'scope': 'https://www.googleapis.com/auth/drive.file',
                'response_type': 'code',
                'redirect_uri': state.redirect_uri,
                'access_type': 'offline'
            });
            window.open(access_token_url + '?' + body.toString(), '_blank');

        }

        const switchIntegration = (integrationKey) => {
            router.push({name: 'upload_integration', query: {integration_key: integrationKey}});
            state.integration_key = integrationKey;
            fetchSettings();
        };

        onMounted(() => {
            fetchSettings();
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings,
            deleteSettings,
            verify,
            switchIntegration,
            current_integration,
        };
    }
}
</script>

<style scoped>
.fs_g_drive_redirect_url{
    padding: 20px;
    background: #f2f2f2;
    margin: 10px 0;
}
</style>
