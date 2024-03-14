<template>
    <div class="fs_fluentcrm_settings">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate('FluentCRM Integration Settings') }}</h3>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">
                <template v-if="is_installed">
                    <form-builder :fields="settings_fields" :formData="settings"/>
                    <el-button :disabled="saving" size="default" type="success" @click="saveSettings()">
                        {{ translate('Save Settings') }}
                    </el-button>
                </template>
                <div v-else style="padding: 20px; background: white;" class="fs_narrow_promo">
                    <img style="max-width: 300px; max-height: 50px" :src="fluentcrm_logo" />
                    <h3>{{ translate('email_marketing_automation_newsletter_crm') }}</h3>
                    <p><a target="_blank" rel="noopener" href="https://fluentcrm.com">FluentCRM</a> {{translate('email_marketing_automation_newsletter_crm_infos')}}</p>
                    <p>{{ translate('integrate_support_customers_with_fluentcrm') }}</p>
                    <el-button v-loading="installing" :disabled="installing" @click="installFluentCRM()" type="success">
                        {{ translate('install_fluentcrm') }}
                    </el-button>
                </div>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body fs_narrow_promo" v-else>
                <el-skeleton :rows="5" animated/>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';
import { onMounted, reactive, toRefs } from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'FluentCRMIntegration',
    components: {
        FormBuilder
    },
    setup() {
        const {
            get,
            post,
            put,
            del,
            handleError,
            setTitle,
            translate,
            appVars,
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            crm_config: appVars.fluentcrm_config,
            fetching: true,
            settings: {},
            settings_fields: {},
            is_installed: false,
            saving: false,
            installing: false,
            fluentcrm_logo: ''
        });

        const fetchSettings = async() => {
            state.fetching = true;
            await get('settings/fluentcrm-settings')
                .then(response => {
                    if (response.is_installed) {
                        state.settings = response.settings;
                        state.settings_fields = response.settings_fields;
                        let tags = state.settings.assigned_tags;
                        let lists = state.settings.assigned_list;

                        if(Array.isArray(tags)) {
                            state.settings.assigned_tags = tags.map((item) => !isNaN(item) ? parseInt(item) : item);
                        }else {
                            state.settings.assigned_tags = !isNaN(tags) ? parseInt(tags) : tags;
                        }

                        if(Array.isArray(lists)) {
                            state.settings.assigned_list = lists.map((list) => !isNaN(list) ? parseInt(list) : list);
                        }else {
                            state.settings.assigned_list = !isNaN(lists) ?  parseInt(lists) : lists;
                        }

                        state.fluentcrm_logo = response.fluentcrm_logo;
                        state.is_installed = true
                    } else {
                        state.is_installed = false
                        state.fluentcrm_logo = response.fluentcrm_logo;
                    }
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };

        const saveSettings = async() => {

            if (state.settings.enabled == 'yes' && !state.settings.assigned_tags.length) {
                notify({
                    type: 'error',
                    message: 'Please select at least one FluentCRM Tag',
                    position: 'bottom-right'
                });
                return false;
            }

            state.saving = true;
            await post('settings', {
                settings_key: '_fluentcrm_intergration_settings',
                settings: state.settings
            })
                .then(response => {
                    notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    })
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        const installFluentCRM = async() => {
            state.installing = true;
            await post('settings/intsall-fluentcrm')
                .then(response => {
                    notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    fetchSettings();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.installing = false;
                });
        };

        onMounted(() => {
            fetchSettings();
            setTitle('FluentCRM Integration Settings');
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings,
            installFluentCRM
        }

    }
}
</script>
