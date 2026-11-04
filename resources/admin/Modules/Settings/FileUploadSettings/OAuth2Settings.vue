<template>
    <div class="fs_storage_oauth_settings">
        <FormSkeleton v-if="loading" :fields="3" />
        <div v-else-if="settings">
            <el-form label-position="top" :data="settings">
                <div v-if="fieldsConfig.description" class="fs_storage_oauth_description" v-html="fieldsConfig.description"></div>
                <form-builder :fields="fieldsConfig.fields" :formData="settings"/>
            </el-form>

            <p v-if="fieldsConfig.last_error" class="fs_storage_card_warning">
                {{ fieldsConfig.last_error }}
                <a v-if="fieldsConfig.help_url" :href="fieldsConfig.help_url" target="_blank" rel="noopener" class="fs_doc_link">{{ $t('How to fix') }}</a>
            </p>

            <div v-if="!fieldsConfig.locked" class="fs_storage_oauth_footer">
                <div class="fs_storage_oauth_actions">
                    <el-button
                        v-if="settings.status != 'yes'"
                        class="fs_filled_btn"
                        @click="saveSettings"
                        :loading="saving"
                    >
                        {{ fieldsConfig.button_text }}
                    </el-button>
                    <el-button
                        v-else
                        class="fs_filled_btn"
                        @click="reconnect()"
                        :loading="saving"
                    >
                        {{ $t('Reconnect') }}
                    </el-button>
                    <el-button
                        v-if="settings.status == 'yes'"
                        @click="disconnect()"
                        :disabled="saving"
                        class="fs_outline_btn fs_danger_btn"
                        plain
                    >
                        {{ $t('Disconnect') }}
                    </el-button>
                </div>
            </div>
        </div>
        <div v-else class="fs_storage_oauth_error">
            <p>{{ $t('Sorry, settings could not be loaded') }}</p>
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../../Pieces/FormElements/_FormBuilder';
import FormSkeleton from '@/admin/Components/FormSkeleton.vue';

export default {
    name: 'OAuth2Settings',
    props: ['driver_name', 'driver'],
    components: {
        FormBuilder,
        FormSkeleton
    },
    $emits: ['driverRemoved'],
    data() {
        return {
            settings: {},
            fieldsConfig: {
                fields: {}
            },
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
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    if (response.redirect) {
                        window.location.href = response.redirect;
                        return;
                    }

                    if (response.is_discarded) {
                        this.$emit('driverRemoved');
                    }
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        reconnect() {
            this.settings.do_reconnect = 'yes';
            this.saveSettings();
        },
        disconnect() {
            const count = this.fieldsConfig.file_count || 0;
            const message = count
                // translators: %s: number of files
                ? this.$_n('%s file is stored here. It stays on the remote storage; you can still open it from its ticket while the storage account exists.', '%s files are stored here. They stay on the remote storage; you can still open them from their tickets while the storage account exists.', count, count)
                : this.$t('Disconnect this storage?');

            this.$confirm({
                message: message,
                title: this.$t('Disconnect'),
                options: {
                    confirmButtonText: this.$t('Disconnect'),
                    cancelButtonText: this.$t('Cancel'),
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'fs_filled_btn'
                }
            }).then(() => {
                this.settings = {
                    disconnect: 'yes',
                    client_id: '',
                    client_secret: '',
                    access_key_id: '',
                    bucket_name: ''
                };
                this.saveSettings();
            }).catch(() => {});
        }
    },
    mounted() {
        this.getSettings();
    }
}
</script>
