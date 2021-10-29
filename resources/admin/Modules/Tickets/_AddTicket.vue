<template>
    <div class="fs_create_ticket">
        <el-form :data="ticket" label-position="top">
            <el-form-item :label="$t('Select Customer')">
                <remote-selector
                    v-model="ticket.customer_id"
                    response_key="customers"
                    api_path="customers"
                    value_selector="id"
                    label_joiner=" - "
                    :label_selectors="['full_name','email']"
                />
                <error :error="errors.get('customer_id')"/>
            </el-form-item>
            <el-form-item v-if="mailboxes.length > 1" :label="$t('Select Business')">
                <el-select v-model="ticket.mailbox_id" :placeholder="$t('Select related Product/Service')">
                    <el-option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id"
                               :label="mailbox.name"></el-option>
                </el-select>
                <error :error="errors.get('mailbox_id')"/>
            </el-form-item>

            <el-form-item :label="$t('Subject')">
                <el-input :placeholder="$t('What\'s about this support ticket')" type="text"
                          v-model="ticket.title"></el-input>
                <error :error="errors.get('title')"/>
            </el-form-item>
            <el-form-item :label="$t('Ticket Details')">
                <wp-editor :height="150" :media-buttons="false" v-model="ticket.content"/>
                <error :error="errors.get('content')"/>
            </el-form-item>

            <el-row :gutter="30">
                <el-col v-if="products.length" :md="12" :xs="24">
                    <el-form-item :label="$t('Related Product/Service')">
                        <el-select v-model="ticket.product_id" :placeholder="$t('Select related Product/Service')">
                            <el-option v-for="product in products" :key="product.id" :value="product.id"
                                       :label="product.title"></el-option>
                        </el-select>
                        <error :error="errors.get('product_id')"/>
                    </el-form-item>
                </el-col>
                <el-col :md="12" :xs="24">
                    <el-form-item :label="$t('Priority (Customer)')">
                        <el-select v-model="ticket.client_priority" :placeholder="$t('Select Priority')">
                            <el-option v-for="(priority,priorityKey) in priorities" :key="priorityKey"
                                       :value="priorityKey" :label="priority"></el-option>
                        </el-select>
                        <error :error="errors.get('client_priority')"/>
                    </el-form-item>
                </el-col>
            </el-row>

            <custom-fields-form :custom_data="ticket.custom_fields" v-if="has_pro" />

            <el-form-item>
                <el-button @click="create()" :disabled="creating" v-loading="creating" type="primary">
                    {{ $t('Create Ticket') }}
                </el-button>
            </el-form-item>

        </el-form>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import RemoteSelector from '../../Pieces/RemoteSelector';
import Error from '../../../admin/Pieces/Error';
import Errors from '../../../admin/Bits/Errors';
import CustomFieldsForm from './parts/_CustomFieldForm';

export default {
    name: 'CreateTicketForm',
    components: {
        WpEditor,
        RemoteSelector,
        Error,
        CustomFieldsForm
    },
    data() {
        return {
            creating: false,
            products: this.appVars.support_products,
            priorities: this.appVars.client_priorities,
            mailboxes: this.appVars.mailboxes,
            errors: new Errors(),
            ticket: {
                customer_id: '',
                mailbox_id: '',
                title: '',
                content: '',
                product_id: '',
                client_priority: '',
                custom_fields: {}
            }
        }
    },
    methods: {
        create() {
            this.errors.clear();
            this.creating = true;
            this.$post('tickets', {
                ticket: this.ticket
            })
                .then((response) => {
                    this.$notify.success(response.message);
                    this.$router.push({name: 'view_ticket', params: {ticket_id: response.ticket.id}});
                })
                .catch((errors) => {
                    this.errors.record(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        }
    }
}
</script>
