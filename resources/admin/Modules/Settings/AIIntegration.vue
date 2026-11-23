<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t('AI Model Setup') }}</h3>
            </div>
            <div class="fs_box_actions" v-if="has_pro && !loading">
                <el-button
                    class="fs_filled_btn"
                    type="primary"
                    :loading="saving"
                    @click="saveSettings"
                >
                    {{ $t('Save') }}
                </el-button>
            </div>
        </div>

        <template v-if="has_pro">
            <el-skeleton v-if="loading" animated class="fs_ai_sections_wrapper">
                <template #template>
                    <div class="fs_ai_section">
                        <div class="fs_ai_toggle_row">
                            <el-skeleton-item variant="rect" style="width: 40px; height: 20px; border-radius: 10px;" />
                            <div class="fs_ai_toggle_text" style="flex: 1;">
                                <el-skeleton-item variant="text" style="width: 120px;" />
                                <el-skeleton-item variant="text" style="width: 60%;" />
                            </div>
                        </div>
                    </div>
                    <div class="fs_ai_section" v-for="section in 2" :key="section">
                        <div class="fs_ai_section_head">
                            <el-skeleton-item variant="text" style="width: 110px;" />
                        </div>
                        <div class="fs_ai_field_row">
                            <div class="fs_ai_field_label">
                                <el-skeleton-item variant="text" style="width: 90px;" />
                                <el-skeleton-item variant="text" style="width: 70%;" />
                            </div>
                            <div class="fs_ai_field_input">
                                <el-skeleton-item variant="rect" style="width: 100%; height: 36px; border-radius: var(--fs-radius-md, 8px);" />
                            </div>
                        </div>
                    </div>
                </template>
            </el-skeleton>

            <div class="fs_ai_sections_wrapper" v-else>
                <!-- Enable AI -->
                <div class="fs_ai_section">
                    <div class="fs_ai_toggle_row">
                        <el-switch v-model="isEnabled" />
                        <div class="fs_ai_toggle_text">
                            <span class="fs_ai_toggle_title">{{ $t('Enable AI') }}</span>
                            <span class="fs_ai_toggle_desc">{{ $t('When enabled, AI will be available for response suggestions and ticket summaries.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- AI Provider -->
                <div class="fs_ai_section" v-if="isEnabled">
                    <div class="fs_ai_section_head">{{ $t('AI Provider') }}</div>
                    <div class="fs_ai_field_row">
                        <div class="fs_ai_field_label">
                            <p class="fs_ai_field_title">{{ $t('Provider') }}</p>
                            <p class="fs_ai_field_desc">{{ $t('Select the AI service you want to use.') }}</p>
                        </div>
                        <div class="fs_ai_field_input">
                            <el-select v-model="provider" @change="onProviderChange" class="fs_ai_select fs_select_field">
                                <el-option
                                    v-for="p in providers"
                                    :key="p.value"
                                    :value="p.value"
                                    :label="p.label"
                                />
                            </el-select>
                        </div>
                    </div>
                    <div class="fs_ai_divider"></div>
                    <div class="fs_ai_field_row">
                        <div class="fs_ai_field_label">
                            <p class="fs_ai_field_title">{{ $t('Model') }}</p>
                            <p class="fs_ai_field_desc">{{ $t('Choose the AI model for text generation.') }}</p>
                        </div>
                        <div class="fs_ai_field_input">
                            <el-select v-model="selectedModel" class="fs_ai_select fs_select_field">
                                <el-option
                                    v-for="m in modelOptions"
                                    :key="m.value"
                                    :value="m.value"
                                    :label="m.label"
                                />
                            </el-select>
                        </div>
                    </div>
                </div>

                <!-- API Key -->
                <div class="fs_ai_section" v-if="isEnabled && provider">
                    <div class="fs_ai_section_head">{{ $t('API Key') }}</div>
                    <div class="fs_ai_field_row">
                        <div class="fs_ai_field_label">
                            <p class="fs_ai_field_title">{{ $t('API Key') }}</p>
                            <p class="fs_ai_field_desc">{{ $t('Enter your API key. It will be stored securely.') }}</p>
                        </div>
                        <div class="fs_ai_field_input">
                            <el-input
                                v-model="apiKey"
                                autocomplete="off"
                                data-1p-ignore
                                data-lpignore="true"
                                :placeholder="isKeySaved ? $t('Enter a new key to replace the saved one') : $t('Enter your API key')"
                                class="fs_ai_input fs_text_input"
                            />
                            <p v-if="isKeySaved" style="margin-top: 6px; font-size: 12px; color: var(--fs-gray-600, #6b7280);">
                                <!-- translators: %s is the last four characters of the saved API key -->
                                {{ $t('A key ending in %s is saved.', apiKey.slice(-4)) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="fs_box_body">
            <ProShowcase
                :feature="$t('AI Model Setup')"
                :heading="$t('Use AI for responses, ticket summaries, and sentiment analysis')"
                :description="$t('ai_integration_promo_description')"
                :specifics="promoSpecifics"
                utm-content="feature_lock_ai_integration"
                docs-url="https://docs.fluentsupport.com/openai-integration-with-fluent-support"
            >
                <template #preview>
                    <div class="fs_pro_preview">
                        <div class="fs_pro_preview_card fs_pro_preview_fields">
                            <div class="fs_pro_preview_title">
                                <span class="fs_pro_preview_checkbox"></span>
                                {{ $t('AI Intelligence') }}
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                                <span class="fs_pro_preview_row_label">{{ $t('Enable AI') }}</span>
                                <span class="fs_pro_preview_switch fs_pro_preview_switch_on"></span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                                <span class="fs_pro_preview_row_label">{{ $t('Provider') }}</span>
                                <span class="fs_pro_preview_row_value">Anthropic Claude</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                                <span class="fs_pro_preview_row_label">{{ $t('Tone of customer') }}</span>
                                <span class="fs_pro_preview_badge fs_pro_preview_badge_warning">{{ $t('Negative') }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </ProShowcase>
        </div>
    </div>
</template>

<script type="text/babel">
import ProShowcase from "@/admin/Components/ProShowcase.vue";

export default {
    name: "AIIntegration",
    components: { ProShowcase },
    data() {
        return {
            loading: false,
            saving: false,
            isEnabled: false,
            provider: "openai",
            apiKey: "",
            selectedModel: "",
            modelOptions: [],
            providers: [
                { value: "openai",    label: "OpenAI" },
                { value: "gemini",    label: "Google Gemini" },
                { value: "anthropic", label: "Anthropic Claude" },
            ],
            availableModels: {},
        };
    },
    computed: {
        promoSpecifics() {
            return [
                this.$t('Generate and enhance replies with AI'),
                this.$t('Ticket summary and customer tone from the ticket view'),
                this.$t('OpenAI, Google Gemini or Anthropic Claude')
            ];
        },
        isKeySaved() {
            return this.apiKey && this.apiKey.startsWith('****');
        },
    },
    methods: {
        onProviderChange(newVal) {
            this.modelOptions  = this.availableModels[newVal] || [];
            this.selectedModel = this.modelOptions[0]?.value || "";
            this.apiKey        = "";
        },

        fetchSettings() {
            this.loading = true;
            this.$get("settings/ai-integration")
                .then((response) => {
                    this.isEnabled      = response.enabled !== 'no';
                    this.provider       = response.provider || "openai";
                    this.apiKey         = response.api_key  || "";
                    this.availableModels = response.available_models || {};
                    this.modelOptions   = this.availableModels[this.provider] || [];
                    this.selectedModel  = response.model || (this.modelOptions[0] ? this.modelOptions[0].value : "");
                    this.loading = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    this.loading = false;
                });
        },

        saveSettings() {
            this.saving = true;
            this.$post("settings/ai-integration", {
                enabled:  this.isEnabled ? 'yes' : 'no',
                provider: this.provider,
                api_key:  this.apiKey,
                model:    this.selectedModel,
            })
                .then((response) => {
                    this.$notify({
                        message:  response.message,
                        type:     "success",
                        position: "bottom-right",
                    });
                    this.saving = false;
                    this.appVars.open_ai_integration = this.isEnabled && !!this.apiKey;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    this.saving = false;
                });
        },

    },
    mounted() {
        if (this.has_pro) {
            this.fetchSettings();
        }
        this.$setTitle("AI Model Setup");
    },
};
</script>
