<template>
    <div v-if="hub.ready && hub.user_can_connect" class="fs_mcp_hub" data-testid="mcp-hub-ready">
        <h4 class="fs_mcp_hub_title">{{ $t('Connect with one URL') }}</h4>
        <p class="fs_mcp_hub_text">{{ introText }}</p>

        <el-tabs v-model="activeKey">
            <el-tab-pane v-for="client in clients" :key="client.key" :label="client.label" :name="client.key" />
        </el-tabs>

        <p v-if="activeClient.note" class="fs_mcp_hub_hint fs_mcp_hub_note">{{ activeClient.note }}</p>
        <pre class="fs_mcp_hub_snippet" data-testid="mcp-hub-snippet">{{ activeClient.snippet }}</pre>
        <div class="fs_mcp_hub_actions">
            <el-button class="fs_filled_btn" @click="copySnippet">{{ $t('Copy') }}</el-button>
            <a v-if="manageLink" class="fs_mcp_hub_link" :href="manageLink.url" target="_blank" rel="noopener noreferrer" data-testid="mcp-hub-manage">{{ manageLink.label }}</a>
        </div>

        <p class="fs_mcp_hub_hint">{{ $t('Site administrators can use this URL for every Fluent plugin. Support agents can use it for Fluent Support.') }}</p>

        <div v-if="hub.can_manage && products.length" class="fs_mcp_hub_products" data-testid="mcp-hub-products">
            <h4 class="fs_mcp_hub_title">{{ $t('Fluent plugins on this URL') }}</h4>
            <div v-for="product in products" :key="product.slug" class="fs_mcp_hub_product" :data-testid="'mcp-product-' + product.slug">
                <span class="fs_mcp_hub_product_name">{{ product.name }}</span>
                <span v-if="product.served" class="fs_status_badge fs_status_active">
                    {{ $_x('On', 'MCP server state') }}
                </span>
                <el-button
                    v-else-if="product.can_enable"
                    class="fs_outline_btn"
                    size="small"
                    :loading="enabling === product.slug"
                    :disabled="!!enabling"
                    @click="$emit('enable', product.slug)"
                >
                    {{ $t('Turn on') }}
                </el-button>
                <span v-else class="fs_status_badge fs_status_inactive">
                    {{ $_x('Off', 'MCP server state') }}
                </span>
            </div>
            <p v-if="hasProductToTurnOn" class="fs_mcp_hub_hint">{{ $t('Connected AI apps can then read and change this plugin\'s data, for the users the plugin allows. To turn a plugin off, use FluentHub.') }}</p>
        </div>
    </div>

    <div v-else-if="canTurnOn" class="fs_mcp_hub fs_mcp_hub_notice" data-testid="mcp-hub-turn-on">
        <h4 class="fs_mcp_hub_title">{{ $t('Connect with one URL') }}</h4>
        <p class="fs_mcp_hub_text">{{ $t('FluentHub can give AI apps one URL for this site, with sign-in through WordPress and no password to copy. Its MCP server is off.') }}</p>
        <el-button class="fs_filled_btn" :loading="enabling === 'hub'" :disabled="!!enabling" @click="$emit('enable', 'hub')">
            {{ $t('Turn on FluentHub sign-in') }}
        </el-button>
        <p class="fs_mcp_hub_hint">{{ $t('Mcp_Hub_Turn_On_Help') }}</p>
    </div>

    <div v-else class="fs_mcp_hub fs_mcp_hub_notice" data-testid="mcp-hub-not-ready">
        <p class="fs_mcp_hub_text">
            <strong>{{ $t('Connect with one URL and no password to copy.') }}</strong>
            {{ reasonText }}
            <a v-if="showOpenHub" :href="hub.manage_url" target="_blank" rel="noopener noreferrer" data-testid="mcp-hub-open">{{ $t('Open FluentHub') }}</a>
        </p>
    </div>
</template>

<script type="text/babel">
/**
 * The "Connect with one URL" card of the MCP settings screen. FluentHub serves
 * every Fluent plugin from one endpoint and signs the user in with their
 * WordPress account. `hub` is what the server read from FluentHub's
 * fluent_kit/mcp_hub_state filter (McpSettingsController::hubState()), with the
 * per-app instructions, so the wording matches FluentHub's own page. The parent
 * does not render this card when `hub` is null (FluentHub absent).
 */
