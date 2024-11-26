<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>File Storage Settings</h3>
            </div>
        </div>
        <div v-if="!loading" class="fs_box_body">
            <div class="fs_integration_cards">
                <div v-for="(driver, driverName) in availableDrivers" class="fs_integration_card">
                    <div class="fs_integration_card_left">
                        <img :src="driver.icon"/>
                        <div class="fs_integration_card_title">
                            <h3>{{ driver.title }}</h3>
                            <p v-html="driver.description"></p>
                        </div>
                    </div>
                    <div class="fs_integration_card_right">
                        <template v-if="enabled_driver == driverName">
                            <el-button :disabled="saving" v-loading="saving" :readonly="true" plain text type="success">
                                Currently Enabled
                            </el-button>
                        </template>
                        <template v-else-if="driver.is_configured">
                            <el-button @click="updateDriver(driverName)" plain type="primary">Enable</el-button>
                        </template>

                        <a class="el-button el-button--primary" target="_blank" rel="noopener" v-if="driver.require_pro"
                           :href="driver.upgrade_url">Upgrade to Pro</a>
                        <el-button v-else-if="driver.has_config" @click="showConfigDialog(driver, driverName)">
                            Configure
                        </el-button>
                    </div>
                </div>
            </div>
        </div>
        <el-skeleton v-else :animated="true" :rows="3" />

        <el-dialog
            :title="title"
            v-model="dialogVisible"
            @close="closeDialog"
            class="fs_dialog"
        >
            <div v-if="selectedDriver && dialogVisible">
                <o-auth2-settings @driverRemoved="handleDriverRemoved(selectedDriverName)" :driver_name="selectedDriverName"
                                  :driver="selectedDriver"></o-auth2-settings>
            </div>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import OAuth2Settings from './OAuth2Settings.vue';

export default {
    name: 'UploadIntegrationView',
    components: {
        OAuth2Settings
    },
    data() {
        return {
            availableDrivers: {},
            enabled_driver: 'local',
            title: '',
            loading: false,
            saving: false,
            dialogVisible: false,
            hasCode: false,
            selectedDriver: null,
            selectedDriverName: '',
        }
    },
    methods: {
        fetchUploadSettings() {
            this.loading = true;
            this.$get('settings/remote-upload-settings')
                .then(resonse => {
                    this.availableDrivers = resonse.drivers;
                    this.enabled_driver = resonse.enabled_driver;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        updateDriver(driverName) {
            this.saving = false;
            this.$post('settings/update-remote-upload-driver', {
                driver: driverName
            })
                .then(response => {
                    this.$notify.success(response.message);
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
            this.title = 'Configure ' + this.selectedDriver.title + ' Settings';
            this.dialogVisible = true;
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
    },
}
</script>
