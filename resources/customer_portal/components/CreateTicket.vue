<template>
    <div class="fs_create_ticket_container">
        <!-- Back Button -->
        <back-button />

        <div class="fs_ticket_form_container">
            <div class="fs_ticket_header">
                <label>{{$t('submit_heading')}}</label>
            </div>
            <el-form :model="ticket" label-position="top" class="fs_ticket_form">
                <el-form-item :label="$t('Subject')" class="fs_input_wrapper">
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

                    <div v-if="shouldShowSuggestions && ticket.title.length" class="fs_suggestions_popover">
                        <div class="fs_suggestions_header">
                            <label>{{$t('Suggested Articles')}}</label>
                            <el-button
                                type="text"
                                class="fs_close_button"
                                @click="closeSuggestions"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                     fill="none">
                                    <path
                                        d="M9.99956 8.93949L13.7121 5.22699L14.7726 6.28749L11.0601 9.99999L14.7726 13.7125L13.7121 14.773L9.99956 11.0605L6.28706 14.773L5.22656 13.7125L8.93906 9.99999L5.22656 6.28749L6.28706 5.22699L9.99956 8.93949Z"
                                        fill="#525866"/>
                                </svg>
                            </el-button>
                        </div>
                        <div v-for="(suggestion, index) in suggestions" :key="index" class="fs_suggestions_title">
                            <a :href="suggestion.link" target="_blank" class="fs_article_item">
                                <el-icon class="fs_article_icon">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M16.75 7V16.7448C16.7507 16.8432 16.732 16.9409 16.6949 17.0322C16.6579 17.1234 16.6032 17.2065 16.534 17.2766C16.4649 17.3468 16.3826 17.4026 16.2919 17.4409C16.2011 17.4792 16.1037 17.4993 16.0052 17.5H3.99475C3.79736 17.5 3.60804 17.4216 3.4684 17.2821C3.32875 17.1426 3.2502 16.9534 3.25 16.756V3.244C3.25 2.84125 3.58675 2.5 4.0015 2.5H12.2477L16.75 7ZM15.25 7.75H11.5V4H4.75V16H15.25V7.75ZM7 6.25H9.25V7.75H7V6.25ZM7 9.25H13V10.75H7V9.25ZM7 12.25H13V13.75H7V12.25Z"
                                            fill="#525866"/>
                                    </svg>
                                </el-icon>
                                <p>{{ suggestion.title }}</p>
                            </a>
                        </div>
                    </div>
                </el-form-item>


                <el-form-item class="fs_tk_suggestions">
                    <label class="fs_ticket_details_label"> {{ $t('ticket_details') }}</label>
                    <wp-editor :height="150" :media-buttons="false" :is_direct_paste="true" v-model="ticket.content"/>
                    <p class="fs_tk_help">{{ $t('details_help') }}</p>

                    <div v-if="errors.get('content')" class="error-message">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M8 14C4.6862 14 2 11.3138 2 8C2 4.6862 4.6862 2 8 2C11.3138 2 14 4.6862 14 8C14 11.3138 11.3138 14 8 14ZM7.4 7.4V11H8.6V7.4H7.4ZM7.4 5V6.2H8.6V5H7.4Z"
                                fill="#FB3748"/>
                        </svg>
                        <error :error="errors.get('content')"/>
                    </div>
                </el-form-item>


                <!-- File Upload -->

                <attachment-form v-if="appVars.has_file_upload" :ticket="ticket" :attachments="attachments"/>

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

                <!-- Your Name -->

                <custom-fields-form :ticket="ticket" :custom_data="custom_data" :exceptions="exceptions"/>

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
import AttachmentForm from "./_AttachmentForm";
import BackButton from "./pieces/BackButton";
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
        AttachmentForm,
        BackButton
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
    computed: {
        exceptions() {
            return this.errors.errors;
        }
    },
    watch: {
        'ticket.title': function (newVal, oldVal) {

            if (!this.appVars.has_doc_integration) {
                return;
            }

            if (this.hasQueryOrSpace(newVal)) {
                this.fetchingSuggestions = true;
                this.debouncedGetSuggestions();
            } else {
                this.fetchingSuggestions = false;
                this.suggestions = [];
            }
        }
    },
    created() {
        this.debouncedGetSuggestions = debounce(this.getSuggestions, 500);
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
        closeSuggestions() {
            this.shouldShowSuggestions = false;
        },
        getSuggestions() {
            this.$get('search-doc', {
                search: this.ticket.title
            })
                .then(response => {
                    this.suggestions = response;

                    if (this.suggestions.length) {
                        this.shouldShowSuggestions = true;
                    }
                })
                .catch((errors) => {
                    console.log(errors);
                    // this.errors.record(errors);
                })
                .always(() => {
                    this.fetchingSuggestions = false;
                });
        },

        hasQueryOrSpace(search) {
            return search && (search.length >= 5 || (search.split(' ').length > 1));
        }
    }
})
</script>

<style lang="scss">


</style>


