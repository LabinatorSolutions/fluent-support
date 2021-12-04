<template>
    <div class="fs-incoming-webhooks">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Incoming Webhook</h3>
                </div>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body fs_narrow_promo" v-if="has_pro && webhook==''">
                <el-skeleton :rows="5" animated/>
            </div>
            <div class="fs_box_body" v-if="has_pro && webhook">
                <el-form>
                    <el-form-item>
                        <el-input label="Incoming Webhook URL" v-model="webhook" :readonly="true"/>
                        <span style="color:red">To create tickets using webhooks please copy this webhook URL and paste to the site from where you want to create ticket in this site.</span>
                    </el-form-item>
                </el-form>
                <h3>Fillable Fields</h3>
                <el-table :data="fields">
                    <el-table-column prop="field" label="Field"/>
                    <el-table-column prop="field_key" label="Field Key"/>
                    <el-table-column prop="type" label="Type" />
                </el-table>
            </div>
        </div>
        <div class="fs_narrow_promo" v-if="!has_pro">
            <h3>Use incoming webhook to create ticket from extranal sites.</h3>
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
                    field: 'Ticket Title',
                    field_key: 'title',
                    type: 'Text'
                },
                {
                    field: 'Ticket Content',
                    field_key: 'content',
                    type: 'Text'
                },
                {
                    field: 'First Name',
                    field_key: 'first_name',
                    type: 'Text'
                },
                {
                    field: 'Last Name',
                    field_key: 'last_name',
                    type: 'Text'
                },
                {
                    field: 'Email',
                    field_key: 'email',
                    type: 'Email'
                },
                {
                    field: 'Customer Title',
                    field_key: 'customer_title',
                    type: 'Text'
                },
                {
                    field: 'Address Line 1',
                    field_key: 'address_line_1',
                    type: 'Text'
                },
                {
                    field: 'Address Line 2',
                    field_key: 'address_line_2',
                    type: 'Text'
                },
                {
                    field: 'City',
                    field_key: 'city',
                    type: 'Text'
                },
                {
                    field: 'State',
                    field_key: 'state',
                    type: 'Text'
                },
                {
                    field: 'Zip',
                    field_key: 'zip',
                    type: 'Text'
                },
                {
                    field: 'Country',
                    field_key: 'country',
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

