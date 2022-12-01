<template>
    <div class="dashboard fs_box_wrapper">
        <div class="fs_box fs_dashboard_box">
            <div v-if="step == 'mailbox'" class="fs_box_header" style="padding: 20px 15px;font-size: 16px;">
                {{ $t('Good') }} {{ greetingTime }} {{ me.first_name }},
                {{ $t('Please setup your support portal first') }}
            </div>
            <div class="fs_box_body fs_setup fs_padded_20">
                <template v-if="step == 'mailbox'">
                    <h3>{{ $t('Your Business Details') }}</h3>
                    <el-form :data="mailbox" label-position="top">
                        <el-form-item :label="$t('Business Name')">
                            <el-input :placeholder="$t('eg: Awesome Business Inc')" type="text"
                                      v-model="mailbox.name"></el-input>
                        </el-form-item>
                        <el-form-item :label="$t('Business Email')">
                            <el-input :placeholder="$t('From Email Address')" type="email"
                                      v-model="mailbox.email"></el-input>
                            <p>{{ $t('email_can_be_send') }}</p>
                        </el-form-item>
                        <el-form-item :label="$t('Support Portal Page (For Customers)')">
                            <el-select :disabled="global_settings.create_portal_page == 'yes'" clearable
                                       v-loading="loading_pages" filterable placeholder="Select a Page"
                                       v-model="global_settings.portal_page_id">
                                <el-option
                                    v-for="item in pages"
                                    :key="item.id"
                                    :label="item.title"
                                    :value="item.id">
                                    <span style="float: left">{{ item.title }}</span>
                                    <span style="float: right; color: #8492a6; font-size: 13px">{{ item.id }}</span>
                                </el-option>
                            </el-select>
                            <p>{{ $t('Use shortcode') }} <code>[fluent_support_portal]</code>
                                {{ $t('in the selected page') }}.</p>
                            <el-checkbox v-if="!global_settings.portal_page_id" true-label="yes" false-label="no"
                                         v-model="global_settings.create_portal_page">
                                {{ $t('shortcode_auto_page_creation') }}
                            </el-checkbox>
                        </el-form-item>
                        <el-form-item style="text-align: right">
                            <el-button v-loading="saving" :disabled="saving" type="success" @click="completeSetup()">
                                {{ $t('Continue') }}
                            </el-button>
                        </el-form-item>
                    </el-form>
                </template>
                <template v-else-if="step == 'maintenance'">
                    <h3>{{$t('Almost Done')}}</h3>

                    <p v-if="!has_fluentform">
                        {{$t('Thank you again for configuring your own Support System in WordPress. You may install companion Form Plugin to create tickets from contact forms.')}}<br/>
                        {{$t('Fluent Forms is fast and very light-weight and works perfectly with Fluent Support Plugin')}}
                    </p>

                    <p v-else>
                        {{ $t('Thank you again for configuring your own Support System in WordPress.') }}<br/>
                        {{
                            $t('You can subscribe to our bi-monthly newsletter where we will email you all about Fluent Support Plugin.')
                        }}
                    </p>

                    <div v-if="!has_fluentform" class="suggest_box">
                        <p style="margin-bottom: 10px;"><b>Install Fluent Forms</b></p>

                        <el-checkbox true-label="yes" false-label="no" v-model="install_fluentforms">
                            Install Fluent Forms Plugin for custom Ticket Forms.
                        </el-checkbox>
                    </div>

                    <div class="suggest_box share_essential">
                        <p style="margin-bottom: 10px;"><b>Help us to make FluentSupport better</b></p>
                        <el-checkbox true-label="yes" false-label="no" v-model="share_essentials">
                            {{ $t('Share Essentials') }}
                        </el-checkbox>
                        <p style="margin-top: 10px;">
                            {{ $t('Allow FluentCRM to collect non-sensitive diagnostic data and usage information.') }}
                            <span style="text-decoration: underline;"
                                  @click="show_essential = !show_essential">{{ $t('what we collect') }}</span></p>
                        <p v-if="show_essential">
                            Server environment details (php, mysql, server, WordPress versions), Site language, Number
                            of active plugins, Site name and url, Your name and email address. No sensitive data is
                            tracked.
                        </p>
                    </div>

                    <div class="suggest_box email_optin">
                        <label>Your Email Address</label>
                        <el-input placeholder="Email Address for bi-monthly newsletter" type="email"
                                  v-model="email_address"></el-input>
                        <br/>
                        <p style="margin-top: 20px; font-size: 13px;">
                            We will send tips and advanced usage of FluentSupport (Monthly)
                        </p>
                    </div>

                    <div style="text-align: right">
                        <el-button v-loading="saving" :disabled="saving" type="success" @click="completeTrack()">
                            {{ $t('Continue') }}
                        </el-button>
                    </div>

                </template>
                <div v-else>
                    <div class="text-align-center">
                        <h1 style="font-size: 45px; color: #7757e6;"><el-icon> <CircleCheck/> </el-icon></h1>
                        <h2>{{ $t('support_portal_ready') }}</h2>
                    </div>
                    <hr/>
                    <ul class="fs_listed">
                        <li>
                            <router-link :to="{ name: 'email_settings', params: { box_id: created_box.id } }">View
                                {{ $t('Ticket Email Settings') }}
                            </router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'products' }">
                                {{ $t('Setup Associate Product/Services for Tickets') }}
                            </router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'support-staffs' }">{{ $t('Manage Support Staff') }}</router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'global_settings' }">{{ $t('Global Settings') }}</router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'dashboard' }">{{ $t('Go to Dashboard') }}</router-link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <el-dialog
            title="Build a better FluentCRM"
            :close-on-click-modal="false"
            :show-close="false"
            :append-to-body="true"
            top="30vh"
            custom-class="fc_essential_modal"
            v-model="show_essential_modal"
            width="30%">
            <p>
                Get improved features and faster fixes by sharing non-sensitive data via usage tracking that shows us
                how FluentSupport is used. No personal data is tracked or stored.
            </p>
            <span slot="footer" class="dialog-footer">
                <el-button style="color: gray;" type="text" @click="handleDataShare('no')">No Thanks</el-button>
                <el-button type="primary" @click="handleDataShare('yes')">Yes, Count me in!</el-button>
          </span>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
