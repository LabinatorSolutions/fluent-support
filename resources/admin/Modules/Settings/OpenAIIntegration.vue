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
                            :key="model.value"
                            :label="model.label"
                            :value="model.value">
                        </el-option>
                    </el-select>
                </el-form-item>
                <div class="fs_open_ai_actions_btn">
                    <el-button class="fs_filled_btn" type="primary" @click="saveSettings">{{$t('Connect')}}</el-button>
                    <el-button class="fs_stroke_btn" v-if="disconnectChatGPT" type="danger" @click="disconnect">{{$t('Disconnect')}}</el-button>
                </div>
            </el-form>
        </div>
            <div class="fs_box_body fs_skeleton_loader" v-else>
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
            selectedModel: "gpt-5.2",
            modelOptions: [],
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
                    this.modelOptions = response.model_options || [];
                    if (response.model_migrated) {
                        this.$notify({
                            title: this.$t('Model Updated'),
                            message: this.$t('Your previously selected model') + ' (' + response.previous_model + ') ' + this.$t('has been deprecated. Switched to') + ' ' + response.model + '.',
                            type: 'warning',
                            position: 'bottom-right',
                            duration: 8000
                        });
                    }
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
