<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>Ticket Form Settings</h3>
            </div>
            <div class="fs_box_actions">

            </div>
        </div>
        <template v-if="has_pro">
            <div style="padding: 20px;" v-if="!fetching" class="fs_box_body">
                <form-builder :fields="settings_fields" :form-data="settings"/>
                <div style="display: block">
                    <el-button @click="saveSettings()" :disabled="saving" v-loading="saving" type="success">Save
                        Settings
                    </el-button>
                </div>
            </div>

            <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                <el-skeleton :rows="5" animated/>
            </div>
        </template>
        <div style="background: white;" class="fs_narrow_promo" v-else>
            <h3>Ticket Form Customization including Knowledge base integration is available on Pro Version</h3>
            <p>{{ $t('pro_promo') }}</p>
            <a target="_blank" rel="noopener" href="https://fluentsupport.com"
               class="el-button el-button--primary">{{ $t('Upgrade To Pro') }}</a>
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';

export default {
    name: 'TicketFormConfig',
    components: {
        FormBuilder
    },
    data() {
        return {
            settings: {},
            fetching: false,
            settings_fields: {},
            saving: false,
            settings_key: 'ticket_form_settings'
        }
    },
    methods: {
        fetchSettings() {
            this.fetching = true;
            this.$get('pro/form-settings', {
                with: ['fields']
            })
                .then(response => {
                    this.settings = response.settings;
                    this.settings_fields = response.settings_fields;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        saveSettings() {
            this.saving = true;
            this.$post('pro/form-settings', {
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
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetchSettings();
        }
    }
}
</script>
