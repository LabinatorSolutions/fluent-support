<template>
    <div class="fs_mailboxes">
        <div class="fs_box_wrapper" v-loading="fetching">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('Business Inboxes') }}</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button size="small" @click="showNewBusinessModal()" type="primary">
                        {{ $t('Add New Business Inbox') }}
                    </el-button>
                </div>
            </div>
            <div class="">
                <el-row :gutter="30">
                    <el-col style="max-width: 30%;" v-for="box in mailboxes" :key="box.id" :sm="12" :md="6" :xl="6">
                        <div class="fs_mail_box">
                            <div class="fs_mail_title">
                                <h3>{{ box.name }}</h3>
                                <el-dropdown @command="handleBoxCommand" class="fs_mail_action" trigger="click">
                                      <span class="el-dropdown-link">
                                            <i class="el-icon-arrow-down el-icon--right"></i>
                                      </span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item :command="{ type: 'delete', box_id: box.id }"
                                                              icon="el-icon-delete">{{ $t('Delete') }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                            <div class="fs_mail_body">
                                <p>{{ box.email }}</p>
                                <p>Type: {{ box.box_type }}</p>
                                <p>Tickets Counts: {{ box.tickets_count }}</p>
                                <router-link class="el-button el-button--success el-button--small"
                                             :to="{name: 'box_settings', params: { box_id: box.id }}">
                                    {{ $t('View Settings') }}
                                </router-link>
                            </div>
                        </div>
                    </el-col>
                </el-row>
            </div>
        </div>

        <el-dialog
            :title="$t('Add a New Business Inbox')"
            v-model="create_modal"
            width="60%">
            <el-form :data="new_business" label-position="top">
                <el-form-item :label="$t('Inbox Name')">
                    <el-input type="text" v-model="new_business.name"></el-input>
                </el-form-item>
                <el-form-item :label="$t('Support Inbox Email')">
                    <el-input type="email" v-model="new_business.email"></el-input>
                    <p>{{ $t('email_can_be_send') }}</p>
                </el-form-item>
                <el-form-item :label="$t('Support Channel')">
                    <el-radio-group v-model="new_business.box_type">
                        <el-radio label="web">{{ $t('Web Based') }}</el-radio>
                        <el-radio :disabled="!appVars.has_email_parser" :label="$t('email')">
                            {{ $t('Email Based (MailBox)') }}
                        </el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                  <el-button @click="create_modal = false">{{ $t('Cancel') }}</el-button>
                  <el-button type="primary" @click="createMailBox()">{{ $t('Add Business Inbox') }}</el-button>
                </span>
            </template>
        </el-dialog>

        <el-dialog
            :title="$t('Are You Sure? You can not undo this action.')"
            v-model="deleting_box.show_modal"
            width="60%">
            <el-form :data="deleting_box" label-position="top">
                <el-form-item :label="$t('Fallback Business')">
                    <el-select v-model="deleting_box.fallback_box" :placeholder="$t('Select related Product/Service')">
                        <el-option :disabled="mailbox.id == deleting_box.box_id" v-for="mailbox in mailboxes"
                                   :key="mailbox.id" :value="mailbox.id"
                                   :label="mailbox.name"></el-option>
                    </el-select>
                    <p>{{ $t('select_fallback_business') }}</p>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                  <el-button @click="deleting_box.show_modal = false">{{ $t('Cancel') }}</el-button>
                  <el-button v-loading="deleteing" :disabled="deleteing" type="danger"
                             @click="deleteMailBox()">{{ $t('Confirm Delete This Business') }}</el-button>
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
            creating: false,
            deleting_box: {
                show_modal: false,
                box_id: '',
                fallback_box: ''
            },
            deleteing: false
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

                    if (response.mailbox.box_type == 'email') {
                        this.$router.push({name: 'email_piping', params: {box_id: response.mailbox.id}})
                    } else {
                        this.$router.push({name: 'box_settings', params: {box_id: response.mailbox.id}})
                    }

                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        },
        handleBoxCommand(command) {
            if (command.type == 'delete') {
                this.deleting_box.box_id = command.box_id;
                this.deleting_box.show_modal = true;
            }
        },
        deleteMailBox() {
            if (!this.deleting_box.fallback_box || !this.deleting_box.box_id) {
                alert(this.$t('Please provide fallback box'));
                return false;
            }

            this.deleteing = true;
            this.$del(`mailboxes/${this.deleting_box.box_id}`, {
                fallback_id: this.deleting_box.fallback_box,
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.fetch();
                    this.deleting_box = {
                        show_modal: false,
                        box_id: '',
                        fallback_box: ''
                    };
                })
                .catch((errors) => {
                    this.handleError(errors);
                })
                .always(() => {
                    this.deleteing = false;
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
        text-align: left;
        overflow: hidden;

        h3 {
            float: left;
        }

        .fs_mail_action {
            float: right;
            line-height: 27px;
        }
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