export default {
    name: 'McpHubConnect',

    props: {
        hub: {
            type: Object,
            required: true
        },
        // What the parent is turning on right now: 'hub', a plugin slug, or ''.
        enabling: {
            type: String,
            default: ''
        }
    },

    emits: ['enable'],

    data() {
        return {
            activeKey: this.hub.clients && this.hub.clients.length ? this.hub.clients[0].key : ''
        };
    },

    computed: {
        clients() {
            return this.hub.clients || [];
        },

        products() {
            return this.hub.products || [];
        },

        // Only the server being off is offered as a button. Sign-in switched off
        // on its own was an admin's choice, so that stays a link to FluentHub.
        canTurnOn() {
            return this.hub.hub_reason === 'off' && !!this.hub.can_manage;
        },

        // A user without access is not sent to FluentHub: each plugin decides who
        // may use its AI tools, and FluentHub has no setting for it.
        showOpenHub() {
            return !!(this.hub.can_manage && this.hub.manage_url) && !this.noAccess;
        },

        noAccess() {
            return !!this.hub.ready && !this.hub.user_can_connect;
        },

        hasProductToTurnOn() {
            return this.products.some(product => !product.served && product.can_enable);
        },

        activeClient() {
            return this.clients.find(client => client.key === this.activeKey) || this.clients[0] || { note: '', snippet: '' };
        },

        // A site administrator reaches every Fluent plugin through the URL.
        introText() {
            if (this.hub.can_manage) {
                return this.$t('Add this URL to your AI app and sign in with your WordPress account. There is no password to copy, and the same URL gives the app your other Fluent plugins.');
            }

            return this.$t('Add this URL to your AI app and sign in with your WordPress account. There is no password to copy.');
        },

        manageLink() {
            if (this.hub.can_manage && this.hub.manage_url) {
                return { url: this.hub.manage_url, label: this.$t('Manage connections in FluentHub') };
            }

            if (this.hub.profile_url) {
                return { url: this.hub.profile_url, label: this.$t('Manage your connected apps') };
            }

            return null;
        },

        reasonText() {
            const hub = this.hub;

            if (this.noAccess) {
                return this.$t('Your account has no access to this plugin\'s AI tools.');
            }

            if (hub.hub_reason === 'off') {
                return this.$t('The FluentHub MCP server is off. A site administrator can turn it on.');
            }

            if (hub.hub_reason === 'adapter_missing' || hub.hub_reason === 'adapter_outdated') {
                return this.$t('FluentHub needs a newer MCP adapter for it.');
            }

            if (!hub.included) {
                return this.$t('Fluent Support is switched off in the FluentHub MCP server.');
            }

            if (hub.sign_in_reason === 'plain_permalinks') {
                return this.$t('Sign-in needs pretty permalinks. Change them in Settings, Permalinks.');
            }

            if (hub.sign_in_reason === 'app_passwords_off') {
                return this.$t('Sign-in needs application passwords, which are switched off on this site. They need HTTPS, and a security plugin can turn them off.');
            }

            return this.$t('Sign-in is switched off in FluentHub.');
        }
    },

    watch: {
        // The parent replaces `hub` after a turn-on; the card stays mounted.
        clients(clients) {
            if (!clients.some(client => client.key === this.activeKey)) {
                this.activeKey = clients.length ? clients[0].key : '';
            }
        }
    },

    methods: {
        copySnippet() {
            this.$copyToClipboard(this.activeClient.snippet, this.$t('Connection snippet copied'));
        }
    }
};
</script>

<style scoped>
/* Same look as the card on the other Fluent MCP screens: plain when the URL is
   ready, a dashed notice when something has to be turned on or fixed first. */
.fs_mcp_hub {
    width: 100%;
}
.fs_mcp_hub_notice {
    padding: 12px 14px;
    margin-bottom: 16px;
    border: 1px dashed var(--fs-stroke-soft, #E1E4EA);
    border-radius: var(--fs-radius-6, 6px);
}
.fs_mcp_hub_notice .fs_mcp_hub_text:last-child {
    margin: 0;
}
.fs_mcp_hub_title {
    margin: 0 0 6px;
    font-size: 16px;
    line-height: 24px;
    font-weight: 500;
    color: var(--fs-text-primary, #0E121B);
}
.fs_mcp_hub_text {
    margin: 0 0 16px;
    font-size: 14px;
    line-height: 20px;
    color: var(--fs-text-primary, #0E121B);
}
.fs_mcp_hub_text a {
    color: inherit;
    text-decoration: underline;
}
.fs_mcp_hub_hint {
    font-size: 13px;
    line-height: 18px;
    color: var(--fs-text-secondary, #525866);
    margin: 16px 0 0;
}
.fs_mcp_hub_note {
    margin: 0 0 12px;
}
.fs_mcp_hub_snippet {
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
.fs_mcp_hub_actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}
.fs_mcp_hub_link {
    font-size: 13px;
    color: var(--fs-text-secondary, #525866);
    text-decoration: underline;
}
.fs_mcp_hub_products {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--fs-stroke-soft, #E1E4EA);
}
.fs_mcp_hub_product {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 36px;
    padding: 4px 0;
    font-size: 14px;
}
.fs_mcp_hub_product_name {
    color: var(--fs-text-primary, #0E121B);
}
.fs_mcp_hub_product .fs_status_badge {
    display: inline-block;
    padding: 3px 10px;
    font-size: 13px;
    font-weight: 500;
    line-height: 18px;
}
</style>
