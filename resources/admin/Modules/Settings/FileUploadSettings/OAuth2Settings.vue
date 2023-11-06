<template>
    <el-skeleton v-if="loading" :animated="true" :rows="4"></el-skeleton>
    <div v-if="settings">
        <div v-if="fieldsConfig.description" v-html="fieldsConfig.description"></div>
        <form-builder :fields="fieldsConfig.fields" :formData="settings"/>
        <div class="fs_form_buttons">
            <el-button type="primary" @click="saveSettings" :loading="saving">{{ fieldsConfig.button_text }}</el-button>
        </div>
    </div>
    <div v-else>
        <p>Sorry, settings could not be loaded</p>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../../Pieces/FormElements/_FormBuilder';

export default {
    name: 'OAuth2Settings',
    props: ['driver_name', 'driver'],
    components: {
        FormBuilder
    },
    $emits: ['driverRemoved'],
    data() {
        return {
            settings: {},
            fieldsConfig: {},
            loading: false,
            saving: false
        }
    },
    methods: {
        getSettings() {
            this.loading = true;
            this.$get('settings/upload_integration', {
                integration_key: this.driver_name
            })
                .then(response => {
                    this.settings = response.settings;
                    this.fieldsConfig = response.fieldsConfig;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        saveSettings() {
            this.saving = true;
            this.$post('settings/upload_integration', {
                integration_key: this.driver_name,
                settings: this.settings
            })
                .then(response => {
                    this.$notify.success(response.message);
                    if (response.redirect) {
                        window.location.href = response.redirect;
                        return;
                    }

                    if (response.is_discarted) {
                        this.$emit('driverRemoved');
                    }
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        }
    },
    mounted() {
        this.getSettings();
    }
}
</script>
