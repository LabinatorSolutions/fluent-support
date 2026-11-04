<template>
    <div class="fs_integration">
        <div  class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t("Notification Integrations") }}</h3>
                    <div class="fs_save_settings_container" v-if="!isLocked">
                        <el-button
                            size="default"
                            type="success"
                            class="fs_filled_btn"
                            v-loading="saving"
                            :disabled="saving"
                            @click="saveSettings()"
                        >{{ $t("Save Settings") }}</el-button>
                    </div>
                </div>
            </div>
            <div class="fs_box_body">
                <div class="fs_settings_sub_menu" v-if="drivers && drivers.length">
                    <ul>
                        <li v-for="driver in drivers" :key="driver.key"
                            @click="switchIntegration(driver.key)"
                            :class="{ fs_sub_active: driver.key == integration_key }">
                            <span>{{ driver.title }}</span>
                        </li>
                    </ul>
                </div>

                <div class="fs_notification_integration_settings_form" v-if="!loading">
                    <ProShowcase
                        v-if="isLocked"
                        :feature="promo.feature"
                        :heading="promo.heading"
                        :description="promo.description"
                        :specifics="promo.specifics"
                        :utm-content="'feature_lock_' + current_integration.key + '_integration'"
                        :docs-url="promo.docsUrl"
                    >
                        <template v-if="promo.rows.length" #preview>
                            <div class="fs_pro_preview">
                                <div class="fs_pro_preview_card fs_pro_preview_fields">
                                    <div class="fs_pro_preview_title">
                                        <span class="fs_pro_preview_checkbox"></span>
                                        {{ current_integration.title }}
                                    </div>
                                    <div
                                        v-for="(row, index) in promo.rows"
                                        :key="index"
                                        class="fs_pro_preview_row fs_pro_preview_reveal"
                                        :class="{ fs_pro_preview_row_focal: index === promo.rows.length - 1 }"
                                        :style="{ '--fs-row-index': index }"
                                    >
                                        <span class="fs_pro_preview_row_label">{{ row.label }}</span>
                                        <span :class="previewValueClass(row.type)">{{ row.value }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </ProShowcase>

                    <template v-else-if="fields">
                        <div class="fs_integration_header" v-if="current_integration">
                            <!-- translators: %s is the integration name, e.g. Slack -->
                            <h3>{{ $t('%s Integration Settings', current_integration.title) }}</h3>
                            <p v-html="current_integration.description"></p>
                        </div>
                        <form-builder :fields="fields.fields" :formData="settings"/>
                    </template>

                    <template v-else>
                        <h3>{{ $t('Settings could not be found') }}!</h3>
                    </template>
                </div>
                <div class="fs_skeleton_wrap" v-else>
                    <FormSkeleton :fields="4" :button="false" />
                </div>
            </div>

        </div>

    </div>
</template>

<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';
import ProShowcase from '@/admin/Components/ProShowcase.vue';
import FormSkeleton from '@/admin/Components/FormSkeleton.vue';
import { useRouter } from 'vue-router';

export default {
    name: 'IntegrationView',
    components: {
        FormBuilder,
        ProShowcase,
        FormSkeleton
    },

    data() {
        const router = useRouter();
        return {
            integration_key: router.currentRoute.value.query.integration_key,
            loading: false,
            settings: false,
            fields: false,
            saving: false,
            drivers: this.appVars.notification_integrations,
            router, // keep router instance in data
        };
    },

    computed: {
        current_integration() {
            return this.drivers.find(driver => driver.key === this.integration_key);
        },
        isLocked() {
            return !!(this.current_integration && this.current_integration.require_pro);
        },
        // Showcase copy per locked integration; add-on drivers fall back to their own title and promo_heading.
        promo() {
            const promos = {
                telegram_settings: {
                    feature: this.$t('Telegram notifications'),
                    heading: this.$t('Follow your tickets from Telegram'),
                    description: this.$t('telegram_promo_description'),
                    specifics: [
                        this.$t('New tickets and customer replies arrive in Telegram as they happen'),
                        this.$t('Reply from Telegram and it is posted on the ticket'),
                        this.$t('Send to a group, a channel or one person')
                    ],
                    docsUrl: 'https://docs.fluentsupport.com/managing-tickets-using-telegram',
                    rows: [
                        { label: this.$t('New ticket'), value: this.$t('Export not working'), type: 'value' },
                        { label: this.$t('From'), value: 'George Wilson', type: 'value' },
                        { label: this.$t('Reply'), value: this.$t('Sent from Telegram'), type: 'success' }
                    ]
                },
                slack_settings: {
                    feature: this.$t('Slack notifications'),
                    heading: this.$t('Keep the whole team in the loop in Slack'),
                    description: this.$t('slack_promo_description'),
                    specifics: [
                        this.$t('New tickets and customer replies post to the channel you choose'),
                        this.$t('Everyone sees assignments and closed tickets'),
                        this.$t('No need to check the inbox to know what is new')
                    ],
                    docsUrl: 'https://docs.fluentsupport.com/managing-tickets-using-slack',
                    rows: [
                        { label: this.$t('Channel'), value: '#support', type: 'chip' },
                        { label: this.$t('New ticket'), value: this.$t('Billing question'), type: 'value' },
                        { label: this.$t('Assigned'), value: 'Sarah Johnson', type: 'info' }
                    ]
                }
            };
            const driver = this.current_integration || {};
            return promos[driver.key] || {
                feature: driver.title,
                heading: driver.promo_heading || driver.title || '',
                description: this.$t('pro_promo'),
                specifics: [],
                docsUrl: '',
                rows: []
            };
        }
    },

    methods: {
        previewValueClass(type) {
            const classes = {
                value: 'fs_pro_preview_row_value',
                chip: 'fs_pro_preview_chip fs_pro_preview_chip_purple fs_pro_preview_chip_plain',
                success: 'fs_pro_preview_badge fs_pro_preview_badge_success',
                info: 'fs_pro_preview_badge fs_pro_preview_badge_info'
            };
            return classes[type] || classes.value;
        },
        async fetchSettings() {
            if (!this.current_integration || this.current_integration.require_pro) {
                return;
            }
            this.loading = true;
            await this.$get('settings/integration', {
                integration_key: this.integration_key
            })
                .then(response => {
                    this.settings = response.settings;
                    this.fields = response.fields;
                    if (response.fields) {
                        this.$setTitle(response.fields.title);
                    }
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },

        async saveSettings() {
            this.saving = true;
            await this.$post('settings/integration', {
                integration_key: this.integration_key,
                settings: this.settings
            })
                .then(response => {
                    this.settings = response.settings;
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },

        switchIntegration(integrationKey) {
            this.router.push({ name: 'integration', query: { integration_key: integrationKey } });
            this.integration_key = integrationKey;
            this.fetchSettings();
        }
    },

    mounted() {
        if (this.integration_key) {
            this.fetchSettings();
        } else if (this.drivers && this.drivers.length) {
            this.integration_key = this.drivers[0].key;
            this.router.push({ name: 'integration', query: { integration_key: this.drivers[0].key } });
            this.fetchSettings();
        }
    }
};
</script>

