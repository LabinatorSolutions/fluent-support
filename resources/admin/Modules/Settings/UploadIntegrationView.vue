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
                            <p>{{ driver.description }}</p>
                        </div>
                    </div>
                    <div class="fs_integration_card_right">
                        <template v-if="enabled_driver == driverName">
                            <el-button :disabled="saving" v-loading="saving" :readonly="true" plain text type="success">Currently Enabled</el-button>
                        </template>
                        <template v-else-if="driver.is_configured">
                            <el-button @click="updateDriver(driverName)" plain type="primary">Enable</el-button>
                        </template>

                        <a class="el-button el-button--primary" target="_blank" rel="noopener" v-if="driver.require_pro"
                           :href="driver.upgrade_url">Upgrade to Pro</a>
                        <el-button v-else-if="driver.has_config" @click="ShowConfigDialog(driver, driverName)">
                            Configure
                        </el-button>
                    </div>
                </div>
            </div>
            <pre>{{ availableDrivers }}</pre>
            <pre>{{ enabled_driver }}</pre>
        </div>

        <el-dialog
            :title="title"
            v-model="dialogVisible"
            @close="closeDialog"
        >
            <template v-if="selectedDriverName === 'dropbox'">
                <DropboxSettingsWindow
                    :driver="selectedDriver"
                    :visible = "dialogVisible"
                    @showDialog="ShowConfigDialog"/>
            </template>
            <template v-else-if="selectedDriverName === 'google_drive'">
                <GoogleDriveSettingsWindow :driver="selectedDriver" :visible = "dialogVisible"/>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import DropboxSettingsWindow from './FileUploadSettings/DropboxSettingsWindow';
import GoogleDriveSettingsWindow from './FileUploadSettings/GoogleDriveSettingsWindow';
export default {
    name: 'UploadIntegrationView',
    components: {
        DropboxSettingsWindow,
        GoogleDriveSettingsWindow
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
            selectedDriver: {},
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

                    if( this.hasCode ){
                        this.ShowConfigDialog(this.availableDrivers.dropbox);
                    }
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
        ShowConfigDialog(driver, driverName) {
            this.selectedDriverName = driverName;
            this.selectedDriver = driver;
            this.title = 'Configure ' +this.selectedDriver.title + ' Settings';
            this.dialogVisible = true;
        },
        closeDialog() {
            this.fetchUploadSettings();
            this.dialogVisible = false;
        },
    },
    mounted() {
        this.fetchUploadSettings();
        if(this.$route.query.code){
            this.hasCode = true;
        }
    },
}
</script>
