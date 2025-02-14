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
        AttachmentForm
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
