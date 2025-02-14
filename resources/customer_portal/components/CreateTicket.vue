<template>
    <div class="fs_support_ticket_container">
        <!-- Back Button -->
        <div class="fs_back_nav">
            <el-button link class="fs_back_button" @click="$router.push({ name: 'dashboard' })">
                <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M2.20437 4.9992L5.91687 8.7117L4.85637 9.7722L0.083374 4.9992L4.85637 0.226196L5.91687 1.2867L2.20437 4.9992Z"
                        fill="#0E121B"/>
                </svg>
                <span>Back to All Tickets</span>
            </el-button>
        </div>

        <div class="fs_ticket_form_container">
            <div class="fs_ticket_header">
                <label>Submit a Support Ticket</label>
            </div>
            <el-form :model="ticket" label-position="top" class="fs_ticket_form">
                <el-form-item label="Subject" class="fs_input_wrapper">
                    <el-input
                        size="default"
                        v-model="ticket.title"
                        :class="{ 'error': errors.get('title') }"
                        placeholder="What's this support ticket about?"
                    />

                    <div v-if="errors.get('title')" class="error-message">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M8 14C4.6862 14 2 11.3138 2 8C2 4.6862 4.6862 2 8 2C11.3138 2 14 4.6862 14 8C14 11.3138 11.3138 14 8 14ZM7.4 7.4V11H8.6V7.4H7.4ZM7.4 5V6.2H8.6V5H7.4Z"
                                fill="#FB3748"/>
                        </svg>
                        <error :error="errors.get('title')"/>
                    </div>

                </el-form-item>

                <div v-if="products.length || Object.keys(priorities).length" class="fs_tk_row">
                    <div v-if="products.length" class="fs_tk_col">
                        <el-form-item class="fs_ticket_product fs_input_label" :label="$t('product_services')"
                                      :required="isProductFieldRequired">
                            <el-select clearable v-model="ticket.product_id"
                                       :class="{ 'error': errors.get('product_id') }"
                                       size="large"
                                       :placeholder="$t('service_placeholder')">
                                <el-option v-for="product in products" :key="product.id" :value="product.id"
                                           :label="product.title"></el-option>
                            </el-select>

                            <div v-if="errors.get('product_id')" class="error-message">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M8 14C4.6862 14 2 11.3138 2 8C2 4.6862 4.6862 2 8 2C11.3138 2 14 4.6862 14 8C14 11.3138 11.3138 14 8 14ZM7.4 7.4V11H8.6V7.4H7.4ZM7.4 5V6.2H8.6V5H7.4Z"
                                        fill="#FB3748"/>
                                </svg>
                                <error :error="errors.get('product_id')"/>
                            </div>


                        </el-form-item>
                    </div>
                </div>

                <!-- Priority -->
                <el-form-item label="Priority" v-if="Object.keys(priorities).length">
                    <el-select
                        v-model="ticket.client_priority"
                        placeholder="Normal"
                        size="large"
                        :class="{ 'error': errors.get('product_id') }"
                    >
                        <el-option
                            v-for="(priority, key) in priorities"
                            :key="key"
                            :label="priority"
                            :value="key"
                        />
                    </el-select>

                    <div v-if="errors.get('client_priority')" class="error-message">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M8 14C4.6862 14 2 11.3138 2 8C2 4.6862 4.6862 2 8 2C11.3138 2 14 4.6862 14 8C14 11.3138 11.3138 14 8 14ZM7.4 7.4V11H8.6V7.4H7.4ZM7.4 5V6.2H8.6V5H7.4Z"
                                fill="#FB3748"/>
                        </svg>
                        <error :error="errors.get('client_priority')"/>
                    </div>
                </el-form-item>

                <!-- Submit Button -->
                <el-form-item class="fs_submit_button_container">
                    <el-button
                        type="primary"
                        :loading="creating"
                        @click="create"
                        class="fs_create_ticket_button"
                    >
                        Create Ticket
                    </el-button>
                </el-form-item>
            </el-form>
        </div>
    </div>
</template>

<script>
import {defineComponent} from 'vue'
import {ArrowLeft, List, UploadFilled} from '@element-plus/icons-vue'
import WpEditor from '../../admin/Pieces/_wp_editor'
import Error from '../../admin/Pieces/Error'
import Errors from '../../admin/Bits/Errors'
import CustomFieldsForm from "./_CustomFieldForm";
import {debounce} from "lodash";

export default defineComponent({
    name: 'CreateTicket',

    components: {
        WpEditor,
        Error,
        ArrowLeft,
        UploadFilled,
        CustomFieldsForm,
        List,
    },

    data() {
        return {
            errors: new Errors(),
            creating: false,
            editorType: 'paragraph',
            editorMode: 'Visual',
            custom_data: {},
            ticket: {
                title: '',
                content: '',
                product_id: '',
                client_priority: 'normal',
                name: '',
                mailbox_id: this.appVars.mailbox_id ? this.appVars.mailbox_id : null,
            },
            products: this.appVars.support_products,
            priorities: this.appVars.customer_ticket_priorities,
            attachments: [],
            suggestions: [],
            fetchingSuggestions: false,
            shouldShowSuggestions: false,
        }
    },

    methods: {
        create() {
            this.errors.clear()
            this.creating = true
            this.$post('tickets', {
                ...this.ticket,
                attachments: this.attachments,
                custom_data: this.custom_data
            })
                .then(response => {
                    this.$router.push({name: 'view_ticket', params: {ticket_id: response.ticket.id}});
                })
                .catch((errors) => {
                    this.errors.record(errors)
                    this.creating = false;
                })
                .finally(() => {
                    this.creating = false
                })
        },

    }
})
</script>
