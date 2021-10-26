<template>
    <div class="fs_all_tickets">
        <div class="fs_tk_actions fs_tk_header">
            <div class="fs_tk_left">
                <h3>Submit a Support Ticket</h3>
            </div>
            <div class="fs_tk_right">
                <el-button @click="$router.push({ name: 'dashboard' })" size="small" type="info">View All</el-button>
            </div>
        </div>
        <div style="background: white; padding: 20px;" class="fs_tk_body">
            <el-form :data="ticket" label-position="top">
                <el-form-item label="Subject">
                    <el-input placeholder="What's about this support ticket" type="text" v-model="ticket.title"></el-input>
                    <error :error="errors.get('title')"/>
                </el-form-item>
                <el-form-item label="Ticket Details">
                    <wp-editor :height="150" :media-buttons="false" v-model="ticket.content" />
                    <p>Please provide details about your problem</p>
                    <error :error="errors.get('content')"/>
                </el-form-item>

                <attachment-form :ticket="ticket" :attachments="attachments" />

                <div class="fs_tk_actions">
                    <div v-if="products.length" class="fs_tk_left">
                        <el-form-item class="fs_ticket_product" label="Related Product/Service">
                            <el-select v-model="ticket.product_id" placeholder="Select related Product/Service">
                                <el-option v-for="product in products" :key="product.id" :value="product.id" :label="product.title"></el-option>
                            </el-select>
                        </el-form-item>
                        <error :error="errors.get('product_id')"/>
                    </div>
                    <div v-if="ticket_types.length" class="fs_tk_left">
                        <el-form-item label="Select Your Ticket Type">
                            <el-select v-model="ticket.ticket_type_id" placeholder="Select Your Ticket Type">
                                <el-option v-for="ticket_type in ticket_types" :key="ticket_type.id" :value="ticket_type.id" :label="ticket_type.title"></el-option>
                            </el-select>
                        </el-form-item>
                        <error :error="errors.get('ticket_type_id')"/>
                    </div>
                    <div class="fs_tk_left">
                        <el-form-item class="fs_ticket_priority" label="Priority">
                            <el-select v-model="ticket.client_priority" placeholder="Select related Product/Service">
                                <el-option v-for="(priority,priorityKey) in priorities" :key="priorityKey" :value="priorityKey" :label="priority"></el-option>
                            </el-select>
                        </el-form-item>
                        <error :error="errors.get('client_priority')"/>
                    </div>
                </div>

                <el-form-item>
                    <el-button @click="create()" :disabled="creating" v-loading="creating" type="primary">Create Ticket</el-button>
                </el-form-item>

            </el-form>
        </div>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../admin/Pieces/_wp_editor';
import Error from '../../admin/Pieces/Error';
import Errors from '../../admin/Bits/Errors';
import AttachmentForm from "../../admin/Modules/Tickets/_AttachmentForm";


export default {
    name: 'CreateTicket',
    components: {
        WpEditor,
        Error,
        AttachmentForm
    },
    data() {
        return {
            errors: new Errors(),
            creating: false,
            ticket: {
                title: '',
                content: '',
                product_id: '',
                client_priority: 'normal',
                ticket_type_id: ''
            },
            attachments: [],
            products: this.appVars.support_products,
            priorities: this.appVars.customer_ticket_priorities,
            ticket_types: this.appVars.ticket_types
        }
    },
    methods: {
        create() {
            this.errors.clear();
            this.creating = false;
            this.$post('tickets', {
                ...this.ticket,
                attachments: this.attachments
            })
            .then(response => {
                this.$router.push({ name: 'view_ticket', params: { ticket_id: response.ticket.id } });
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
