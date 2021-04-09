<template>
    <div class="fs_create_ticket">
        <el-form :data="ticket" label-position="top">
            <el-form-item label="Select Customer">
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
            <el-form-item v-if="mailboxes.length > 1" label="Select Business">
                <el-select v-model="ticket.mailbox_id" placeholder="Select related Product/Service">
                    <el-option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id"
                               :label="mailbox.name"></el-option>
                </el-select>
                <error :error="errors.get('mailbox_id')"/>
            </el-form-item>

            <el-form-item label="Subject">
                <el-input placeholder="What's about this support ticket" type="text" v-model="ticket.title"></el-input>
                <error :error="errors.get('title')"/>
            </el-form-item>
            <el-form-item label="Ticket Details">
                <wp-editor :height="150" :media-buttons="false" v-model="ticket.content"/>
                <error :error="errors.get('content')"/>
            </el-form-item>

            <div class="fs_tk_actions">
                <div v-if="products.length" class="fs_tk_left">
                    <el-form-item label="Related Product/Service">
                        <el-select v-model="ticket.product_id" placeholder="Select related Product/Service">
                            <el-option v-for="product in products" :key="product.id" :value="product.id"
                                       :label="product.title"></el-option>
                        </el-select>
                        <error :error="errors.get('product_id')"/>
                    </el-form-item>
                </div>
                <div class="fs_tk_left">
                    <el-form-item label="Priority">
                        <el-select v-model="ticket.client_priority" placeholder="Select related Product/Service">
                            <el-option v-for="(priority,priorityKey) in priorities" :key="priorityKey"
                                       :value="priorityKey" :label="priority"></el-option>
                        </el-select>
                        <error :error="errors.get('client_priority')"/>
                    </el-form-item>
                </div>
            </div>

            <el-form-item>
                <el-button @click="create()" :disabled="creating" v-loading="creating" type="primary">
                    Create Ticket
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

export default {
    name: 'CreateTicketForm',
    components: {
        WpEditor,
        RemoteSelector,
        Error
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
                client_priority: ''
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
