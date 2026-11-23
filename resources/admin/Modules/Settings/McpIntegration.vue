<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t('MCP for AI Agents') }}</h3>
            </div>
            <div v-if="!fetching && !loadFailed" class="fs_box_actions">
                <span class="fs_status_badge fs_mcp_badge" :class="stateClass" data-testid="mcp-state">{{ stateLabel }}</span>
            </div>
        </div>

        <div v-if="fetching" class="fs_box_body fs_skeleton_loader">
            <el-skeleton animated>
                <template #template>
                    <div class="fs_mcp_skeleton_row">
                        <el-skeleton-item variant="button" class="fs_mcp_skeleton_switch" />
                        <div class="fs_mcp_skeleton_text">
                            <el-skeleton-item variant="text" class="fs_mcp_skeleton_title" />
                            <el-skeleton-item variant="text" class="fs_mcp_skeleton_line" />
                        </div>
                    </div>
                    <el-skeleton-item variant="rect" class="fs_mcp_skeleton_card" />
                </template>
            </el-skeleton>
        </div>

        <div v-else-if="loadFailed" class="fs_box_body fs_mcp_load_error">
            <p>{{ $t('Could not load MCP settings. The controls below are hidden until the server state is confirmed.') }}</p>
            <div>
                <el-button class="fs_filled_btn" @click="fetchStatus">{{ $t('Retry') }}</el-button>
            </div>
        </div>

        <div v-else class="fs_box_body fs_mcp_body">

            <!-- The switch -->
            <div class="fs_mcp_card">
                <div class="fs_mcp_switch">
                    <el-switch v-model="mcpEnabled" :loading="toggling" :aria-label="$t('Enable MCP for AI Agents')" data-testid="mcp-active" @change="handleToggle" />
                    <div>
                        <p class="fs_mcp_switch_label">{{ $t('Enable MCP for AI Agents') }}</p>
                        <span>{{ $t('Mcp_Toggle_Help') }}</span>
                    </div>
                </div>
            </div>

            <template v-if="mcpEnabled">

                <!-- Status -->
                <div class="fs_mcp_card">
                    <h4 class="fs_mcp_card_title">{{ $t('Status') }}</h4>
                    <div class="fs_mcp_card_body">
                        <div v-if="!adapterAvailable" data-testid="mcp-adapter-missing">
                            <p v-if="!abilitiesApi" class="fs_mcp_text">{{ $t('The MCP server needs WordPress 6.9 or newer.') }}</p>
                            <template v-else>
                                <p class="fs_mcp_text">{{ $t('Fluent Support ships AI agent tools, but they only become available once FluentHub is installed and active.') }}</p>
                                <div class="fs_mcp_actions">
                                    <el-button v-if="canAutoInstall" class="fs_filled_btn" :loading="installing" @click="installAdapter">
                                        {{ toolkitInstalled ? $t('Activate FluentHub') : $t('Install FluentHub') }}
                                    </el-button>
                                    <template v-else>
                                        <a :href="toolkitDownloadUrl || pluginsUrl" target="_blank" rel="noopener noreferrer" class="fs_doc_link">{{ $t('Get FluentHub') }}</a>
                                        <span class="fs_mcp_hint">{{ $t('Tip: Fluent Support Pro can install it for you in one click.') }}</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <template v-else>
                            <div class="fs_mcp_row">
                                <div class="fs_mcp_row_label">
                                    <strong>{{ $t('Adapter') }}</strong>
                                    <span>{{ adapterLabel }}</span>
                                </div>
                                <div class="fs_mcp_row_field">
                                    <span class="fs_status_badge fs_mcp_badge fs_status_active">{{ $t('Connected') }}</span>
                                </div>
                            </div>
                            <div v-if="hub" class="fs_mcp_row" data-testid="mcp-hub-row">
                                <div class="fs_mcp_row_label">
                                    <strong>{{ $t('FluentHub sign-in') }}</strong>
                                    <span>{{ $t('One URL for all your Fluent plugins, with no password to copy.') }}</span>
                                </div>
                                <div class="fs_mcp_row_field">
                                    <span class="fs_status_badge fs_mcp_badge" :class="hub.ready ? 'fs_status_active' : 'fs_status_pending'">{{ hub.ready ? $_x('On', 'MCP server state') : $_x('Off', 'MCP server state') }}</span>
                                </div>
                            </div>
                            <div class="fs_mcp_row">
                                <div class="fs_mcp_row_label">
                                    <strong>{{ $t('Available Tools') }}</strong>
                                </div>
                                <div class="fs_mcp_row_field">
                                    <strong data-testid="mcp-count">{{ toolsCount }}</strong>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Connect a client -->
                <div v-if="adapterAvailable" class="fs_mcp_card" data-testid="mcp-connect">
                    <h4 class="fs_mcp_card_title">{{ $t('Connect a client') }}</h4>
                    <div class="fs_mcp_card_body">
                        <!-- FluentHub's one URL for every Fluent plugin, when FluentHub is active -->
                        <mcp-hub-connect v-if="hub" :hub="hub" :enabling="enablingHub" @enable="enableHub" />

                        <p v-if="hubUsable" class="fs_mcp_text fs_mcp_toggle_line">
                            <el-button link class="fs_mcp_toggle_btn" :aria-expanded="showPasswordMethod ? 'true' : 'false'" data-testid="mcp-password-toggle" @click="showPasswordMethod = !showPasswordMethod">
                                {{ showPasswordMethod ? $t('Hide the application password method') : $t('Use an application password instead') }}
                            </el-button>
                        </p>

                        <div v-if="!hubUsable || showPasswordMethod" data-testid="mcp-password-method">
                            <p class="fs_mcp_text">
                                {{ $t('Mcp_Password_Method_Help') }}
                                <a :href="appPasswordsUrl" target="_blank" rel="noopener noreferrer">{{ $t('Open profile → Application Passwords') }}</a>
                            </p>

                            <div class="fs_mcp_creds">
                                <el-input v-model="username" class="fs_text_input fs_text_input_40" :placeholder="$t('WordPress username')" data-testid="mcp-username" />
                                <el-input v-model="appPassword" class="fs_text_input fs_text_input_40" type="password" show-password :placeholder="$t('Application password (xxxx xxxx xxxx xxxx xxxx xxxx)')" data-testid="mcp-password" />
                            </div>

                            <el-tabs v-model="activeClient">
                                <el-tab-pane v-for="client in clients" :key="client.key" :label="client.label" :name="client.key" />
                            </el-tabs>

                            <div v-if="activeClient === 'claude-desktop'" class="fs_mcp_localdev">
                                <el-checkbox v-model="localDev" @change="loadSnippets">
                                    {{ $t('Local site with a self-signed SSL certificate') }}
                                </el-checkbox>
                                <span class="fs_mcp_hint">{{ $t('Adds NODE_TLS_REJECT_UNAUTHORIZED=0 so the proxy accepts the certificate.') }}</span>
                            </div>

                            <el-skeleton v-if="snippetLoading && !snippetsLoaded" animated>
                                <template #template>
                                    <el-skeleton-item variant="rect" class="fs_mcp_skeleton_card" />
                                </template>
                            </el-skeleton>

                            <template v-else-if="activeSnippet">
                                <p v-if="activeSnippet.instructions" class="fs_mcp_hint fs_mcp_instructions">{{ activeSnippet.instructions }}</p>
                                <pre v-loading="snippetLoading" class="fs_mcp_snippet" data-testid="mcp-snippet">{{ renderedSnippet }}</pre>
                                <div class="fs_mcp_actions">
                                    <el-button :class="credentialsReady ? 'fs_filled_btn' : 'fs_outline_btn'" :disabled="!renderedSnippet" @click="copySnippet">
                                        {{ $t('Copy snippet') }}
                                    </el-button>
                                    <span v-if="!credentialsReady" class="fs_mcp_hint">
                                        {{ $t('Enter the username and application password above to get a ready-to-paste snippet.') }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </template>

            <!-- Who can connect -->
            <div class="fs_mcp_card">
                <p class="fs_mcp_text fs_mcp_note">{{ $t('Mcp_Who_Can_Connect') }}</p>
            </div>

        </div>
    </div>
</template>

<script type="text/babel">
import McpHubConnect from './_McpHubConnect.vue';

export default {
    name: 'McpIntegration',

    components: { McpHubConnect },

    data() {
        return {
            fetching: true,
            loadFailed: false,
            toggling: false,
            installing: false,
            snippetLoading: false,
            snippetsLoaded: false,

            mcpEnabled: false,
            adapterAvailable: false,
            toolkitInstalled: false,
            toolkitActive: false,
            toolkitVersion: null,
            adapterVersion: null,
            abilitiesApi: true,
            canAutoInstall: false,
            toolkitDownloadUrl: '',
            pluginsUrl: '',
            toolsCount: 0,
            appPasswordsUrl: '',

            // FluentHub's one-URL connection; null when FluentHub is not active.
            hub: null,
            // What is being turned on right now: 'hub', a plugin slug, or ''.
            enablingHub: '',
            showPasswordMethod: false,

            username: '',
            appPassword: '',
            localDev: false,
            activeClient: 'claude-code',
            snippets: {},

            // The same order as on the other Fluent MCP screens.
            clients: [
                { key: 'claude-code',    label: 'Claude Code' },
                { key: 'codex',          label: 'OpenAI Codex' },
                { key: 'cursor',         label: 'Cursor' },
                { key: 'generic',        label: this.$t('Other') },
                { key: 'claude-desktop', label: 'Claude Desktop' },
            ],
        };
    },

    computed: {
        // The one URL works for this user: lead with it and fold the application
        // password method behind a link.
        hubUsable() {
            return !!(this.hub && this.hub.ready && this.hub.user_can_connect);
        },

        // disabled, pending (on, but no adapter to serve it) or connected.
        state() {
            if (!this.mcpEnabled) {
                return 'disabled';
            }

            return this.adapterAvailable ? 'connected' : 'pending';
        },

        stateLabel() {
            if (this.state === 'disabled') {
                return this.$t('Disabled');
            }

            return this.state === 'connected' ? this.$t('Connected') : this.$t('Adapter Required');
        },

        stateClass() {
            return { disabled: 'fs_status_inactive', pending: 'fs_status_pending', connected: 'fs_status_active' }[this.state];
        },

        adapterLabel() {
            if (this.toolkitActive) {
                // translators: %s is the FluentHub version number
                return this.toolkitVersion ? this.$t('FluentHub %s', this.toolkitVersion) : 'FluentHub';
            }

            if (this.adapterVersion) {
                // translators: %s is the MCP Adapter plugin version number
                return this.$t('WordPress MCP Adapter %s', this.adapterVersion);
            }

            return this.$t('MCP adapter detected');
        },

        credentialsReady() {
            return !!(this.username.trim() && this.appPassword.trim());
        },

        activeSnippet() {
            return this.snippets[this.activeClient] || null;
        },

        renderedSnippet() {
            if (!this.activeSnippet || !this.activeSnippet.snippet) {
                return '';
            }
            let text = this.activeSnippet.snippet;
            const u = this.username.trim();
            const p = this.appPassword.trim();
            if (u && p) {
                try {
                    const basic = btoa(`${u}:${p}`);
                    text = text.split('<base64(your-username:application-password)>').join(basic);
                } catch (_) {
                    // btoa fails on non-latin1 characters — leave the placeholder in place
                }
                text = text.split('<your-username>').join(u);
                text = text.split('<your-application-password>').join(p);
            }
            return text;
        },
    },

    methods: {
        fetchStatus() {
            this.fetching = true;
            this.loadFailed = false;
            return this.$get('settings/mcp')
                .then((response) => {
                    this.mcpEnabled         = !!response.mcp_enabled;
                    this.adapterAvailable   = !!response.adapter_available;
                    this.toolkitInstalled   = !!response.toolkit_installed;
                    this.toolkitActive      = !!response.toolkit_active;
                    this.toolkitVersion     = response.toolkit_version || null;
                    this.adapterVersion     = response.adapter_version || null;
                    this.abilitiesApi       = response.abilities_api !== false;
                    this.canAutoInstall      = !!response.can_auto_install;
                    this.toolkitDownloadUrl = response.toolkit_download_url || '';
                    this.pluginsUrl         = response.plugins_url || '';
                    this.toolsCount         = response.tools_count || 0;
                    this.appPasswordsUrl    = response.app_passwords_url || '';
                    this.localDev           = !!response.is_local_dev;
                    this.hub                = response.hub || null;
                    if (response.current_user_login && !this.username) {
                        this.username = response.current_user_login;
                    }
                })
                .catch((errors) => {
                    this.loadFailed = true;
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },

        handleToggle(value) {
            this.toggling = true;
            this.$post('settings/mcp/toggle', { mcp_enabled: value })
                .then((response) => {
                    this.mcpEnabled = !!response.mcp_enabled;
                    this.$notify({ message: response.message, type: 'success', position: 'bottom-right' });
                    if (this.mcpEnabled && this.adapterAvailable && !this.snippetsLoaded) {
                        this.loadSnippets();
                    }
                })
                .catch((errors) => {
                    this.mcpEnabled = !value;
                    this.$handleError(errors);
                })
                .always(() => {
                    this.toggling = false;
                });
        },

        loadSnippets() {
            this.snippetLoading = true;
            this.$get('settings/mcp/config-snippets', { local_dev: this.localDev ? 'yes' : 'no' })
                .then((response) => {
                    this.snippets       = response.snippets || {};
                    this.snippetsLoaded = true;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.snippetLoading = false;
                });
        },

        installAdapter() {
            this.installing = true;
            this.$post('settings/mcp/install-adapter', {})
                .then((response) => {
                    const type = response.adapter_available ? 'success' : 'warning';
                    this.$notify({ message: response.message, type, position: 'bottom-right' });
                    this.toolkitInstalled = !!response.toolkit_installed;
                    this.toolkitActive    = !!response.toolkit_active;
                    if (response.adapter_available) {
                        // Reload to bring the endpoint fully online after adapter activation.
                        setTimeout(() => { window.location.reload(); }, 800);
                    } else {
                        this.installing = false;
                    }
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    this.installing = false;
                });
        },

        // Turns on the FluentHub MCP server ('hub') or another Fluent plugin's MCP
        // (its slug). Nothing is turned off from this screen.
        enableHub(target) {
            this.enablingHub = target;
            this.$post('settings/mcp/enable-hub', { target })
                .then((response) => {
                    this.hub = response.hub || null;
                    this.mcpEnabled = !!response.mcp_enabled;
                    this.$notify({ message: response.message, type: 'success', position: 'bottom-right' });
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.enablingHub = '';
                });
        },

        copySnippet() {
            this.$copyToClipboard(this.renderedSnippet, this.$t('Connection snippet copied'));
        },
    },

    mounted() {
        this.$setTitle(this.$t('MCP for AI Agents'));
        this.fetchStatus().then(() => {
            if (this.mcpEnabled && this.adapterAvailable) {
                this.loadSnippets();
            }
        });
    },
};
</script>

<style scoped>
/* The body is a stack of cards, as on the other Fluent MCP screens. */
.fs_mcp_body {
    display: flex;
    flex-direction: column;
    /* The settings layout sets its own gap on a box body; the cards use one step. */
    gap: 20px !important;
}

.fs_mcp_load_error {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
}
.fs_mcp_load_error p {
    font-size: 14px;
    color: var(--fs-text-secondary, #525866);
    margin: 0;
}

.fs_mcp_badge {
    display: inline-block;
    padding: 3px 10px;
    font-size: 13px;
    font-weight: 500;
    line-height: 18px;
}

.fs_mcp_card {
    background: var(--fs-bg-primary, #FFF);
    border: 1px solid var(--fs-stroke-soft, #E1E4EA);
    border-radius: var(--fs-radius-md, 8px);
    padding: 20px;
}
.fs_mcp_card_title {
    margin: -20px -20px 0;
    padding: 16px 20px;
    font-size: 16px;
    line-height: 24px;
    font-weight: 500;
    color: var(--fs-text-primary, #0E121B);
    border-bottom: 1px solid var(--fs-stroke-soft, #E1E4EA);
}
.fs_mcp_card_body {
    padding-top: 20px;
}

.fs_mcp_switch {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.fs_mcp_switch span {
    font-size: 14px;
    line-height: 20px;
    color: var(--fs-text-secondary, #525866);
}
.fs_mcp_switch_label {
    margin: 4px 0;
    font-size: 16px;
    line-height: 24px;
    font-weight: 500;
    color: var(--fs-text-primary, #0E121B);
}

.fs_mcp_row {
    display: flex;
    gap: 20px;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid var(--fs-stroke-soft, #E1E4EA);
}
.fs_mcp_row:first-child {
    padding-top: 0;
}
.fs_mcp_row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}
.fs_mcp_row_label {
    flex: 0 0 40%;
}
.fs_mcp_row_label strong {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--fs-text-primary, #0E121B);
}
.fs_mcp_row_label span {
    font-size: 13px;
    color: var(--fs-text-secondary, #525866);
}
.fs_mcp_row_field {
    flex: 1;
    min-width: 0;
    font-size: 14px;
    color: var(--fs-text-primary, #0E121B);
}

.fs_mcp_text {
    margin: 0 0 16px;
    font-size: 14px;
    line-height: 20px;
    color: var(--fs-text-primary, #0E121B);
}
.fs_mcp_text a {
    color: inherit;
    text-decoration: underline;
}
.fs_mcp_note {
    margin: 0;
    color: var(--fs-text-secondary, #525866);
}
.fs_mcp_hint {
    font-size: 13px;
    line-height: 18px;
    color: var(--fs-text-secondary, #525866);
}
.fs_mcp_actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}

.fs_mcp_toggle_line {
    margin: 16px 0 0;
    padding-top: 16px;
    border-top: 1px solid var(--fs-stroke-soft, #E1E4EA);
}
.fs_mcp_toggle_line .fs_mcp_toggle_btn,
.fs_mcp_toggle_line .fs_mcp_toggle_btn:hover,
.fs_mcp_toggle_line .fs_mcp_toggle_btn:focus {
    color: var(--fs-text-link, #089360);
    font-weight: 500;
}
.fs_mcp_toggle_line + div {
    margin-top: 16px;
}

.fs_mcp_creds {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}
.fs_mcp_localdev {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin: 0 0 16px;
    padding: 8px 12px;
    border: 1px dashed var(--fs-stroke-soft, #E1E4EA);
    border-radius: var(--fs-radius-6, 6px);
}
.fs_mcp_instructions {
    margin: 0 0 12px;
}
.fs_mcp_snippet {
    background: var(--fs-bg-subtle, #F5F7FA);
    border: 1px solid var(--fs-stroke-soft, #E1E4EA);
    border-radius: var(--fs-radius-6, 6px);
    color: var(--fs-text-primary, #0E121B);
    padding: 12px;
    margin: 0 0 12px;
    overflow-x: auto;
    font-size: 13px;
    line-height: 20px;
    white-space: pre;
    direction: ltr;
    text-align: left;
}

.fs_mcp_skeleton_row {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.fs_mcp_skeleton_switch {
    width: 40px;
    height: 20px;
    border-radius: 10px;
}
.fs_mcp_skeleton_text {
    flex: 1;
}
.fs_mcp_skeleton_title {
    width: 30%;
    height: 18px;
}
.fs_mcp_skeleton_line {
    width: 75%;
    margin-top: 8px;
}
.fs_mcp_skeleton_card {
    width: 100%;
    height: 160px;
    margin-top: 20px;
}

@media (max-width: 600px) {
    .fs_mcp_creds,
    .fs_mcp_row {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
