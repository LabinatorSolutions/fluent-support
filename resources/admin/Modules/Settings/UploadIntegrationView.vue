<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>File Storgae Settings</h3>
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
                        <el-button v-else-if="driver.has_config">
                            Configure
                        </el-button>
                    </div>
                </div>
            </div>
            <pre>{{ availableDrivers }}</pre>
            <pre>{{ enabled_driver }}</pre>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'UploadIntegrationView',
    data() {
        return {
            availableDrivers: {},
            enabled_driver: 'local',
            loading: false,
            saving: false
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
        }
    },
    mounted() {
        this.fetchUploadSettings();
    }
}
</script>
