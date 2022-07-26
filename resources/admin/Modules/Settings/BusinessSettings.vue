<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{$t('Global Settings')}}</h3>
            </div>
            <div class="fs_box_actions">

            </div>
        </div>
        <div style="padding: 20px;" v-if="!fetching" v-loading="loading" class="fs_box_body">
            <form-builder v-if="app_ready" :fields="fields" :form-data="settings" label_position="top">
                <template #form_end>
                    <el-button size="default" type="success" @click="saveSettings()">{{$t('Save Settings')}}</el-button>
                </template>
            </form-builder>
        </div>
        <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated/>
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';

export default {
    name: 'BusinessSettings',
    components: {
        FormBuilder
    },
    data() {
        return {
            settings: {},
            fields: {},
            fetching: false,
            loading: false,
            app_ready: false,
            settings_key: 'global_business_settings'
        }
    },
    methods: {
        fetchSettings() {
            this.fetching = true;
            this.$get('settings', {
                settings_key: this.settings_key,
                with: ['fields']
            })
                .then(response => {
                    this.settings = response.settings;
                    this.fields = response.fields;
                    this.app_ready = true;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        saveSettings() {
            this.loading = true;
            this.$post('settings', {
                settings_key: this.settings_key,
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
                    this.loading = false;
                });
        }
    },
    mounted() {
        this.fetchSettings();
        this.$setTitle('Business Settings');
    }
}
</script>