export default {
    name: 'FluentSupportSetup',
    data() {
        return {
            me: this.appVars.me,
            global_settings: {
                portal_page_id: '',
                create_portal_page: 'no'
            },
            mailbox: {
                name: '',
                email: '',
                box_type: 'web',
                is_default: 'yes'
            },
            created_box: {},
            step: 'mailbox',
            pages: [],
            loading_pages: false,
            saving: false,
            has_fluentform: false,
            install_fluentforms: 'no',
            share_essentials: 'no',
            show_essential: false,
            email_address: '',
            show_essential_modal: false
        }
    },
    computed: {
        greetingTime() {
            const m = this.moment();
            let g = null; //return g

            if (!m || !m.isValid()) {
                return;
            } //if we can't find a valid or filled moment, we return.

            const split_afternoon = 12 //24hr time to split the afternoon
            const split_evening = 17 //24hr time to split the evening
            const currentHour = parseFloat(m.format("HH"));

            if (currentHour >= split_afternoon && currentHour <= split_evening) {
                g = this.$t("afternoon");
            } else if (currentHour >= split_evening) {
                g = this.$t("evening");
            } else {
                g = this.$t("morning");
            }

            return g;
        }
    },
    methods: {
        fetchPages() {
            this.loading_pages = true;
            this.$get('settings/pages')
                .then(response => {
                    this.pages = response.pages;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading_pages = false;
                });
        },
        completeSetup() {
            this.saving = true;
            this.$post('settings/setup', {
                mailbox: this.mailbox,
                global_settings: this.global_settings
            })
                .then(response => {
                    this.created_box = response.mailbox;
                    this.appVars.mailboxes = response.mailboxes;
                    this.has_fluentform = response.has_fluentform;
                    this.step = 'maintenance';
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });

        },
        handleDataShare(status) {
            this.share_essentials = status;
            this.show_essential_modal = false;
            this.completeTrack(true);
        },
        completeTrack(force = false) {
            if (this.share_essentials != 'yes' && !force) {
                this.show_essential_modal = true;
                return;
            }

            this.saving = true;
            this.$post('settings/setup-installation', {
                install_fluentform: this.install_fluentforms,
                share_essentials: this.share_essentials,
                optin_email: this.email_address
            })
                .then(response => {
                    this.$notify.success({
                        title: this.$t('Great!'),
                        message: response.message,
                        offset: 19
                    });
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .always(() => {
                    this.saving = false;
                    this.step = 'completed';
                });
        }
    },
    mounted() {
        if (this.appVars.mailboxes.length) {
            this.$router.push({name: 'dashboard', query: {t: Date.now()}});
        } else {
            this.fetchPages();
        }
    }
}
</script>
