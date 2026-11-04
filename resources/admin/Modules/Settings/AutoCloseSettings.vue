<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t("Auto Close Settings") }}</h3>
                <div class="fs_save_settings_container" v-if="has_pro">
                    <el-button
                        size="default"
                        type="success"
                        class="fs_save_settings_btn"
                        v-loading="loading"
                        :disabled="loading || fetching"
                        @click="saveSettings()"
                    >{{ $t("Save Settings") }}</el-button>
                </div>
            </div>
            <div class="fs_box_actions"></div>
        </div>
        <div v-if="!has_pro" class="fs_box_body">
            <ProShowcase
                :feature="$t('Auto close')"
                :heading="$t('Close inactive tickets without lifting a finger')"
                :description="$t('auto_close_promo_description')"
                :specifics="promoSpecifics"
                utm-content="feature_lock_auto_close"
                docs-url="https://docs.fluentsupport.com/automatic-ticket-closing"
            >
                <template #preview>
                    <div class="fs_pro_preview">
                        <div class="fs_pro_preview_card fs_pro_preview_fields">
                            <div class="fs_pro_preview_title">
                                <span class="fs_pro_preview_checkbox"></span>
                                {{ $t('Auto close rule') }}
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                                <span class="fs_pro_preview_row_label">{{ $t('Inactive for') }}</span>
                                <!-- translators: %s: number of days -->
                                <span class="fs_pro_preview_row_value">{{ $_n('%s day', '%s days', 7) }}</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                                <span class="fs_pro_preview_row_label">{{ $t('Only tags') }}</span>
                                <span class="fs_pro_preview_chip fs_pro_preview_chip_purple fs_pro_preview_chip_plain">{{ $t('Waiting on customer') }}</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                                <span class="fs_pro_preview_row_label">{{ $t('Then') }}</span>
                                <span class="fs_pro_preview_badge fs_pro_preview_badge_info">{{ $t('Close ticket') }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </ProShowcase>
        </div>
        <template v-else>
            <div
                style="padding: 20px"
                v-if="!fetching"
                v-loading="loading"
                class="fs_box_body fs_auto_close_body"
            >
                <div>
                    <el-checkbox
                        v-model="settings.enabled"
                        true-value="yes"
                        false-value="no"
                    >
                        {{ $t("Enable Auto Closing Inactive Tickets") }}
                    </el-checkbox>
                </div>
                <form-builder
                    v-if="app_ready && settings.enabled == 'yes'"
                    :fields="fields"
                    :form-data="settings"
                    label_position="top"
                >
                </form-builder>
            </div>
            <div
                class="fs_box_body"
                v-else
            >
                <FormSkeleton :fields="3" :button="false" />
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
import FormBuilder from "../../Pieces/FormElements/_FormBuilder";
import ProShowcase from "@/admin/Components/ProShowcase.vue";
import FormSkeleton from "@/admin/Components/FormSkeleton.vue";

export default {
    name: "AutoCloseSettings",
    components: {
        FormBuilder,
        ProShowcase,
        FormSkeleton
    },
    data() {
        return {
            settings: {},
            fields: {},
            fetching: false,
            loading: false,
            app_ready: false,
            settings_key: "auto_close_settings"
        }
    },
    computed: {
        promoSpecifics() {
            return [
                this.$t('Close tickets after a number of days without activity'),
                this.$t('Limit the rule to tickets with certain tags'),
                this.$t('Send your own closing message when a ticket closes')
            ];
        }
    },
    methods: {
        fetchSettings() {
            this.fetching = true;
            this.$get("settings/auto-close", {
                settings_key: this.settings_key,
                with: ["fields"]
            })
                .then(response => {
                    this.settings = response.settings;
                    this.fields = response.fields;
                    this.app_ready = true;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        saveSettings() {
            this.loading = true;
            this.$post("settings/auto-close", {
                settings_key: this.settings_key,
                settings: this.settings
            })
                .then(response => {
                    this.$notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right"
                    });
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetchSettings();
        }
        this.$setTitle(this.$t("Auto Close Settings"));
    }
}
</script>

