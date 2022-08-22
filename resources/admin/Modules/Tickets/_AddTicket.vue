<template>
    <div class="fs_create_ticket">
        <el-form :data="ticket" label-position="top">

            <div class="text-align-center" v-if="step == 'search'">
                <h3>Search Existing contact or provide email address</h3>
                <el-input @keypress.enter.native.prevent="searchCustomers()" v-model="search_customer" size="large"
                          placeholder="Search or provide email address">
                    <template #append>
                        <el-button :disabled="!search_customer" @click="searchCustomers()">
                            <el-icon>
                                <Search/>
                            </el-icon>
                        </el-button>
                    </template>
                </el-input>

                <div style="text-align: left;" v-if="search_results && search_results.data && search_results.data.length && ticket.create_customer != 'yes'"
                     class="fs_customer_selector">
                    <h3>Please Select a contact [{{ search_results.provider }}]</h3>
                    <ul class="fs_contact_results">
                        <li v-for="item in search_results.data" :key="item.id"
                            @click="customerSelected(item, search_results.provider)">{{ item.email }} -
                            {{ item.first_name }} {{ item.last_name }}
                        </li>
                    </ul>
                </div>

                <template v-if="searched">
                    <el-form-item style="margin-top: 20px;">
                        <el-checkbox true-label="yes" false-label="no" v-model="ticket.create_customer">
                            {{ $t('Could not find a contact? Create a new one.') }}
                        </el-checkbox>
                    </el-form-item>

                    <div class="fs_tk_create_customer" v-if="ticket.create_customer=='yes'">
                        <el-row :gutter="30">
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="$t('First Name')">
                                    <el-input :placeholder="$t('First Name')" type="text"
                                              v-model="new_customer.first_name"></el-input>
                                </el-form-item>
                            </el-col>
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="$t('Last Name')">
                                    <el-input :placeholder="$t('Last Name')" type="text"
                                              v-model="new_customer.last_name"></el-input>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item :label="$t('Email')">
                            <el-input placeholder="Email" type="email" v-model="new_customer.email"></el-input>
                        </el-form-item>

                        <el-row :gutter="30" v-if="ticket.create_wp_user=='yes'">
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="$t('Username')">
                                    <el-input :placeholder="$t('Username')" type="text"
                                              v-model="new_customer.username"></el-input>
                                </el-form-item>
                            </el-col>
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="$t('Password')">
                                    <el-input :placeholder="$t('Password (Leave blank for auto generated email)')" type="password" show-password
                                              v-model="new_customer.password"></el-input>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item>
                            <el-checkbox true-label="yes" false-label="no" v-model="ticket.create_wp_user">
                                {{ $t('Create New User in WordPress') }}
                            </el-checkbox>
                        </el-form-item>

                        <el-form-item>
                            <el-button @click="step = 'ticket'" type="primary">Next</el-button>
                        </el-form-item>

                    </div>
                </template>
            </div>

            <template v-else-if="step == 'ticket'">

                <div style="background: #dbdfe5; padding: 10px 10px 5px; margin-bottom: 10px;">
                    <h3 style="margin: 0px;">Selected Contact Details</h3>
                    <p>Name: {{new_customer.first_name}} {{new_customer.first_name}}</p>
                    <p>Email: {{new_customer.email}}</p>
                </div>

                <el-form-item v-if="mailboxes.length > 1" :label="$t('Select Business Inbox')">
                    <el-select v-model="ticket.mailbox_id" :placeholder="$t('Select Business Inbox')">
                        <el-option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id"
                                   :label="mailbox.name"></el-option>
                    </el-select>
                    <error :error="errors.get('mailbox_id')"/>
                </el-form-item>

                <el-form-item :label="$t('Subject')">
                    <el-input :placeholder="$t('subject_placeholder')" type="text"
                              v-model="ticket.title"></el-input>
                    <error :error="errors.get('title')"/>
                </el-form-item>
                <el-form-item :label="$t('Ticket Details')">
                    <el-row :gutter="20" style="position: absolute;right: 86px;z-index: 2;">
                        <el-col :span="6" :offset="20">
                            <template-inserter v-if="appVars.has_pro" @insert="insertTemplate"/>
                        </el-col>
                    </el-row>
                    <wp-editor :height="150" :media-buttons="false" v-model="ticket.content" v-if="editor_ready"/>
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

                <custom-field-form :custom_data="ticket.custom_fields" v-if="has_pro"/>

                <el-col :span="4">
                    <el-form-item>
                        <el-button @click="create()" :disabled="creating" v-loading="creating" type="primary">
                            {{ $t('Create Ticket') }}
                        </el-button>
                    </el-form-item>
                </el-col>
            </template>

        </el-form>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import RemoteSelector from '../../Pieces/RemoteSelector';
import Error from '../../../admin/Pieces/Error';
import Errors from '../../../admin/Bits/Errors';
import CustomFieldForm from './parts/_CustomFieldForm';
import TemplateInserter from './_templateInserter';

export default {
    name: 'CreateTicketForm',
    components: {
        WpEditor,
        RemoteSelector,
        Error,
        CustomFieldForm,
        TemplateInserter
    },
    data() {
        return {
            step: 'search',
            creating: false,
            editor_ready: true,
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
                client_priority: 'normal',
                custom_fields: CustomFieldForm.data(),
                create_customer: 'no',
                create_wp_user: 'no',
            },
            new_customer: {},
            customer_search_item: {
                provider: '',
                id: ''
            },
            search_customer: '',
            search_results: {
                provider: '',
                data: []
            },
            searching: false,
            searched: false
        }
    },
    methods: {
        create() {
            this.errors.clear();
            this.creating = true;
            this.$post('tickets', {
                ticket: this.ticket,
                newCustomer: this.new_customer,
                // customerFromCrm: this.customer_from_crm,
            })
                .then((response) => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.$router.push({name: 'view_ticket', params: {ticket_id: response.ticket.id}});
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        },
        labelMaker(name, email) {
            return `${name} - ${email}`;
        },
        insertTemplate(content) {
            this.editor_ready = false;
            this.ticket.content = this.ticket.content + content;
            this.$nextTick(() => {
                this.editor_ready = true;
            });
        },
        searchCustomers() {
            this.searching = true;
            this.searched = false;
            this.$get('tickets/search-contact', {
                search: this.search_customer
            })
                .then(response => {
                    this.search_results = response;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    // this.errors.record(errors);
                })
                .always(() => {
                    this.searching = false;
                    this.searched = true;
                });
        },
        customerSelected(item, provider) {
            this.new_customer = {...item};
            this.new_customer.provider = provider;
            this.step = 'ticket';
        }
    }
}
</script>
