<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t("Ticket Form Settings") }}</h3>
                <div class="fs_save_settings_container" v-if="has_pro" >
                    <el-button
                        @click="saveSettings()"
                        :disabled="saving"
                        v-loading="saving"
                        class="fs_save_settings_btn"
                        type="success"
                    >
                        {{ $t("Save Settings") }}
                    </el-button>
                </div>
            </div>
            <div class="fs_box_actions"></div>
        </div>
        <template v-if="has_pro">
            <div class="fs_box_body fs_business_settings_wrapper" v-if="!fetching">
                <form-builder :fields="settings_fields" :form-data="settings" />
            </div>

            <div
                class="fs_box_body fs_skeleton_loader"
                v-else
            >
                <FormSkeleton :fields="4" :button="false" />
            </div>
        </template>
        <div v-else class="fs_box_body">
            <ProShowcase
                :feature="$t('Ticket form settings')"
                :heading="$t('Shape the ticket form your customers fill in')"
                :description="$t('ticket_form_promo_description')"
                :specifics="promoSpecifics"
                utm-content="feature_lock_ticket_form_customization"
                docs-url="https://docs.fluentsupport.com/ticket-form-customization#ticket-form-configuration"
            >
                <template #preview>
                    <div class="fs_pro_preview">
                        <div class="fs_pro_preview_card fs_pro_preview_fields">
                            <div class="fs_pro_preview_title">
                                <span class="fs_pro_preview_checkbox"></span>
                                {{ $t('Ticket form') }}
                            </div>
                            <div class="fs_pro_preview_row">
                                <span class="fs_pro_preview_row_name">{{ $t('Subject') }}</span>
                                <span class="fs_pro_preview_switch fs_pro_preview_switch_on"></span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_row_focal">
                                <span class="fs_pro_preview_row_name">
                                    {{ $t('Product') }}
                                    <span class="fs_pro_preview_tag">{{ $t('Required') }}</span>
                                </span>
                                <span class="fs_pro_preview_switch fs_pro_preview_switch_on"></span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_row_off">
                                <span class="fs_pro_preview_row_name">{{ $t('Attachments') }}</span>
                                <span class="fs_pro_preview_switch fs_pro_preview_switch_turning_off"></span>
                            </div>
                            <div class="fs_pro_preview_note fs_pro_preview_added">
                                <span class="fs_pro_preview_note_icon"></span>
                                <span>{{ $t('Suggested article: Resetting your password') }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </ProShowcase>
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from "../../Pieces/FormElements/_FormBuilder";
import ProShowcase from "@/admin/Components/ProShowcase.vue";
import FormSkeleton from "@/admin/Components/FormSkeleton.vue";

export default {
    name: "TicketFormConfig",
    components: {
        FormBuilder,
        ProShowcase,
        FormSkeleton,
    },
    data() {
        return {
            settings: {},
            fetching: false,
            settings_fields: {},
            saving: false,
            settings_key: "ticket_form_settings",
        };
    },
    computed: {
        has_pro() {
            return this.appVars?.has_pro || false;
        },
        promoSpecifics() {
            return [
                this.$t('Show or hide fields on the ticket form'),
                this.$t('Make choosing a product required'),
                this.$t('Connect your knowledge base so articles are suggested before a ticket is sent')
            ];
        },
    },
    watch: {
        'settings.disabled_fields'() {
            if (this.settings.disabled_fields && this.settings.disabled_fields.includes("product_services")) {
                this.settings.product_required_field = 'no';
            }
        }
    },
    methods: {
        fetchSettings() {
            this.fetching = true;
            this.$get("pro/form-settings", {
                with: ["fields"],
            })
                .then((response) => {
                    this.settings = response.settings;
                    this.settings_fields = response.settings_fields;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        saveSettings() {
            this.saving = true;
            this.$post("pro/form-settings", {
                settings: this.settings,
            })
                .then((response) => {
                    this.$notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
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
        if (this.has_pro) {
            this.fetchSettings();
        }
        this.$setTitle(this.$t("Ticket Form Config"));
    }
};
</script>
