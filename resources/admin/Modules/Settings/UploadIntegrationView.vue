<template>
    <div class="fs_narrow_promo" v-if="!has_pro">
        <h3>3rd party file upload integration is available on Pro Version</h3>
        <p>{{translate('pro_promo')}}</p>
        <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{translate('Upgrade To Pro')}}</a>
    </div>
    <div class="fs_integration" v-else>
        <div class="fs_settings_sub_menu">
            <ul>
                <li v-for="driver in drivers" :key="driver.key" @click="switchIntegration(driver.key)"
                    :class="{fs_sub_active: driver.key == integration_key}">{{ driver.title }}
                </li>
            </ul>
        </div>
        <div v-if="!loading" class="fs_box_wrapper fs_padded_20">
            <div v-if="current_integration">
                <h3>{{ current_integration.title }}</h3>
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
import GoogleDriveSettings from "@/admin/Modules/Settings/FileUploadSettings/GoogleDriveSettings";
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
        GoogleDriveSettings,
        LocalSettings
    },

    setup() {

        const { get, post, translate, handleError, setTitle, appVars, has_pro } =
            useFluentHelper();

        const { notify } = useNotify();
        const router = useRouter();

        const state  = reactive ({
            integration_key: router.currentRoute.value.query.integration_key,
            loading: false,
            settings: false,
            fields: false,
            saving: false,
            drivers: appVars.upload_drivers
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


        const switchIntegration = (integrationKey) => {
            router.push({name: 'upload_integration', query: {integration_key: integrationKey}});
            state.integration_key = integrationKey;
            fetchSettings();
        };

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
            switchIntegration,
            current_integration,
            has_pro
        };
    }
}
</script>
