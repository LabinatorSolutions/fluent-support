<template>
    <div class="fs-incoming-webhooks">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('Incoming Webhook')}}</h3>
                </div>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body fs_narrow_promo" v-if="has_pro && webhook === ''">
                <el-skeleton :rows="5" animated/>
            </div>
            <div class="fs_box_body" v-if="has_pro && webhook">
                <el-form>
                    <el-form-item size="small">
                        <el-input style="width:90%; margin-right:.2em;" label="Incoming Webhook URL" v-model="webhook" :readonly="true" />
                        <el-popconfirm title="Are you sure to regenerate the webhook url? If you regenerate the url you have to change all your used webhook"
                                       @confirm="updateWebhook">
                            <template #reference>
                                <el-button icon="Refresh"></el-button>
                            </template>
                        </el-popconfirm>
                    </el-form-item>
                </el-form>
                <span style="color:red">{{$t('to_create_tickets_using_webhooks_notice')}}</span>
                <h3>{{ $t('Fillable Fields') }}</h3>
                <el-table :data="fields">
                    <el-table-column prop="field" label="Field"/>
                    <el-table-column prop="field_key" label="Field Key"/>
                    <el-table-column prop="type" label="Type" />
                </el-table>
            </div>
        </div>
        <div class="fs_narrow_promo" v-if="!has_pro">
            <h3>{{ $t('use_webhook_to_create_ticket_from_external_site') }}</h3>
            <p>{{ $t('pro_promo') }}</p>
            <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{ $t('Upgrade To Pro') }}</a>
        </div>
    </div>
</template>

<script type="text/babel">

export default {
    name: 'IncomingWebhook',
    data() {
        return {
            loading: false,
            webhook: '',
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
    methods: {
        fetch() {
            this.loading = true;
            this.$get('settings/incoming-webhook')
                .then(response => {
                    this.webhook = response.webhook;
                    this.loading!=this.loading;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading != this.loading;
                });
        },
        updateWebhook() {
            this.loading = true;
            this.$put('settings/incoming-webhook')
                .then(response => {
                    this.$notify.success({
                        'message' : response.message,
                        'position' : 'bottom-right'
                    });
                    this.fetch();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading != this.loading;
                });
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetch();
        }
        this.$setTitle('Incoming Webhook Settings');
    }
};
</script>

