<template>
    <div class="fs_narrow_promo" v-if="!has_pro">
        <h3>{{ current_integration.promo_heading }}</h3>
        <p>{{translate('pro_promo')}}</p>
        <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{translate('Upgrade To Pro')}}</a>
    </div>
    <div class="fs_integration" v-else-if="has_pro">
        <div v-if="!loading" class="fs_box_wrapper">
            <div v-if="fields">
                <form-builder :fields="fields.fields" :formData="settings"/>
                <el-button size="default" v-loading="saving" :disabled="saving" type="success" @click="saveSettings()">
                    {{fields.button_text}}
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


<script type="text/babel">
import { onMounted,computed, reactive, toRefs } from "vue";
import FormBuilder from '../../../Pieces/FormElements/_FormBuilder';
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import { useRouter } from 'vue-router';

export default {
    name: 'LocalSettings',
    components: {
        FormBuilder,
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


        onMounted(() => {
            fetchSettings();
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings,
            switchIntegration,
            current_integration,
        };
    }
}
</script>

<style scoped>

</style>
