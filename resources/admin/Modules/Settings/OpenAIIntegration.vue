<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <div class="fs_box_head">
                    <h3>{{ $t("OpenAI Integration") }}</h3>
                </div>
            </div>
        </div>
        <div class="fs_ai_settings_container" v-if="has_pro">
            <div class="fs_box_body" v-if="!loading">
            <el-form label-position="top" label-width="140px">
                <el-form-item class="fs_form_item" :label="$t('Access Code')">
                    <el-input
                        class="fs_text_input fs_text_input_40"
                        type="password"
                        v-model="apiKey"
                    />
                </el-form-item>
                <el-form-item class="fs_form_item" :label="$t('Select Model')">
                    <el-select class="fs_select_field" clearable v-model="selectedModel" :placeholder="$t('Choose OpenAI model')">
                        <el-option
                            v-for="model in modelOptions"
                            :key="model"
                            :label="model"
                            :value="model">
                        </el-option>
                    </el-select>
                </el-form-item>
                <div class="fs_open_ai_actions_btn">
                    <el-button class="fs_filled_btn" type="primary" @click="saveSettings">{{$t('Connect')}}</el-button>
                    <el-button class="fs_stroke_btn" v-if="disconnectChatGPT" type="danger" @click="disconnect">{{$t('Disconnect')}}</el-button>
                </div>
            </el-form>
        </div>
            <div class="fs_box_body" v-else>
                <el-skeleton :animated="true" :rows="3" />
            </div>
        </div>
            <NarrowPromo
                v-else
                :heading="$t('Use OpenAI for responses, ticket summaries, and sentiment analysis')"
                :description="$t('pro_promo')"
                :button-text="$t('Upgrade To Pro')"
            />
    </div>
</template>

<script type="text/babel">
import NarrowPromo from "@/admin/Components/NarrowPromo.vue";

export default {
    name: "OpenAIIntegration",
    components: {
        NarrowPromo
    },
    data() {
        return {
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
        };
    },
    methods: {
        saveSettings() {
            this.loading = true;
            this.$post("settings/openai-integration", {
                api_key: this.apiKey,
                model: this.selectedModel, // Save the selected model
            })
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.disconnectChatGPT = true;
                    this.loading = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    this.loading = false;
                });
        },

        fetchSettings() {
            this.loading = true;
            this.$get("settings/openai-integration")
                .then((response) => {
                    this.apiKey = response.api_key;
                    this.selectedModel = response.model;
                    if (response.api_key) {
                        this.disconnectChatGPT = true;
                    }
                    this.loading = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    this.loading = false;
                });
        },

        disconnect() {
            this.loading = true;
            this.$post("settings/openai-integration/disconnect")
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.disconnectChatGPT = false;
                    this.fetchSettings();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetchSettings();
        }
        this.$setTitle('OpenAI Integration Settings');
    }
}
</script>
