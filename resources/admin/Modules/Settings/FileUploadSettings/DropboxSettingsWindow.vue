<template>
    <div class="fs_integration">
        <div v-if="!loading" class="fs_box_wrapper">
            <div v-if="fields">
                <div class="drive_setup_help_doc">
                    <span>{{translate('dropbox_help_text')}} <a href="https://fluentsupport.com/docs/dropbox-integration/" target="_blank">
                        <i class="fas fa-external-link-alt"></i> {{ translate('Click here') }}
                    </a></span>
                </div>
                <div class="fs_dropbox_redirect_url">
                    <strong>oAuth2 Redirect URL</strong>: <code>{{dropbox_redirect_uri}}</code>
                </div>

                <form-builder :fields="fields.fields" :formData="settings"/>

                <el-button v-if="!settings.refresh_token && !access_code" size="default" v-loading="saving" :disabled="saving" type="success" @click="authorize()">
                    {{ translate('Get Authorization Code') }}
                </el-button>
                <el-button v-if="!settings.refresh_token && access_code" size="default" v-loading="saving" :disabled="saving" type="success" @click="handleAuthorizationResponse()">
                    {{ translate('Authorize App') }}
                </el-button>
                <el-button v-if="settings.refresh_token && settings.access_token" size="default" v-loading="saving" :disabled="saving" type="success" @click="saveSettings">
                    {{ translate('Update') }}
                </el-button>
                <el-button v-if="hasSettingsMeta" size="default" v-loading="saving" :disabled="saving" type="danger" @click="deleteSettings">
                    {{ translate('Delete Settings') }}
                </el-button>
            </div>
            <div  v-else>
                <h3>{{translate('Settings could not be found')}}!</h3>
            </div>
        </div>
        <div  v-else>
            <h3>{{translate('Settings could not be found')}}!</h3>
        </div>
    </div>
</template>
<script type="text/babel">
import FormBuilder from "@/admin/Pieces/FormElements/_FormBuilder.vue";
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";
import {useRouter} from "vue-router";
import {onMounted, reactive, toRefs, watch} from "vue";

export default {
    name: 'DropboxSettingsWindow',
    props: ['driver', 'visible'],
    emits: ['showDialog'],
    components: {
        FormBuilder,
    },
    setup(props, content ) {
        const {get, post, del, translate, handleError, setTitle, appVars} =
            useFluentHelper();

        const {notify} = useNotify();
        const router = useRouter();

        const state  = reactive ({
            integration_key: props.driver.meta_key,
            loading: false,
            settings: false,
            fields: false,
            saving: false,
            access_code: router.currentRoute.value.query.code,
            authorization: false,
            hasSettingsMeta: false,
            dropbox_redirect_uri: appVars.rest.url + '/public/dropbox_auth',
        });

        const fetchSettings = async () => {
            state.loading = true;
            await get('settings/upload_integration', {
                integration_key: state.integration_key
            })
                .then(response => {
                    state.settings = response.settings;
                    state.fields = response.fields;
                    state.component = response.fields.component;
                    if( response.settings.client_id && response.settings.client_secret) {
                        state.hasSettingsMeta = true;
                    }
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
                    router.push({name: 'upload_integration'})
                    fetchSettings();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        const authorize = () => {
            saveSettings();
            const authorizeUrl = 'https://www.dropbox.com/oauth2/authorize';
            const url = `${authorizeUrl}?client_id=${state.settings.client_id}&redirect_uri=${state.dropbox_redirect_uri}&token_access_type=offline&response_type=code`;
            window.location.href = url;
        }

        const deleteSettings = () => {
            state.saving = true;
            del('settings/upload_integration', {
                integration_key: state.integration_key,
            })
                .then(response => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    state.settings = false;
                    state.fields = false;
                    state.component = false;
                    fetchSettings();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        const handleAuthorizationResponse = () => {
            // Exchange the code for an access token
            fetch('https://api.dropboxapi.com/oauth2/token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `grant_type=authorization_code&code=${state.access_code}&client_id=${state.settings.client_id}&client_secret=${state.settings.client_secret}&redirect_uri=${state.dropbox_redirect_uri}`
            })
                .then(response => response.json())
                .then(data => {
                    // Store the access token somewhere secure (e.g. a server-side database) and use it to make API calls
                    state.settings.access_token = data.access_token;
                    state.settings.refresh_token = data.refresh_token;
                    state.authorization = false;
                    saveSettings();
                    router.push({name: 'upload_integration'})
                })
                .catch(error => {
                    console.error('Error exchanging code for access token:', error);
                });
        }

        onMounted(() => {
            fetchSettings();
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings,
            authorize,
            handleAuthorizationResponse,
            deleteSettings,
        }
    }
}
</script>
<style scoped>
.fs_dropbox_redirect_url{
    padding: 20px;
    background: #f2f2f2;
    margin: 10px 0;
}
.drive_setup_help_doc{
    padding: 10px;
    background: #f0f8ff;
    margin: 10px 0;
    font-size: 14px;
    color: #666;
}
</style>
