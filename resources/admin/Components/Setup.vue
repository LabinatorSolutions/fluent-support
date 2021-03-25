<template>
    <div class="dashboard fs_box_wrapper">
        <div class="fs_box fs_dashboard_box">
            <div v-if="step == 'mailbox'" class="fs_box_header" style="padding: 20px 15px;font-size: 16px;">
                Good {{ greetingTime }} {{ me.full_name }}, Please setup your support portal first
            </div>
            <div class="fs_box_body fs_padded_20">
                <template v-if="step == 'mailbox'">
                    <h3>Your Business Details</h3>
                    <el-form :data="mailbox" label-position="top">
                        <el-form-item label="Business Name">
                            <el-input placeholder="eg: Awesome Business Inc" type="text"
                                      v-model="mailbox.name"></el-input>
                        </el-form-item>
                        <el-form-item label="Business Email">
                            <el-input placeholder="From Email Address" type="email" v-model="mailbox.email"></el-input>
                            <p>Please make sure your website can send emails from this email address</p>
                        </el-form-item>
                        <el-form-item label="Support Portal Page (For Customers)">
                            <el-select :disabled="global_settings.create_portal_page == 'yes'" clearable
                                       v-loading="loading_pages" filterable placeholder="Select a Page"
                                       v-model="global_settings.portal_page_id">
                                <el-option
                                    v-for="item in pages"
                                    :key="item.ID"
                                    :label="item.post_title"
                                    :value="item.ID">
                                    <span style="float: left">{{ item.post_title }}</span>
                                    <span style="float: right; color: #8492a6; font-size: 13px">{{ item.ID }}</span>
                                </el-option>
                            </el-select>
                            <p>Use shortcode <code>[fluent_support_portal]</code> in the selected page.</p>
                            <el-checkbox v-if="!global_settings.portal_page_id" true-label="yes" false-label="no"
                                         v-model="global_settings.create_portal_page">Create a page automatically with
                                the shortcode
                            </el-checkbox>
                        </el-form-item>
                        <el-form-item style="text-align: right">
                            <el-button v-loading="saving" :disabled="saving" type="success" @click="completeSetup()">
                                Complete Setup
                            </el-button>
                        </el-form-item>
                    </el-form>
                </template>
                <div v-else>
                    <div class="text-align-center">
                        <h1 style="font-size: 45px; color: #7757e6;"><i class="el-icon-circle-check"></i></h1>
                        <h2>Awesome! Your support portal is ready to go!</h2>
                    </div>
                    <hr/>
                    <ul class="fs_listed">
                        <li>
                            <router-link :to="{ name: 'email_settings', params: { box_id: created_box.id } }">View
                                Ticket Email Settings
                            </router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'products' }">Setup Associate Product/Services for Tickets
                            </router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'support-staffs' }">Manage Support Staffs</router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'global_settings' }">Global Settings</router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'dashboard' }">Go to Dashboard</router-link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
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
            saving: false
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
                g = "afternoon";
            } else if (currentHour >= split_evening) {
                g = "evening";
            } else {
                g = "morning";
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
            this.$post('settings/setup', {
                mailbox: this.mailbox,
                global_settings: this.global_settings
            })
                .then(response => {
                    this.created_box = response.mailbox;
                    this.appVars.mailboxes = response.mailboxes;
                    this.step = 'completed';
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {

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
