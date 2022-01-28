<template>
    <div class="fs_integration">
        <div v-if="!loading" class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ fields.title }}</h3>
                </div>
                <div class="fs_box_actions">

                </div>
            </div>
            <div v-if="fields" class="fs_box_body fs_padded_20">
                <form-builder :fields="fields.fields" :formData="settings" />
                <el-button @click="saveOrUpdateSettings()" size="small" v-loading="saving" :disabled="saving" type="success">{{fields.button_text}}</el-button>
            </div>
            <div class="fs_box_body fs_padded_20" v-else>
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
export default {
    name: 'ZapierIntegration',
    components: {
        FormBuilder
    },
    data() {
        return {
            loading: true,
            settings: false,
            fields: false,
            saving: false
        }
    },
    methods:{
        getSettings() {
            this.loading = true;
            this.$get('settings/zapier-integration', {
                integration_key:'zapier_integration'
            }).then(response => {
                this.settings = response.settings;
                this.fields = response.fields;
                this.$setTitle(response.fields.title);
                this.loading = false;
            }).catch(error => {
                this.$handleError(error);
            }).always(() => {
                this.loading = false;
            });
        },


        saveOrUpdateSettings() {
            this.saving = true;
            this.$post('settings/zapier-integration', {
                settings: this.settings,
                integration_key:'zapier_integration'
            }).then(response => {
                this.saving = false;
                this.$notify.success({
                    'message' : response.message,
                    'position' : 'bottom-right'
                });
            }).catch(error => {
                this.$handleError(error);
            }).always(() => {
                this.saving = false;
                this.getSettings();
            });
        }
    },
    mounted() {
        this.getSettings();
    }
}
</script>

