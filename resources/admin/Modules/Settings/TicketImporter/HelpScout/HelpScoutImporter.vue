<template>
    <div class="fs_helpscout_importer">
        <div class="fs_helpscout_intro">
            <p>{{ $t('Help_Scout_Redirect_Url_Help') }} <code><strong>{{ appVars.rest.url + '/public/authorize' }}</strong></code></p>
        </div>
        <el-form :data="settings" label-position="top">
            <el-form-item :label="$t('App ID')" class="fs_form_item">
                <el-input class="fs_text_input" v-model="settings.app_id" :placeholder="$t('App ID')" />
            </el-form-item>
            <el-form-item :label="$t('App Secret')" class="fs_form_item">
                <div class="fs_input_with_button">
                    <div class="fs_input_container">
                        <el-input class="fs_text_input" v-model="settings.app_secret" :placeholder="$t('App Secret')" />
                        <span class="fs_input_help">
                            <el-icon><InfoFilled /></el-icon>
                            <!-- translators: %s is the label of the Get Authorized button -->
                            {{ $t('Click the "%s" button to get the authorization code.', $t('Get Authorized')) }}
                        </span>
                    </div>
                    <div class="fs_button_container">
                        <el-button class="fs_subtle_btn" @click="authorize" :disabled="!settings.app_secret || !settings.app_id">
                            {{ $t('Get Authorized') }}
                        </el-button>
                    </div>
                </div>
            </el-form-item>

            <el-form-item :label="$t('Authorization Code')" class="fs_form_item">
                <div class="fs_input_with_button">
                    <div class="fs_input_container">
                        <el-input class="fs_text_input" v-model="settings.code" :placeholder="$t('Authorization Code')" />
                        <span class="fs_input_help">
                            <el-icon><InfoFilled /></el-icon>
                            {{ $t('Paste the authorization code here once you get it.') }}
                        </span>
                    </div>
                    <div class="fs_button_container">
                        <el-button class="fs_subtle_btn" @click="getAccessToken" v-loading="requesting_token" :disabled="!settings.code || requesting_token">
                            {{ $t('Request Token') }}
                        </el-button>
                    </div>
                </div>
            </el-form-item>

            <el-form-item :label="$t('Access Token')" class="fs_form_item" v-if="settings.access_token">
                <el-input class="fs_text_input" v-model="settings.access_token" :placeholder="$t('Access Token')" disabled/>
            </el-form-item>

            <el-form-item :label="$t('Choose Mailbox')" class="fs_form_item" v-if="mailboxes.length">
                <el-select class="fs_select_field" v-model="settings.mailbox_id" :placeholder="$t('Please select a mailbox')">
                    <el-option
                        v-for="item in mailboxes"
                        :key="item.id"
                        :label="item.name"
                        :value="item.id">
                    </el-option>
                </el-select>
            </el-form-item>
        </el-form>

        <!-- Footer actions -->
        <div class="fs_importer_footer">
            <el-checkbox
                v-if="Object.keys(previously_imported).length > 0 && settings.mailbox_id == previously_imported.mailbox_id"
                v-model="start_from_previous_migration"
                :label="resumeMigrationLabel"
                size="large"
            />
            <div class="fs_importer_actions">
                <el-button class="fs_outline_btn" @click="$emit('close')">{{ $t('Cancel') }}</el-button>
                <el-button
                    v-if="!start_from_previous_migration"
                    class="fs_filled_btn"
                    @click="$emit('import')"
                    :disabled="!settings.mailbox_id">
                    {{ $t('Import Tickets') }}
                </el-button>
                <el-button
                    v-if="start_from_previous_migration"
                    class="fs_filled_btn"
                    @click="$emit('restart_previous_migration')"
                    :disabled="!settings.mailbox_id">
                    {{ $t('Resume Previous Migration') }}
                </el-button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HelpScoutImporter',
    props: ['show', 'settings', 'previously_imported'],
    emits: ['import', 'close', 'restart_previous_migration'],
    data() {
        return {
            mailboxes: {},
            start_from_previous_migration: false,
            requesting_token: false,
        }
    },
    computed: {
        resumeMigrationLabel() {
            // translators: %s is how much of the previous migration finished, as a percentage, e.g. "40%"
            return this.$t('An incomplete migration exists (%s completed). Would you like to resume from the previous one?', this.previously_imported.completed + '%');
        },
    },
    methods: {
        authorize() {
            // state comes back in the redirect URL, so it must never carry the App Secret.
            const state = Array.from(window.crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
            const url = 'https://secure.helpscout.net/authentication/authorizeClientApplication?client_id=' + encodeURIComponent(this.settings.app_id) + '&state=' + state;
            window.open(url, '_blank');
        },

        getAccessToken() {
            // The server swaps the code for a token, so the App Secret stays out of browser URLs.
            this.requesting_token = true;
            this.$post('ticket_importer/helpscout/token', {
                app_id: this.settings.app_id,
                app_secret: this.settings.app_secret,
                code: this.settings.code
            })
                .then(response => {
                    this.settings.access_token = response.access_token;
                    this.settings.refresh_token = response.refresh_token;
                    this.settings.token_type = response.token_type;
                    this.settings.expires_in = response.expires_in;
                    if (this.settings.access_token) {
                        this.getMailboxes();
                    } else {
                        this.requesting_token = false;
                    }
                })
                .catch(error => {
                    this.requesting_token = false;
                    this.$handleError(error);
                });
        },
        getMailboxes() {
            const that = this;
            const settings = {
                url: 'https://api.helpscout.net/v2/mailboxes',
                method: "GET",
                headers: {
                    Authorization: "Bearer " + this.settings.access_token
                },
            };

            jQuery.ajax(settings).done(response => {
                that.mailboxes = response._embedded.mailboxes;
            }).fail(error => {
                this.$handleError(error);
            }).always(() => {
                this.requesting_token = false;
            });
        },
        handleCancel() {
            this.show = false;
        },
    }
}
</script>

