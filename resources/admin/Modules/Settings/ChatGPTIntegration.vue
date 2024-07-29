<template>
    <div class="fs_box_wrapper">
        <div class="fs_chatGPT_box_header">
            <div>
                <h3>{{ translate("OpenAI ChatGPT Integration") }}</h3>
                <p class="fs_chatGPT_description">{{ translate('The OpenAI ChatGPT API can be used to generate and enhance responses.') }}</p>
            </div>
        </div>
        <div class="fs_box_body"  v-if="!loading">
            <div class="fs_chatGPT_instruction">
                <a href="https://platform.openai.com/account/api-keys" class="fs_link">{{ translate('Get OpenAI ChatGPT API Keys') }}</a>
                <p>{{ translate('Please click on this link to get API keys from OpenAI ChatGPT.') }}</p>
            </div>
            <el-form label-position="top" label-width="140px">
                <el-form-item label="Access Code">
                    <el-input
                        type="password"
                        v-model="apiKey"
                    />
                </el-form-item>
                <el-button type="primary" @click="saveSettings">{{translate('Verify OpenAI ChatGPT')}}</el-button>
                <el-button v-if="disconnectChatGPT" type="danger" @click="disconnect">{{translate('Disconnect')}}</el-button>
            </el-form>
        </div>
        <el-skeleton class="fs_chatGPT_settings_loading" v-else :animated="true" :rows="3" />
    </div>
</template>


<script>
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";
import {onMounted, reactive, toRefs} from "vue";

export default {
    name: "ChatGPTIntegration",
    setup() {

        const { get, post, handleError, translate } =
            useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            apiKey: "",
            disconnectChatGPT: false,
            loading: false,
        });

        const saveSettings = () => {
            state.loading = true;
            post("settings/chatGPT-integration", {
                api_key: state.apiKey,
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    state.disconnectChatGPT = true;
                    state.loading = false;
                })
                .catch((errors) => {
                    handleError(errors);
                    state.loading = false;
                });
        };

        const fetchSettings = () => {
            state.loading = true;
            get("settings/chatGPT-integration")
                .then((response) => {
                    state.apiKey = response.api_key;
                    if (response.api_key) {
                        state.disconnectChatGPT = true;
                    }
                    state.loading = false;
                })
                .catch((errors) => {
                    handleError(errors);
                    state.loading = false;
                });
        };

        const disconnect = () => {
            post("settings/chatGPT-integration/disconnect")
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    state.disconnectChatGPT = false;
                    fetchSettings();
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
            saveSettings,
            disconnect
        };
    }
}
</script>

<style>
</style>

