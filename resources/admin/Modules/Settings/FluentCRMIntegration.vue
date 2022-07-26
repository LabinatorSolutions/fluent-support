<template>
    <div class="fs_fluentcrm_settings">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('FluentCRM Integration Settings')}}</h3>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">
                <template v-if="is_installed">
                    <form-builder :fields="settings_fields" :formData="settings"/>
                    <el-button :disabled="saving" size="default" type="success" @click="saveSettings()">
                        {{ $t('Save Settings') }}
                    </el-button>
                </template>
                <div v-else style="padding: 20px; background: white;" class="fs_narrow_promo">
                    <img style="max-width: 300px; max-height: 50px" :src="fluentcrm_logo" />
                    <h3>{{ $t('email_marketing_automation_newsletter_crm') }}</h3>
                    <p><a target="_blank" rel="noopener" href="https://fluentcrm.com">FluentCRM</a> {{$t('email_marketing_automation_newsletter_crm_infos')}}</p>
                    <p>{{ $t('integrate_support_customers_with_fluentcrm') }}</p>
                    <el-button v-loading="installing" :disabled="installing" @click="installFluentCRM()" type="success">
                        {{ $t('install_fluentcrm') }}
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

export default {
    name: 'FluentCRMIntegration',
    components: {
        FormBuilder
    },
    data() {
        return {
            crm_config: this.appVars.fluentcrm_config,
            fetching: true,
            settings: {},
            settings_fields: {},
            is_installed: false,
            saving: false,
            installing: false,
            fluentcrm_logo: ''
        }
    },
    methods: {
        fetchSettings() {
            this.fetching = true;
            this.$get('settings/fluentcrm-settings')
                .then(response => {
                    if (response.is_installed) {
                        this.settings = response.settings;
                        this.settings_fields = response.settings_fields;
                        this.fluentcrm_logo = response.fluentcrm_logo;
                        this.is_installed = true
                    } else {
                        this.is_installed = false
                        this.fluentcrm_logo = response.fluentcrm_logo;
                    }
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        saveSettings() {

            if (this.settings.enabled == 'yes' && !this.settings.assigned_tags.length) {
                this.$notify.error({
                    message: 'Please select at least one FluentCRM Tag',
                    position: 'bottom-right'
                });
                return false;
            }

            this.saving = true;
            this.$post('settings', {
                settings_key: '_fluentcrm_intergration_settings',
                settings: this.settings
            })
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    })
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        installFluentCRM() {
            this.installing = true;
            this.$post('settings/intsall-fluentcrm')
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.fetchSettings();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.installing = false;
                });
        }
    },
    mounted() {
        this.fetchSettings();
        this.$setTitle('FluentCRM Integration Settings');
    }
}
</script>
