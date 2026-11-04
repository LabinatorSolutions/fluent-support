<template>
    <div class="fs_box_wrapper fs_remote_portal">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t("Remote Portal") }}</h3>
                <div class="fs_save_settings_container" v-if="has_pro && status">
                    <el-switch
                        v-model="enabled"
                        active-value="yes"
                        inactive-value="no"
                        :disabled="saving || !status.can_manage || isConstant('FLUENT_SUPPORT_REMOTE_PORTAL') || status.constant_mode"
                        :active-text="$t('Enabled')"
                        @change="saveEnabled"
                    />
                </div>
            </div>
        </div>
        <div v-if="!has_pro" class="fs_box_body">
            <ProShowcase
                :feature="$t('Remote portal')"
                :heading="$t('Run support on one site, serve customers on another')"
                :description="$t('remote_portal_promo_description')"
                :specifics="promoSpecifics"
                utm-content="feature_lock_remote_portal"
                docs-url="https://docs.fluentsupport.com/remote-portal"
            >
                <template #preview>
                    <div class="fs_pro_preview">
                        <div class="fs_pro_preview_card fs_pro_preview_fields">
                            <div class="fs_pro_preview_title">
                                <span class="fs_pro_preview_checkbox"></span>
                                {{ $t('Remote portal') }}
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                                <span class="fs_pro_preview_row_label">{{ $t('Helpdesk') }}</span>
                                <span class="fs_pro_preview_row_value">support.example.com</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                                <span class="fs_pro_preview_row_label">{{ $t('Customer site') }}</span>
                                <span class="fs_pro_preview_row_value">example.com</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                                <span class="fs_pro_preview_row_label">{{ $t('Connection') }}</span>
                                <span class="fs_pro_preview_badge fs_pro_preview_badge_success">{{ $t('Connected') }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </ProShowcase>
        </div>
        <template v-else>
            <div class="fs_box_body" v-if="loadFailed">
                <el-alert type="error" :closable="false" show-icon :title="$t('The settings could not be loaded.')">
                    <el-button class="fs_outline_btn" @click="fetch">{{ $t('Try again') }}</el-button>
                </el-alert>
            </div>
            <div class="fs_box_body fs_skeleton_loader" v-else-if="!status">
                <el-skeleton animated>
                    <template #template>
                        <el-skeleton-item variant="text" style="width: 90%;" />
                        <el-skeleton-item variant="text" style="width: 65%; margin-top: 8px;" />
                        <el-skeleton-item variant="h3" style="width: 25%; margin-top: 24px;" />
                        <div v-for="n in 4" :key="n" style="display: flex; gap: 24px; margin-top: 14px;">
                            <el-skeleton-item variant="text" style="width: 20%;" />
                            <el-skeleton-item variant="text" style="width: 45%;" />
                        </div>
                        <div style="display: flex; gap: 12px; margin-top: 24px;">
                            <el-skeleton-item variant="button" style="width: 140px;" />
                            <el-skeleton-item variant="button" style="width: 100px;" />
                        </div>
                    </template>
                </el-skeleton>
            </div>
            <div class="fs_box_body fs_remote_portal_body" v-else v-loading="working">
                <p class="fs_remote_portal_intro">{{ $t('Remote_Portal_Intro') }}</p>

                <el-alert v-if="status.legacy_plugin" type="warning" :closable="false" show-icon
                          :title="$t('Remote_Portal_Legacy_Plugin')" />
                <el-alert v-if="isConstant('FLUENT_SUPPORT_REMOTE_PORTAL')" type="info" :closable="false" show-icon
                          :title="$t('Remote_Portal_Switch_Constant')" />

                <el-alert v-if="status.constant_mode && !status.connected" type="warning" :closable="false" show-icon
                          :title="$t('Remote_Portal_Constant_Incomplete')" />
                <el-alert v-if="status.constant_mode && status.connected" type="info" :closable="false" show-icon
                          :title="$t('Remote_Portal_Constant_Connected')" />
                <el-alert v-if="status.connected && enabled !== 'yes'" type="warning" :closable="false" show-icon
                          :title="$t('Remote_Portal_Connected_Off')" />
                <el-alert v-if="!status.can_manage" type="info" :closable="false" show-icon
                          :title="$t('Only a site administrator can connect, disconnect or switch Remote Portal.')" />

                <template v-if="enabled === 'yes' && !status.connected">
                    <el-alert v-if="!status.app_passwords" type="error" :closable="false" show-icon
                              :title="$t('Remote_Portal_No_App_Passwords')" />
                    <el-alert v-if="!status.https" type="warning" :closable="false" show-icon
                              :title="$t('Remote_Portal_No_Https')" />

                    <h4>{{ $t("Connect your main site") }}</h4>
                    <ol class="fs_remote_portal_steps">
                        <li>{{ $t('Remote_Portal_Step_Agent') }}</li>
                        <li>{{ $t('Remote_Portal_Step_Password') }}</li>
                        <li>
                            {{ $t('Remote_Portal_Step_Connector') }}
                            <div v-if="status.connector_download_url" class="fs_remote_portal_download">
                                <a :href="status.connector_download_url" target="_blank" rel="noopener">{{ $t('Download the connector plugin (.zip)') }}</a>
                            </div>
                        </li>
                        <li>{{ $t('Remote_Portal_Step_Paste') }}</li>
                    </ol>

                    <div v-if="connectionKey" class="fs_remote_portal_form">
                        <el-input :model-value="connectionKey" type="textarea" :rows="3" readonly class="fs_remote_portal_key" @focus="$event.target.select()" />
                        <p class="fs_remote_portal_help">{{ $t('Remote_Portal_Key_Help') }}</p>
                        <div class="fs_remote_portal_actions">
                            <el-button class="fs_filled_btn" @click="copyKey">{{ $t("Copy key") }}</el-button>
                            <el-button class="fs_outline_btn" @click="generateKey">{{ $t("Generate a new key") }}</el-button>
                        </div>
                    </div>
                    <el-button v-else class="fs_filled_btn" v-loading="working" :disabled="working || status.constant_mode || !status.can_manage" @click="generateKey">
                        {{ $t("Generate connection key") }}
                    </el-button>

                    <div class="fs_remote_portal_config">
                        <a v-if="!showConfig" href="#" @click.prevent="openConfig">{{ $t("Use wp-config.php instead") }}</a>
                        <template v-else>
                            <h4>{{ $t("Connect with wp-config.php") }}</h4>
                            <p class="fs_remote_portal_help">{{ $t('Remote_Portal_Config_Intro') }}</p>
                            <el-form label-position="top" class="fs_remote_portal_form">
                                <el-form-item :label="$t('Customer portal page on the main site')">
                                    <el-input v-model="config.portal_url" placeholder="https://example.com/support" />
                                </el-form-item>
                                <el-form-item :label="$t('Username on this site')">
                                    <el-input v-model="config.username" name="fs_remote_portal_config_username" autocomplete="off" />
                                </el-form-item>
                            </el-form>

                            <p><strong>{{ $t("This site's wp-config.php") }}</strong></p>
                            <pre class="fs_remote_portal_snippet">{{ supportSnippet }}</pre>
                            <el-button class="fs_outline_btn" @click="copyText(supportSnippet)">{{ $t("Copy") }}</el-button>

                            <p><strong>{{ $t("The main site's wp-config.php") }}</strong></p>
                            <pre class="fs_remote_portal_snippet">{{ mainSnippet }}</pre>
                            <el-button class="fs_outline_btn" @click="copyText(mainSnippet)">{{ $t("Copy") }}</el-button>

                            <p class="fs_remote_portal_help">{{ $t('Remote_Portal_Config_Help') }}</p>
                        </template>
                    </div>
                </template>

                <template v-if="status.connected">
                    <h4>{{ $t("Connection") }}</h4>
                    <table class="fs_remote_portal_health">
                        <tbody>
                        <tr>
                            <th>{{ $t("Main site") }}</th>
                            <td><a :href="settings.remote_url" target="_blank" rel="noopener">{{ settings.remote_url }}</a></td>
                        </tr>
                        <tr>
                            <th>{{ $t("Customer portal") }}</th>
                            <td>
                                <a v-if="settings.portal_url" :href="settings.portal_url" target="_blank" rel="noopener">{{ settings.portal_url }}</a>
                                <span v-else class="fs_remote_portal_muted">{{ $t("Not set yet") }}</span>
                            </td>
                        </tr>
                        <tr v-if="status.connected_user">
                            <th>{{ $t("Connected as") }}</th>
                            <td>
                                <strong>{{ status.connected_user.display_name }}</strong>
                                ({{ status.connected_user.user_login }})
                                <el-tag size="small" :type="status.connected_user.role === 'admin' ? 'warning' : 'info'">
                                    {{ status.connected_user.role === 'admin' ? $t('Administrator, full site access') : $t('Support agent') }}
                                </el-tag>
                                <div class="fs_remote_portal_muted">{{ status.connected_user.mailboxes }}</div>
                                <a :href="status.connected_user.profile_url" target="_blank" rel="noopener">{{ $t("Manage Application Passwords") }}</a>
                            </td>
                        </tr>
                        <tr v-else-if="status.constant_mode">
                            <th>{{ $t("Connected as") }}</th>
                            <td>{{ $t('Remote_Portal_Constant_User') }}</td>
                        </tr>
                        <tr>
                            <th>{{ $t("Connector version") }}</th>
                            <td>{{ settings.connector_version || '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $t("Customer data from") }}</th>
                            <td>
                                <template v-if="settings.providers.length">
                                    <el-tag v-for="provider in settings.providers" :key="provider" size="small" class="fs_remote_portal_provider">{{ provider }}</el-tag>
                                </template>
                                <span v-else class="fs_remote_portal_muted">{{ $t("None reported") }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>{{ $t("Last request from main site") }}</th>
                            <td>{{ formatDate(settings.last_inbound_at) }}</td>
                        </tr>
                        <tr>
                            <th>{{ $t("Last call to main site") }}</th>
                            <td>
                                {{ formatDate(settings.last_outbound_at) }}
                                <el-tag v-if="settings.last_outbound_ok === 'yes'" size="small" type="success">{{ $t("OK") }}</el-tag>
                                <el-tag v-else-if="settings.last_outbound_ok === 'no'" size="small" type="danger">{{ $t("Failed") }}</el-tag>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <div class="fs_remote_portal_actions">
                        <el-button class="fs_outline_btn" @click="testConnection">{{ $t("Test connection") }}</el-button>
                        <el-popconfirm
                            v-if="!status.constant_mode && status.can_manage"
                            :width="320"
                            :title="$t('Remote_Portal_Disconnect_Confirm')"
                            @confirm="disconnect"
                        >
                            <template #reference>
                                <el-button class="fs_stroke_btn">{{ $t("Disconnect") }}</el-button>
                            </template>
                        </el-popconfirm>
                    </div>
                </template>

                <p v-if="status.constants.length" class="fs_remote_portal_muted">
                    <!-- translators: %s: comma separated list of wp-config constant names -->
                    {{ $t('Set in wp-config.php: %s', status.constants.join(', ')) }}
                </p>
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
import ProShowcase from '@/admin/Components/ProShowcase.vue';

export default {
    name: 'RemotePortal',
    components: {
        ProShowcase
    },
    data() {
        return {
            status: null,
            enabled: 'no',
            saving: false,
            working: false,
            loadFailed: false,
            connectionKey: '',
            keyExpiresAt: 0,
            pollTimer: null,
            polling: false,
            showConfig: false,
            config: {
                token: '',
                portal_url: '',
                username: ''
            }
        };
    },
    computed: {
        promoSpecifics() {
            return [
                this.$t('Keep the helpdesk on its own site'),
                this.$t('Customers open and follow tickets on your main website'),
                this.$t('Connect the two with a one-time key and disconnect at any time')
            ];
        },
        settings() {
            return this.status ? this.status.settings : {};
        },
        supportSnippet() {
            return [
                "define('FLUENT_SUPPORT_PORTAL_BASE_URL', " + this.phpString(this.config.portal_url || 'https://example.com/support') + ');',
                "define('FLUENT_SUPPORT_REMOTE_API_TOKEN', " + this.phpString(this.config.token) + ');',
                "// define('FLUENT_SUPPORT_REMOTE_API_URL', 'https://example.com/wp-json'); // only if the main site's REST path is not /wp-json"
            ].join('\n');
        },
        mainSnippet() {
            return [
                "define('FLUENT_SUPPORT_SERVER_URL', " + this.phpString(this.status.support_url) + ');',
                "define('FLUENT_SUPPORT_SERVER_USERNAME', " + this.phpString(this.config.username || 'USERNAME') + ');',
                "define('FLUENT_SUPPORT_SERVER_PASSWORD', 'APPLICATION PASSWORD');",
                "define('FLUENT_SUPPORT_REMOTE_API_TOKEN', " + this.phpString(this.config.token) + ');',
                "// define('FLUENT_SUPPORT_SERVER_API_URL', " + this.phpString(this.status.support_url + '/wp-json') + "); // only if this site's REST path is not /wp-json"
            ].join('\n');
        }
    },
    methods: {
        fetch() {
            this.loadFailed = false;
            this.$get('settings/remote-portal')
                .then(response => {
                    this.setStatus(response);
                })
                .catch(errors => {
                    this.loadFailed = !this.status;
                    this.$handleError(errors);
                });
        },
        setStatus(response) {
            this.status = response;
            this.enabled = response.settings.enabled;
        },
        saveEnabled() {
            if (this.enabled !== 'yes') {
                this.stopPolling();
                this.connectionKey = '';
            }
            this.saving = true;
            this.$post('settings/remote-portal', {enabled: this.enabled})
                .then(response => {
                    this.setStatus(response);
                    this.$notify({type: 'success', message: response.message, position: 'bottom-right'});
                })
                .catch(errors => {
                    this.enabled = this.enabled === 'yes' ? 'no' : 'yes';
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        testConnection() {
            this.working = true;
            this.$post('settings/remote-portal/test')
                .then(response => {
                    this.setStatus(response);
                    this.$notify({type: 'success', message: response.message, position: 'bottom-right'});
                })
                .catch(errors => {
                    this.$handleError(errors);
                    this.fetch();
                })
                .always(() => {
                    this.working = false;
                });
        },
        generateKey() {
            this.working = true;
            this.$post('settings/remote-portal/connection-key')
                .then(response => {
                    this.connectionKey = response.connection_key;
                    this.keyExpiresAt = response.expires_at;
                    this.setStatus(response);
                    this.startPolling();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.working = false;
                });
        },
        // A new token each time the panel opens; nothing is stored until the constants are set.
        openConfig() {
            const bytes = new Uint8Array(32);
            window.crypto.getRandomValues(bytes);
            this.config.token = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
            this.showConfig = true;
        },
        phpString(value) {
            return "'" + String(value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
        },
        copyText(text) {
            const copied = () => this.$notify({type: 'success', message: this.$t('Copied to clipboard'), position: 'bottom-right'});
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(copied);
                return;
            }
            // The clipboard API needs HTTPS (or localhost).
            const field = document.createElement('textarea');
            field.value = text;
            document.body.appendChild(field);
            field.select();
            document.execCommand('copy') && copied();
            document.body.removeChild(field);
        },
        copyKey() {
            this.copyText(this.connectionKey);
        },
        // Wait for the main site to connect with the key.
        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => {
                if (Date.now() / 1000 > this.keyExpiresAt) {
                    this.stopPolling();
                    this.connectionKey = '';
                    this.$notify({type: 'warning', message: this.$t('The connection key expired. Generate a new one.'), position: 'bottom-right'});
                    return;
                }
                if (this.polling) {
                    return;
                }
                this.polling = true;
                this.$get('settings/remote-portal')
                    .always(() => {
                        this.polling = false;
                    })
                    .then(response => {
                        if (response.connected) {
                            this.stopPolling();
                            this.connectionKey = '';
                            this.setStatus(response);
                            this.$notify({type: 'success', message: this.$t('The main site is connected.'), position: 'bottom-right'});
                        }
                    })
                    .catch(() => {});
            }, 4000);
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        disconnect() {
            this.working = true;
            this.$post('settings/remote-portal/disconnect')
                .then(response => {
                    this.setStatus(response);
                    this.$notify({type: 'success', message: response.message, position: 'bottom-right'});
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.working = false;
                });
        },
        isConstant(name) {
            return !!(this.status && this.status.constants.includes(name));
        },
        formatDate(date) {
            if (!date) {
                return this.$t('Never');
            }
            return this.$dayjs(date).format('MMM D, YYYY h:mm A');
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetch();
        }
        this.$setTitle(this.$t('Remote Portal'));
    },
    beforeUnmount() {
        this.stopPolling();
    }
};
</script>

<style lang="scss">
.fs_remote_portal_body {
    color: var(--fs-text-primary, #0E121B);
    padding: 20px;

    h4 {
        color: var(--fs-text-primary, #0E121B);
        margin: 0 0 12px;
    }

    // The settings body's flex gap spaces the top-level blocks
    > h4 {
        margin: 0;
    }

    a {
        color: var(--fs-text-link, #089360);
    }

    .el-alert {
        margin: 0;
    }
}

.fs_remote_portal_intro {
    margin: 0;
    max-width: 720px;
}

.fs_remote_portal_steps {
    color: var(--fs-text-secondary, #525866);
    margin: 0 0 0 20px;
    max-width: 720px;

    li {
        margin-bottom: 8px;
    }
}

.fs_remote_portal_download {
    margin-top: 4px;
}

.fs_remote_portal_form {
    max-width: 640px;
}

.fs_remote_portal_key textarea {
    font-family: monospace;
    word-break: break-all;
}

.fs_remote_portal_config {
    max-width: 720px;
}

.fs_remote_portal_snippet {
    background: var(--fs-bg-subtle, #F5F7FA);
    color: var(--fs-text-primary, #0E121B);
    border: 1px solid var(--fs-stroke-soft, #E1E4EA);
    border-radius: 6px;
    font-size: 12px;
    margin: 8px 0;
    overflow-x: auto;
    padding: 12px;
    white-space: pre;
}

.fs_remote_portal_help,
.fs_remote_portal_muted {
    color: var(--fs-text-secondary, #525866);
    font-size: 13px;
    margin: 4px 0 0;
}

.fs_remote_portal_health {
    border-collapse: collapse;
    max-width: 720px;
    width: 100%;

    th,
    td {
        border-bottom: 1px solid var(--fs-stroke-soft, #E1E4EA);
        padding: 10px 8px;
        text-align: left;
        vertical-align: top;
    }

    th {
        color: var(--fs-text-secondary, #525866);
        font-weight: 500;
        width: 220px;
    }

    .el-tag {
        margin-left: 6px;
    }
}

.fs_remote_portal_provider {
    margin: 0 6px 6px 0 !important;
}

.fs_remote_portal_actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}
</style>
