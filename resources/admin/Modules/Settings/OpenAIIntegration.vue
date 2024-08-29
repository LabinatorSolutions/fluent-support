<template>
    <div class="fs_box_wrapper">
        <div class="fs_chatGPT_box_header">
            <div>
                <h3>{{ translate("OpenAI Integration") }}</h3>
                <p class="fs_chatGPT_description">{{ translate('The OpenAI API can be used to generate and enhance responses.') }}</p>
            </div>
        </div>
        <div class="ai-settings-container" v-if="has_pro">
            <div class="fs_box_body" v-if="!loading">
            <el-form label-position="top" label-width="140px">
                <el-form-item label="Access Code">
                    <el-input
                        type="password"
                        v-model="apiKey"
                    />
                </el-form-item>
                <el-form-item label="Select Model">
                    <el-select v-model="selectedModel" placeholder="Choose OpenAI model">
                        <el-option
                            v-for="model in modelOptions"
                            :key="model"
                            :label="model"
                            :value="model">
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-button type="primary" @click="saveSettings">{{translate('Verify OpenAI')}}</el-button>
                <el-button v-if="disconnectChatGPT" type="danger" @click="disconnect">{{translate('Disconnect')}}</el-button>
            </el-form>
        </div>
            <el-skeleton class="fs_chatGPT_settings_loading" v-else :animated="true" :rows="3" />
        </div>
        <div class="fs_narrow_promo" v-else>
            <h3>
                {{
                    translate("Use OpenAI for responses, ticket summaries, and sentiment analysis")
                }}
            </h3>
            <p>{{ translate("pro_promo") }}</p>
            <a
                target="_blank"
                rel="noopener"
                href="https://fluentsupport.com"
                class="el-button el-button--success"
            >{{ translate("Upgrade To Pro") }}</a
            >
        </div>
    </div>
</template>

<script>
import { useFluentHelper, useNotify } from "@/admin/Composable/FluentFrameworkHelper";
import { onMounted, reactive, toRefs } from "vue";


export default {
    name: "OpenAIIntegration",
    setup() {
        const { get, post, handleError, translate, has_pro } = useFluentHelper();
        const { notify } = useNotify();

        const state = reactive({
            apiKey: "",
            selectedModel: "gpt-3.5-turbo", // Default model
            modelOptions: [
                "gpt-3.5-turbo",
                "gpt-3.5-turbo-0125",
                "gpt-3.5-turbo-1106",
                "gpt-3.5-turbo-0125",
                "gpt-4-0314",
                "gpt-4-0613",
                "gpt-4",
                "gpt-4-1106-preview",
                "gpt-4-0125-preview",
                "gpt-4-turbo-preview",
                "gpt-4-turbo-2024-04-09",
                "gpt-4-turbo",
                "gpt-4o-mini-2024-07-18",
                "gpt-4o-mini",
                "chatgpt-4o-latest",
                "gpt-4o-2024-08-06",
                "gpt-4o-2024-05-13",
                "gpt-4o"
            ],
            disconnectChatGPT: false,
            loading: false,
        });

        const saveSettings = () => {
            state.loading = true;
            post("settings/openai-integration", {
                api_key: state.apiKey,
                model: state.selectedModel, // Save the selected model
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
            get("settings/openai-integration")
                .then((response) => {
                    state.apiKey = response.api_key;
                    state.selectedModel = response.model
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
            post("settings/openai-integration/disconnect")
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
            if (has_pro) {
                fetchSettings();
            }
        });

        return {
            ...toRefs(state),
            translate,
            saveSettings,
            disconnect,
            has_pro
        };
    }
}
</script>
