<template>
    <div class="fs_all_tickets">
        <div class="fs_tk_actions fs_tk_header fs_tk_create_head">
            <div class="fs_tk_left">
                <h3>{{$t('submit_heading')}}</h3>
            </div>
            <div class="fs_tk_right">
                <el-button @click="$router.push({ name: 'dashboard' })" size="small" type="info">{{$t('View All')}}</el-button>
            </div>
        </div>
        <div style="background: white; padding: 20px;" class="fs_tk_body">
            <el-form :data="ticket" label-position="top">
                <el-form-item :label="$t('subject')">
                    <el-input :placeholder="$t('subject_placeholder')" type="text" v-model="ticket.title"></el-input>
                    <error :error="errors.get('title')"/>
                </el-form-item>

                <div class="fs_tk_suggestions" v-if="fetchingSuggestions || suggestions.length">
                    <p v-if="fetchingSuggestions">{{$t('suggestion_loading')}}</p>

                    <template v-else>
                        <p class="fs_tk_suggestion_articles">
                            <b>{{ $t('articles_heading') }}</b>
                        </p>

                        <p v-for="suggestion in suggestions">
                            <a :href="suggestion.link" target="_blank" class="fs_tk_suggestion">
                                {{ suggestion.title }}
                            </a>
                        </p>
                    </template>
                </div>

                <el-form-item :label="$t('ticket_details')">
                    <wp-editor :height="150" :media-buttons="false" v-model="ticket.content"/>
                    <p class="fs_tk_help">{{$t('details_help')}}</p>
                    <error :error="errors.get('content')"/>
                </el-form-item>

                <attachment-form v-if="appVars.has_file_upload" :ticket="ticket" :attachments="attachments" />

                <div v-if="products.length || Object.keys(priorities).length" class="fs_tk_row">
                    <div v-if="products.length" class="fs_tk_col">
                        <el-form-item class="fs_ticket_product" :label="$t('product_services')">
                            <el-select clearable v-model="ticket.product_id" :placeholder="$t('service_placeholder')">
                                <el-option v-for="product in products" :key="product.id" :value="product.id" :label="product.title"></el-option>
                            </el-select>
                        </el-form-item>
                        <error :error="errors.get('product_id')"/>
                    </div>
                    <div v-if="Object.keys(priorities).length" class="fs_tk_col">
                        <el-form-item class="fs_ticket_priority" :label="$t('priority')">
                            <el-select clearable v-model="ticket.client_priority" :placeholder="$t('priority_placeholder')">
                                <el-option v-for="(priority,priorityKey) in priorities" :key="priorityKey" :value="priorityKey" :label="priority"></el-option>
                            </el-select>
                        </el-form-item>
                        <error :error="errors.get('client_priority')"/>
                    </div>
                </div>

                <custom-fields-form :ticket="ticket" :custom_data="custom_data" :exceptions="exceptions" />

                <el-form-item>
                    <el-button @click="create()" :disabled="creating" v-loading="creating" type="success">{{$t('btn_text')}}</el-button>
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
import CustomFieldsForm from "./_CustomFieldForm";
import {debounce} from "lodash";


export default {
    name: 'CreateTicket',
    components: {
        WpEditor,
        Error,
        AttachmentForm,
        CustomFieldsForm
    },
    data() {
        return {
            errors: new Errors(),
            creating: false,
            ticket: {
                title: '',
                content: '',
                product_id: '',
                client_priority: 'normal'
            },
            custom_data: {},
            attachments: [],
            products: this.appVars.support_products,
            priorities: this.appVars.customer_ticket_priorities,
            suggestions: [],
            fetchingSuggestions: false,
        }
    },
    computed: {
        exceptions() {
            return this.errors.errors;
        }
    },
    watch: {
        'ticket.title': function (newVal, oldVal) {

            if(!this.appVars.has_doc_integration) {
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
    created () {
        this.debouncedGetSuggestions = debounce(this.getSuggestions, 500);
    },
    methods: {
        create() {
            this.errors.clear();
            this.creating = true;
            this.$post('tickets', {
                ...this.ticket,
                attachments: this.attachments,
                custom_data: this.custom_data
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
        },

        getSuggestions() {
            this.$get('search-doc', {
                search: this.ticket.title
            })
                .then(response => {
                    this.suggestions = response;
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
}
</script>
