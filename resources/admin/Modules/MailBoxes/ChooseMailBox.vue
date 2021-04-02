<template>
    <div class="fs_mailboxes">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Businesses</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button size="small" @click="showNewBusinessModal()" type="primary">Add New Business</el-button>
                </div>
            </div>
            <div class="">
                <el-row :gutter="30">
                    <el-col style="max-width: 30%;" v-for="box in mailboxes" :key="box.id" :sm="12" :md="6" :xl="6">
                        <div class="fs_mail_box">
                            <div class="fs_mail_title">
                                <h3>{{ box.name }}</h3>
                            </div>
                            <div class="fs_mail_body">
                                <p>{{ box.email }}</p>
                                <p>Type: {{ box.box_type }}</p>
                                <router-link class="el-button el-button--success el-button--small"
                                             :to="{name: 'box_settings', params: { box_id: box.id }}">View Settings
                                </router-link>
                            </div>
                        </div>
                    </el-col>
                </el-row>
            </div>
        </div>

        <el-dialog
            title="Add a Business"
            v-model="create_modal"
            width="60%">
            <el-form :data="new_business" label-position="top">
                <el-form-item label="Business Name">
                    <el-input type="text" v-model="new_business.name"></el-input>
                </el-form-item>
                <el-form-item label="Business Email">
                    <el-input type="email" v-model="new_business.email"></el-input>
                    <p>Please make sure your website can send emails from this email address</p>
                </el-form-item>
                <el-form-item label="Support Channel">
                    <el-radio-group v-model="new_business.box_type">
                        <el-radio label="web">Web Based</el-radio>
                        <el-radio :disabled="appVars.has_email_parser" label="email">Email Based (MailBox)</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="new_business.box_type == 'email'" label="Mapped Email">
                    <el-input type="email" v-model="new_business.mapped_email"></el-input>
                    <p>Please provide mapped webhook email from where you will send emails as webhook</p>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                  <el-button @click="create_modal = false">Cancel</el-button>
                  <el-button type="primary" @click="createMailBox()">Add Business</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
export default {
    name: 'ChooseMailBox',
    data() {
        return {
            fetching: true,
            mailboxes: [],
            create_modal: false,
            new_business: {
                box_type: 'web',
                name: '',
                email: '',
                mapped_email: '',
                customer_portal_page_id: ''
            },
            creating: false
        }
    },
    methods: {
        fetch() {
            this.fetching = true;
            this.$get('mailboxes')
                .then(response => {
                    this.mailboxes = response.mailboxes;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        showNewBusinessModal() {
            this.new_business = {
                box_type: 'web',
                name: '',
                email: '',
                mapped_email: '',
                customer_portal_page_id: ''
            };
            this.create_modal = true;
        },
        createMailBox() {
            this.creating = true;
            this.$post('mailboxes', {
                business: this.new_business
            })
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });

                    this.$router.push({ name: 'box_settings', params: { box_id: response.mailbox.id } })
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        }
    },
    mounted() {
        this.fetch();
    }
}
</script>

<style lang="scss">
.fs_mail_box {
    border: 1px solid #e3e8ed;
    border-radius: 5px;
    margin-top: 20px;
    text-align: center;

    .fs_mail_title {
        border-bottom: 1px solid #e3e8ed;
        background: white;
        padding: 10px 15px;
    }

    .fs_mail_body {
        padding: 15px;
        background: #f7fafc;
    }

    p, h3 {
        margin: 5px;
    }

    a {
        text-decoration: none;
    }
}
</style>
