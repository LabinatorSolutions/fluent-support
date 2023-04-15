<template>
    <div class="fs_narrow_promo" v-if="!has_pro">
        <h3>{{ current_integration.promo_heading }}</h3>
        <p>{{translate('pro_promo')}}</p>
        <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{translate('Upgrade To Pro')}}</a>
    </div>
    <div class="fs_integration" v-else-if="has_pro">
        <div class="fs_settings_sub_menu">
            <ul>
                <li v-for="driver in drivers" :key="driver.key" @click="switchIntegration(driver.key)"
                    :class="{fs_sub_active: driver.key == integration_key}">{{ driver.title }}
                </li>
            </ul>
        </div>
        <div v-if="!loading" class="fs_box_wrapper fs_padded_20">
            <div v-if="current_integration">
                <h3>{{ current_integration.title }} {{translate('Integration Settings')}}</h3>
                <p v-html="current_integration.description"></p>
                <hr/>
                <div v-if="fields">
                    <component :is="fields.component" :settings="settings" :fields="fields"/>
                </div>
            </div>
            <div class="fs_box_body" v-else>
                <h3>{{translate('Settings could not be found')}}!</h3>
            </div>
        </div>
        <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated/>
        </div>
    </div>
</template>

<script type="text/babel">
import { onMounted,computed, reactive, toRefs } from "vue";
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';
import DropboxSettings from "@/admin/Modules/Settings/FileUploadSettings/DropboxSettings";
import LocalSettings from "@/admin/Modules/Settings/FileUploadSettings/LocalSettings";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import { useRouter } from 'vue-router';

export default {
    name: 'UploadIntegrationView',
    components: {
        FormBuilder,
        DropboxSettings,
        LocalSettings
    },

    setup() {

        const { get, post, translate, handleError, setTitle, appVars } =
            useFluentHelper();

        const { notify } = useNotify();
        const router = useRouter();

        const state  = reactive ({
            integration_key: router.currentRoute.value.query.integration_key,
            loading: false,
            settings: false,
            fields: false,
            saving: false,
            drivers: appVars.upload_drivers,
            dropbox_code: router.currentRoute.value.query.code,
            authorization: false,
        });

        const current_integration = computed(() => {
            return state.drivers.find((driver) => {
                return driver.key == state.integration_key;
            })
        });

        const check = computed(() => {
            if (state.dropbox_code) {
                setTimeout(() => {
                    handleAuthorizationResponse();
                }, 2000);
            }
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

        // const handleAction = (integrationKey) => {
        //     if ('dropbox_settings' == integrationKey) {
        //         authorize();
        //     } else {
        //         saveSettings();
        //     }
        // }

        const saveSettings = () => {
            state.saving = true;
            post('settings/upload_integration', {
                integration_key: state.integration_key,
                settings: state.settings
            })
                .then(response => {
                    state.settings = response.settings;
                    if(state.integration_key == 'dropbox_settings' && !state.settings.access_token) {
                        authorize();
                    } else {
                        notify({
                            message: response.message,
                            type: 'success',
                            position: 'bottom-right'
                        });
                    }
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        const switchIntegration = (integrationKey) => {
            router.push({name: 'upload_integration', query: {integration_key: integrationKey}});
            state.integration_key = integrationKey;
            fetchSettings();
        };



// Redirect the user to the Dropbox authorization page
        const authorize = () => {
            const redirectUri = 'https://187b-82-6-61-227.ngrok-free.app/wp-json/fluent-support/v2/public/dropbox_auth';
            const authorizeUrl = 'https://www.dropbox.com/oauth2/authorize';
            const url = `${authorizeUrl}?client_id=${state.settings.client_id}&redirect_uri=${redirectUri}&token_access_type=offline&response_type=code`;
            window.location.href = url;
        }





// After the user authorizes the app, the Dropbox server will redirect the user back to your redirect URI
// with a code that can be exchanged for an access token
        const handleAuthorizationResponse = () => {
            const redirectUri = 'https://187b-82-6-61-227.ngrok-free.app/wp-json/fluent-support/v2/public/dropbox_auth';
            // Exchange the code for an access token
            fetch('https://api.dropboxapi.com/oauth2/token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `grant_type=authorization_code&code=${state.dropbox_code}&client_id=${state.settings.client_id}&client_secret=${state.settings.client_secret}&redirect_uri=${redirectUri}`
            })
                .then(response => response.json())
                .then(data => {
                    // Store the access token somewhere secure (e.g. a server-side database) and use it to make API calls
                    state.settings.access_token = data.access_token;
                    state.settings.refresh_token = data.refresh_token;
                    state.authorization = false;
                    saveSettings();
                })
                .catch(error => {
                    console.error('Error exchanging code for access token:', error);
                });
        }












        onMounted(() => {
            if (state.integration_key) {
                fetchSettings();
            } else if (state.drivers && state.drivers.length) {
                state.integration_key = state.drivers[0].key;
                router.push({name: 'upload_integration', query: {integration_key: state.drivers[0].key}});
                fetchSettings();
            }
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings,
            switchIntegration,
            current_integration,
            authorize,
            // handleAction,
            check,
            handleAuthorizationResponse,
        };
    }
}
</script>
