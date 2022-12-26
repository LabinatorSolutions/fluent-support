<template>
    <div v-loading="loading" class="fs_activity_settings">
        <el-form label-position="top">
            <el-form-item label="Automatically delete activity logs after days">
                <el-input
                    type="number"
                    min="1"
                    max="60"
                    v-model="activity_settings.delete_days"
                />
            </el-form-item>
            <el-form-item>
                <el-checkbox
                    true-label="yes"
                    false-label="no"
                    v-model="activity_settings.disable_logs"
                >
                    {{ translate("Disable Activity Logs") }}
                </el-checkbox>
            </el-form-item>
        </el-form>

        <span class="dialog-footer">
            <el-button
                v-loading="saving"
                :disabled="saving"
                type="success"
                @click="updateSettings()"
            >
                {{ translate("Update Settings") }}
            </el-button>
        </span>
    </div>
</template>

<script type="text/babel">
import {  onMounted, reactive, toRefs } from "vue";
import { useFluentHelper, useNotify } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: 'ActivitySettings',
    emits: ['updated'],
    setup(props, context){

        const { notify } = useNotify();
        const {
            get,
            post,
            translate,
            handleError,
        } = useFluentHelper();

        const state = reactive ({
            loading: false,
            saving: false,
            activity_settings: {}
        });

        const fetchSettings = async() => {
            state.loading = true;
            await get('activity-logger/settings')
                .then(response => {
                    state.activity_settings = response.activity_settings;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const updateSettings = async() => {
            state.saving = true;
            await post('activity-logger/settings', {
                activity_settings: state.activity_settings
            })
                .then(response => {
                    notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });

                    context.emit('updated');
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        onMounted(() => {
            fetchSettings();
        });

        return {
            ...toRefs(state),
            fetchSettings,
            updateSettings,
            translate

        }
    }
}
</script>
