<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <h3>{{ translate("ChatGPT Integration") }}</h3>
        </div>
        <div class="fs_box_body">
            <el-form label-position="top" label-width="140px">
                <el-form-item label="Api Key">
                    <el-input
                        type="password"
                        v-model="apiKey"
                    />
                </el-form-item>

                <el-button type="primary" @click="saveSettings">Save</el-button>
            </el-form>
        </div>
    </div>
</template>

<script>
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";
import {onMounted, reactive, toRefs} from "vue";

export default {
    name: "ChatGPTIntegration",
    setup() {

        const { get, post, handleError, has_pro, setTitle, translate } =
            useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            apiKey: "",
        });

        const saveSettings = () => {
            post("settings/chat-gpt-integration", {
                api_key: state.apiKey,
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                })
                .catch((errors) => {
                    handleError(errors);
                });
        };

        const fetchSettings = () => {
            get("settings/chat-gpt-integration")
                .then((response) => {
                    state.apiKey = response.api_key;
                })
                .catch((errors) => {
                    handleError(errors);
                });
        };

        onMounted(() => {
            fetchSettings();
        });

        return {
            ...toRefs(state),
            translate,
            saveSettings
        };
    }
}
</script>

<style lang="scss" scoped>

</style>
