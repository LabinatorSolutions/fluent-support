<template>
    <div class="fs-incoming-webhooks">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t("Incoming Webhook") }}</h3>
                </div>
            </div>
            <div
                class="fs_box_body"
                v-if="has_pro && webhook === ''"
            >
                <FormSkeleton :fields="2" :button="false" />
                <TableSkeleton :rows="4" :columns="3" />
            </div>
            <div class="fs_box_body" v-if="has_pro && webhook" v-loading="loading">
                <div class="fs_incoming_webhook_wrapper">
                    <div class="fs_webhook_settings">
                        <el-row class="fs_select_mailbox">
                            <el-col :span="24">
                                <label class="fs_select_box_label">{{ $t("Select Business Box") }}</label>
                            </el-col>
                            <el-col :span="24" class="fs_mailbox_select_wrapper">
                                <el-select v-model="mailbox"  class="fs_select_field" :placeholder="$t('Select')" :disabled="loading"
                                           @change="changeBusinessBox">
                                    <el-option
                                        v-for="box in mailboxes"
                                        :key="box.id"
                                        :label="box.name+'( '+box.box_type+' )'"
                                        :value="box.id"
                                    />
                                </el-select>
                            </el-col>
                        </el-row>
                        <el-form class="fs_webhook_url">
                            <el-form-item size="small">
                                <el-input
                                    :label="$t('Incoming Webhook URL')"
                                    v-model="webhook"
                                    :readonly="true"
                                    class="fs_webhook_url_input"
                                />
                                <el-popconfirm
                                    :width="310"
                                    :title="
                                        $t(
                                            'Are you sure you want to regenerate the webhook URL? You will need to update every service that uses the current URL.'
                                        )
                                    "
                                    @confirm="updateWebhook"
                                >
                                    <template #reference>
                                        <el-button
                                            size="default"
                                            class="fs_refresh_btn"
                                            :disabled="loading"
                                        >
                                            <DynamicIcon name="Refresh" class="fs_icon"/>
                                        </el-button>
                                    </template>
                                    <template #actions="{ confirm, cancel }">
                                        <div class="fs_popconfirm_actions">
                                            <el-button class="fs_outline_btn" size="small" @click="cancel">{{ $t("Cancel") }}</el-button>
                                            <el-button
                                                class="fs_filled_btn"
                                                size="small"
                                                @click="confirm"
                                            >
                                                {{ $t("Confirm") }}
                                            </el-button>
                                        </div>
                                    </template>
                                </el-popconfirm>
                            </el-form-item>
                            <p class="fc_inline_help">{{
                                    $t("to_create_tickets_using_webhooks_notice")
                                }}</p>
                        </el-form>
                    </div>
                    <hr class="fs_divider" />

                    <div class="fs_webhook_info_table_wrapper">
                        <p>{{ $t("Fillable Fields") }}</p>
                        <el-table class="fs_webhook_info_table" :highlight-current-row="false" :data="fields">
                            <el-table-column prop="field" :label="$t('Field')" />
                            <el-table-column prop="field_key" :label="$t('Field Key')" />
                            <el-table-column prop="type" :label="$t('Type')" />
                        </el-table>
                    </div>
                </div>
            </div>
            <div v-if="!has_pro" class="fs_box_body">
                <ProShowcase
                    :feature="$t('Incoming webhook')"
                    :heading="$t('Turn requests from other tools into tickets')"
                    :description="$t('incoming_webhook_promo_description')"
                    :specifics="promoSpecifics"
                    utm-content="feature_lock_incoming_webhook"
                    docs-url="https://docs.fluentsupport.com/incoming-webhook"
                >
                    <template #preview>
                        <div class="fs_pro_preview">
                            <div class="fs_pro_preview_card fs_pro_preview_fields">
                                <div class="fs_pro_preview_title">
                                    <span class="fs_pro_preview_checkbox"></span>
                                    {{ $t('Incoming webhook') }}
                                </div>
                                <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                                    <span class="fs_pro_preview_row_label">{{ $t('Request') }}</span>
                                    <span class="fs_pro_preview_row_value">POST /webhook/billing</span>
                                </div>
                                <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                                    <span class="fs_pro_preview_row_label">{{ $t('Business box') }}</span>
                                    <span class="fs_pro_preview_chip fs_pro_preview_chip_purple fs_pro_preview_chip_plain">{{ $t('Billing') }}</span>
                                </div>
                                <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                                    <span class="fs_pro_preview_row_label">{{ $t('Result') }}</span>
                                    <span class="fs_pro_preview_badge fs_pro_preview_badge_success">{{ $t('Ticket created') }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </ProShowcase>
            </div>
        </div>
    </div>
</template>
<script type="text/babel">
import ProShowcase from '@/admin/Components/ProShowcase.vue';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import FormSkeleton from '@/admin/Components/FormSkeleton.vue';
import TableSkeleton from '@/admin/Components/TableSkeleton.vue';

export default {
    name: 'IncomingWebhook',
    components: {
        DynamicIcon,
        ProShowcase,
        FormSkeleton,
        TableSkeleton
    },
    data() {
        return {
            loading: false,
            webhook: '',
            mailbox: "",
            confirmedMailbox: "",
            mailboxes: [],
            fields: [
                {
                    field: 'Ticket Title(Required)',
                    field_key: 'title',
                    type: 'Text'
                },
                {
                    field: 'Ticket Content(Required)',
                    field_key: 'content',
                    type: 'Text'
                },
                {
                    field: 'Ticket Priority',
                    field_key: 'priority',
                    type: 'Text'
                },
                {
                    field: 'Customer First Name',
                    field_key: 'sender[first_name]',
                    type: 'Text'
                },
                {
                    field: 'Customer Last Name',
                    field_key: 'sender[last_name]',
                    type: 'Text'
                },
                {
                    field: 'Customer Email(Required)',
                    field_key: 'sender[email]',
                    type: 'Email'
                },
                {
                    field: 'Customer Title',
                    field_key: 'sender[title]',
                    type: 'Text'
                },
                {
                    field: 'Customer Address Line 1',
                    field_key: 'sender[address_line_1]',
                    type: 'Text'
                },
                {
                    field: 'Customer Address Line 2',
                    field_key: 'sender[address_line_2]',
                    type: 'Text'
                },
                {
                    field: 'Customer City',
                    field_key: 'sender[city]',
                    type: 'Text'
                },
                {
                    field: 'Customer State',
                    field_key: 'sender[state]',
                    type: 'Text'
                },
                {
                    field: 'Customer Zip',
                    field_key: 'sender[zip]',
                    type: 'Text'
                },
                {
                    field: 'Customer Country',
                    field_key: 'sender[country]',
                    type: 'Text'
                },
                {
                    field: 'Ticket Custom Field',
                    field_key: 'custom_fields[custom_field_slug]',
                    type: 'Text'
                }

            ],
        };
    },
    computed: {
        promoSpecifics() {
            return [
                this.$t('One secure URL for each business box'),
                this.$t('Map the incoming fields to the ticket subject, message and customer'),
                this.$t('Works with any form, app or script that can send a webhook')
            ];
        }
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('settings/incoming-webhook')
                .then(response => {
                    this.webhook = response.webhook;
                    this.mailboxes = response.mailboxes;
                    this.mailbox = response.mailbox;
                    this.confirmedMailbox = response.mailbox;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        updateWebhook() {
            this.loading = true;
            this.$put('settings/incoming-webhook')
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message : response.message,
                        position : 'bottom-right'
                    });
                    this.fetch();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },

        async changeBusinessBox(value) {
            const confirmed = await ElMessageBox.confirm(
                this.$t('set_mailbox_webhook'),
                this.$t('Warning'),
                {
                    confirmButtonText: this.$t('Set Business Mailbox'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning',
                    confirmButtonClass: 'fs_filled_btn',
                    cancelButtonClass: 'fs_outline_btn'
                }
            ).catch(() => false);

            if (!confirmed) {
                // Revert select to last confirmed mailbox if user cancels
                this.mailbox = this.confirmedMailbox;
                return;
            }

            this.loading = true;
            this.$put("settings/incoming-webhook", { mailbox_id: value })
                .then((response) => {
                    this.$notify({
                        type: 'success',
                        message : response.message,
                        position : 'bottom-right'
                    });
                    this.confirmedMailbox = value;
                    this.fetch();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
    },
    mounted() {
        if (this.has_pro) {
            this.fetch();
        }
        this.$setTitle('Incoming Webhook Settings');
    }
};
</script>
