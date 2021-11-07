<template>
    <div v-loading="loading" class="fs_activity_settings">
        <el-form label-position="top">
            <el-form-item label="Automatically delete activity logs after days">
                <el-input type="number" min="1" max="60" v-model="activity_settings.delete_days"/>
            </el-form-item>
        </el-form>

        <span class="dialog-footer">
            <el-button v-loading="saving" :disabled="saving" type="primary" @click="updateSettings()">
                Update Settings
            </el-button>
        </span>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'ActivitySettings',
    emits: ['updated'],
    data() {
        return {
            loading: false,
            saving: false,
            activity_settings: {}
        }
    },
    methods: {
        fetchSettings() {
            this.loading = true;
            this.$get('activity-logger/settings')
                .then(response => {
                    this.activity_settings = response.activity_settings;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        updateSettings() {
            this.saving = true;
            this.$post('activity-logger/settings', {
                activity_settings: this.activity_settings
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.$emit('updated');
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
        this.fetchSettings();
    }
}
</script>
