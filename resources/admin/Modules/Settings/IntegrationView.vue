<template>
    <div class="fs_integration">
        <div class="fs_settings_sub_menu">
            <ul>
                <li v-for="driver in drivers" :key="driver.key" @click="switchIntegration(driver.key)"
                    :class="{fs_sub_active: driver.key == integration_key}">{{ driver.title }}
                </li>
            </ul>
        </div>
        <div v-if="!loading" class="fs_box_wrapper fs_padded_20">
            <div v-if="current_integration">
                <h3>{{ current_integration.title }} {{$t('Integration Settings')}}</h3>
                <p>{{ current_integration.description }}</p>
                <hr/>
            </div>

            <div class="fs_narrow_promo" v-if="current_integration && current_integration.require_pro">
                <h3>{{ current_integration.promo_heading }}</h3>
                <p>{{$t('pro_promo')}}</p>
                <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{$t('Upgrade To Pro')}}</a>
            </div>

            <div v-else-if="fields" class="fs_box_body">
                <form-builder :fields="fields.fields" :formData="settings"/>
                <el-button @click="saveSettings()" v-loading="saving" :disabled="saving" type="success">
                    {{ fields.button_text }}
                </el-button>
            </div>
            <div class="fs_box_body" v-else>
                <h3>{{$t('Settings could not be found')}}!</h3>
            </div>
        </div>
        <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated/>
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';
import each from 'lodash/each';

export default {
    name: 'IntegrationView',
    components: {
        FormBuilder
    },
    data() {
        return {
            integration_key: this.$route.query.integration_key,
            loading: false,
            settings: false,
            fields: false,
            saving: false,
            drivers: this.appVars.notification_integrations
        }
    },
    computed: {
        current_integration() {
            return this.drivers.find((driver) => {
                return driver.key == this.integration_key;
            });
        }
    },
    methods: {
        fetchSettings() {
            if (!this.current_integration || this.current_integration.require_pro) {
                return;
            }
            this.loading = true;
            this.$get('settings/integration', {
                integration_key: this.integration_key
            })
                .then(response => {
                    this.settings = response.settings;
                    this.fields = response.fields;
                    if (response.fields) {
                        this.$setTitle(response.fields.title);
                    }
                })
                .catch((errors) => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.loading = false;
                });
        },
        saveSettings() {
            this.saving = true;
            this.$post('settings/integration', {
                integration_key: this.integration_key,
                settings: this.settings
            })
                .then(response => {
                    this.settings = response.settings;
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        switchIntegration(integrationKey) {
            this.$router.push({name: 'integration', query: {integration_key: integrationKey}});
            this.integration_key = integrationKey;
            this.fetchSettings();
        }
    },
    mounted() {
        if (this.integration_key) {
            this.fetchSettings();
        } else if (this.drivers && this.drivers.length) {
            this.integration_key = this.drivers[0].key;
            this.$router.push({name: 'integration', query: {integration_key: this.drivers[0].key}});
            this.fetchSettings();
        }
    }
}
</script>
