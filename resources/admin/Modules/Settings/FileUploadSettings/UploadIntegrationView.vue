<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t('File Storage Settings') }}</h3>
            </div>
        </div>
        <div v-if="!first_loading" v-loading="loading" class="fs_box_body fs_storage_integration_wrapper">
            <div class="fs_storage_integration_cards">
                <div v-for="(driver, driverName) in availableDrivers" :key="driverName" class="fs_storage_integration_card">
                    <div class="fs_storage_card_content">
                        <div class="fs_storage_card_left">
                            <div class="fs_storage_icon_wrapper">
                                <img :src="driver.icon" :alt="driver.title" class="fs_storage_icon"/>
                            </div>
                            <div class="fs_storage_card_info">
                                <div class="fs_storage_card_header">
                                    <h3 class="fs_storage_card_title">{{ driver.title }}</h3>
                                    <span v-if="enabled_driver == driverName" class="fs_status_badge active">
                                        {{ $t('Active') }}
                                    </span>
                                </div>
                                <p class="fs_storage_card_description" v-html="driver.description"></p>
                                <p v-if="driver.setup_issue && driver.setup_issue.message" class="fs_storage_card_warning">
                                    {{ driver.setup_issue.message }}
                                    <a v-if="driver.setup_issue.help_url" :href="driver.setup_issue.help_url" target="_blank" rel="noopener" class="fs_doc_link">{{ $t('How to fix') }}</a>
                                </p>
                                <p v-if="driver.health && driver.health.failed_uploads" class="fs_storage_card_warning">
                                    <!-- translators: %s: number of files -->
                                    {{ $_n('%s file could not be uploaded and was kept on this server.', '%s files could not be uploaded and were kept on this server.', driver.health.failed_uploads, driver.health.failed_uploads) }}
                                    <span v-if="driver.health.last_error">{{ driver.health.last_error }}</span>
                                    <el-button
                                        v-if="driver.is_configured"
                                        class="fs_outline_btn fs_storage_retry_btn"
                                        size="small"
                                        :disabled="retrying === driverName"
                                        :loading="retrying === driverName"
                                        @click="retryUploads(driverName)"
                                    >
                                        {{ $t('Retry now') }}
                                    </el-button>
                                </p>
                                <p v-if="driver.health && driver.health.failed_deletes" class="fs_storage_card_warning">
                                    <!-- translators: %s: number of files -->
                                    {{ $_n('%s file could not be removed from the remote storage after its ticket was deleted.', '%s files could not be removed from the remote storage after their tickets were deleted.', driver.health.failed_deletes, driver.health.failed_deletes) }}
                                    <span v-if="driver.health.last_delete_error">{{ driver.health.last_delete_error }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="fs_storage_card_right">
                            <div class="fs_storage_card_actions">
                                <template v-if="driverName === 'local' || driver.is_configured">
                                    <div class="fs_tk_filter fs_waiting_toggle">
                                        <el-switch
                                            @change="updateDriver(driverName)"
                                            :model-value="enabled_driver == driverName"
                                            :disabled="saving || (driverName === 'local' && enabled_driver === 'local')"
                                            v-loading="saving"
                                        ></el-switch>
                                    </div>
                                    <template v-if="driver.has_config && driverName !== 'local'">
                                        <el-button
                                            @click="showConfigDialog(driver, driverName)"
                                            class="fs_outline_btn"
                                        >
                                            <DynamicIcon name="Gear"/>
                                        </el-button>
                                    </template>
                                </template>
                                <template v-else-if="driver.require_pro">
                                    <a
                                        class="fs_storage_manage_btn fs_storage_upgrade_btn"
                                        target="_blank"
                                        rel="noopener"
                                        :href="driver.upgrade_url"
                                    >
                                        {{ $t('Upgrade to Pro') }}
                                    </a>
                                </template>
                                <template v-else-if="driver.has_config">
                                    <el-button
                                        @click="showConfigDialog(driver, driverName)"
                                        class="fs_outline_btn"
                                    >
                                        <DynamicIcon name="Gear"/>
                                    </el-button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="can_async" class="fs_storage_options">
                <div class="fs_storage_options_text">
                    <h4>{{ $t('Upload in the background') }}</h4>
                    <p>{{ $t('Replies are saved right away and files are copied to your remote storage a few seconds later.') }}</p>
                </div>
                <el-switch
                    :model-value="async_upload === 'yes'"
                    :disabled="savingOptions"
                    @change="saveAsyncOption"
                ></el-switch>
            </div>
        </div>
        <div v-else class="fs_box_body fs_storage_integration_wrapper fs_skeleton_loader">
            <el-skeleton animated>
                <template #template>
                    <div class="fs_storage_integration_cards">
                        <div v-for="card in 3" :key="card" class="fs_storage_integration_card">
                            <div class="fs_storage_card_content">
                                <div class="fs_storage_card_left">
                                    <el-skeleton-item variant="rect" style="width: 40px; height: 40px; border-radius: var(--fs-radius-md, 8px); flex-shrink: 0;" />
                                    <div class="fs_storage_card_info" style="flex: 1;">
                                        <el-skeleton-item variant="text" style="width: 140px; display: block; margin-bottom: 8px;" />
                                        <el-skeleton-item variant="text" style="width: 80%; display: block;" />
                                    </div>
                                </div>
                                <div class="fs_storage_card_right">
                                    <el-skeleton-item variant="rect" style="width: 40px; height: 20px; border-radius: 10px;" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </el-skeleton>
        </div>

        <el-dialog
            v-if="selectedDriver"
            :title="title"
            v-model="dialogVisible"
            @close="closeDialog"
            width="60%"
            class="fs_dialog"
            :append-to-body="true"
        >
            <o-auth2-settings
                v-if="selectedDriver && dialogVisible"
                @driverRemoved="handleDriverRemoved(selectedDriverName)"
                :driver_name="selectedDriverName"
                :driver="selectedDriver"
            />
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import OAuth2Settings from './OAuth2Settings.vue';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

export default {
    name: 'UploadIntegrationView',
    components: {
        DynamicIcon,
        OAuth2Settings
    },
    data() {
        return {
            availableDrivers: {},
            enabled_driver: 'local',
            title: '',
            loading: false,
            first_loading: true,
            saving: false,
            dialogVisible: false,
            hasCode: false,
            selectedDriver: null,
            selectedDriverName: '',
            async_upload: 'no',
            can_async: false,
            savingOptions: false,
            retrying: ''
        }
    },
    methods: {
        fetchUploadSettings() {
            this.loading = true;
            this.$get('settings/remote-upload-settings')
                .then(response => {
                    this.availableDrivers = response.drivers;
                    this.enabled_driver = response.enabled_driver;
                    this.async_upload = response.async_upload || 'no';
                    this.can_async = !!response.can_async;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                    this.first_loading = false;
                });
        },
        updateDriver(driverName) {
            // Don't allow disabling local if it's the only active driver
            if (driverName === 'local' && this.enabled_driver === 'local') {
                return;
            }

            // If trying to disable the current driver, switch to local
            if (this.enabled_driver === driverName && driverName !== 'local') {
                driverName = 'local';
            }

            this.saving = true;
            this.$post('settings/update-remote-upload-driver', {
                driver: driverName
            })
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.enabled_driver = driverName;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        showConfigDialog(driver, driverName) {
            this.selectedDriverName = driverName;
            this.selectedDriver = driver;
            // translators: %s: storage name, e.g. Amazon S3
            this.title = this.$t('Configure %s Settings', this.selectedDriver.title);
            this.dialogVisible = true;
        },
        saveAsyncOption(value) {
            this.savingOptions = true;
            this.$post('settings/remote-upload-options', {
                async_upload: value ? 'yes' : 'no'
            })
                .then(response => {
                    this.async_upload = response.async_upload;
                    this.$notify({ type: 'success', message: response.message, position: 'bottom-right' });
                })
                .catch(errors => this.$handleError(errors))
                .always(() => {
                    this.savingOptions = false;
                });
        },
        retryUploads(driverName) {
            this.retrying = driverName;
            this.$post('settings/remote-upload-retry', { driver: driverName })
                .then(response => {
                    this.$notify({ type: 'success', message: response.message, position: 'bottom-right' });
                    this.fetchUploadSettings();
                })
                .catch(errors => this.$handleError(errors))
                .always(() => {
                    this.retrying = '';
                });
        },
        closeDialog() {
            this.fetchUploadSettings();
            this.dialogVisible = false;
            this.selectedDriver = null;
            this.selectedDriverName = '';
        },
        handleDriverRemoved(driverName) {
            if(driverName == this.enabled_driver) {
                this.enabled_driver = 'local';
                this.updateDriver('local');
            }
            this.closeDialog();
        }
    },
    mounted() {
        this.fetchUploadSettings();
        if (this.$route.query.code) {
            this.hasCode = true;
        }
        this.$setTitle('File Storage Settings');
    }
}
</script>
