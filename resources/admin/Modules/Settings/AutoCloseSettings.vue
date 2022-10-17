<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{$t('Auto Close Settings')}}</h3>
            </div>
            <div class="fs_box_actions">

            </div>
        </div>
        <div class="fs_narrow_promo" v-if="!appVars.has_pro">
            <h3>Auto Close tickets based on active days or based on tags or waiting time.</h3>
            <p>{{ $t('pro_promo') }}</p>
            <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{ $t('Upgrade To Pro') }}</a>
        </div>
        <template v-else>
            <div style="padding: 20px;" v-if="!fetching" v-loading="loading" class="fs_box_body">
                <div style="margin-bottom: 20px;">
                    <el-checkbox v-model="settings.enabled" true-label="yes" false-label="no">Enable Auto Closing Inactive Tickets</el-checkbox>
                </div>
                <form-builder v-if="app_ready && settings.enabled == 'yes'" :fields="fields" :form-data="settings" label_position="top">
                </form-builder>

                <div style="margin-top: 20px;">
                    <el-button size="default" type="success" @click="saveSettings()">{{$t('Save Settings')}}</el-button>
                </div>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                <el-skeleton :rows="5" animated/>
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';
export default {
    name: 'AutoCloseSettings',
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
            settings_key: 'auto_close_settings'
        }
    },
    methods: {
        fetchSettings() {
            this.fetching = true;
            this.$get('settings/auto-close', {
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
            const that = this;
            this.$post('settings/auto-close', {
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
        if(this.appVars.has_pro) {
            this.fetchSettings();
        }
        this.$setTitle('Auto Close Settings');
    }
}
</script>
