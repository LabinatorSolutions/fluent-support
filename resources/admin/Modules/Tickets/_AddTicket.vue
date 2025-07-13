<template>
    <div class="fs_create_ticket">
        <el-form :data="ticket" label-position="top">

            <div class="text-align-center" v-if="step == 'search'">
                <h3>{{ translate('Search Existing contact or provide email address') }}</h3>
                <el-input @keypress.enter.native.prevent="searchCustomers()" v-model="search_customer" size="large"
                          :placeholder="translate('Search or provide email address')">
                    <template #append>
                        <el-button :disabled="!search_customer" @click="searchCustomers()">
                            <el-icon>
                                <Search/>
                            </el-icon>
                        </el-button>
                    </template>
                </el-input>

                <div style="text-align: left;"
                     v-if="search_results && search_results.data && search_results.data.length && ticket.create_customer != 'yes'"
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
                        <el-checkbox true-value="yes" false-value="no" v-model="ticket.create_customer">
                            {{ translate('Could not find a contact? Create a new one.') }}
                        </el-checkbox>
                    </el-form-item>

                    <div class="fs_tk_create_customer" v-if="ticket.create_customer=='yes'">
                        <el-row :gutter="30">
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="translate('First Name')">
                                    <el-input :placeholder="translate('First Name')" type="text"
                                              v-model="new_customer.first_name"></el-input>
                                </el-form-item>
                            </el-col>
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="translate('Last Name')">
                                    <el-input :placeholder="translate('Last Name')" type="text"
                                              v-model="new_customer.last_name"></el-input>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item :label="translate('Email')" :error="validation_errors.email">
                            <el-input placeholder="Email" type="email" v-model="new_customer.email"></el-input>
                        </el-form-item>

                        <el-row :gutter="30" v-if="ticket.create_wp_user == 'yes'">
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="translate('Username')" :error="validation_errors.username">
                                    <el-input :placeholder="translate('Username')" type="text"
                                              v-model="new_customer.username"></el-input>
                                </el-form-item>
                            </el-col>
                            <el-col :md="12" :xs="24">
                                <el-form-item :label="translate('Password')" :error="validation_errors.password">
                                    <el-input
                                        :placeholder="translate('Password (Leave blank for auto generated)')"
                                        type="password"
                                        show-password
                                        v-model="new_customer.password">
                                    </el-input>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item>
                            <el-checkbox true-value="yes" false-value="no" v-model="ticket.create_wp_user">
                                {{ translate('Create New User in WordPress') }}
                            </el-checkbox>
                        </el-form-item>

                        <el-form-item>
                            <el-button @click="goToTicketStep" type="primary">
                                {{ translate('Next') }}
                            </el-button>
                        </el-form-item>

                    </div>
                </template>
            </div>

            <template v-else-if="step == 'ticket'">

                <div style="background: #dbdfe5; padding: 10px 10px 5px; margin-bottom: 10px;">
                    <h3 style="margin: 0px;">{{ translate('Selected Contact Details') }}</h3>
                    <p>{{ translate('Name:') }} {{ new_customer.first_name }} {{ new_customer.last_name }}</p>
                    <p>{{ translate('Email:') }} {{ new_customer.email }}</p>
                </div>

                <el-form-item v-if="mailboxes.length > 1" :label="translate('Select Business Inbox')">
                    <el-select v-model="ticket.mailbox_id" :placeholder="translate('Select Business Inbox')">
                        <el-option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id"
                                   :label="mailbox.name"></el-option>
                    </el-select>
                    <error :error="errors.get('mailbox_id')"/>
                </el-form-item>

                <el-form-item :label="translate('Subject')">
                    <el-input :placeholder="translate('subject_placeholder')" type="text"
                              v-model="ticket.title"></el-input>
                    <error :error="errors.get('title')"/>
                </el-form-item>
                <el-form-item :label="translate('Ticket Details')">
                    <wp-editor :height="150" :autofocus="true" :media-buttons="false" :is_direct_paste="true" v-model="ticket.content" v-if="editor_ready"
                               :show-saved-replies="true"/>
                    <error :error="errors.get('content')"/>
                </el-form-item>

                <attachment-form :ticket="ticket" :attachments="attachments" :errors="errors"></attachment-form>

                <el-row :gutter="30">
                    <el-col v-if="products.length" :md="12" :xs="24">
                        <el-form-item :label="translate('Related Product/Service')">
                            <el-select v-model="ticket.product_id"
                                       :placeholder="translate('Select related Product/Service')">
                                <el-option v-for="product in products" :key="product.id" :value="product.id"
                                           :label="product.title"></el-option>
                            </el-select>
                            <error :error="errors.get('product_id')"/>
                        </el-form-item>
                    </el-col>
                    <el-col :md="12" :xs="24">
                        <el-form-item :label="translate('Priority (Customer)')">
                            <el-select v-model="ticket.client_priority" :placeholder="translate('Select Priority')">
                                <el-option v-for="(priority,priorityKey) in priorities" :key="priorityKey"
                                           :value="priorityKey" :label="priority"></el-option>
                            </el-select>
                            <error :error="errors.get('client_priority')"/>
                        </el-form-item>
                    </el-col>
                </el-row>

                <custom-field-form :custom_data="ticket.custom_fields" :ticket="ticket" type="create_ticket" v-if="has_pro"/>

                <el-col :span="4">
                    <el-form-item>
                        <el-button @click="create()" :disabled="creating" v-loading="creating" type="primary">
                            {{ translate('Create Ticket') }}
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
import {nextTick, reactive, toRefs} from "vue";
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";
import {useRouter} from 'vue-router'
import AttachmentForm from "@/admin/Modules/Tickets/_AttachmentForm.vue";

export default {
    name: 'CreateTicketForm',
    components: {
        AttachmentForm,
        WpEditor,
        RemoteSelector,
        Error,
        CustomFieldForm
    },

    setup() {
        const {
            translate,
            handleError,
            appVars,
            post,
            get
        } = useFluentHelper();
        const {notify} = useNotify();
        const router = useRouter()

        const state = reactive({
            step: 'search',
            creating: false,
            editor_ready: true,
            products: appVars.support_products,
            priorities: appVars.client_priorities,
            mailboxes: appVars.mailboxes,
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
            searched: false,
            attachments: [],
            validation_errors: {
                email: '',
                username: '',
                password: ''
            },
        });

        const create = () => {
            state.errors.clear();
            state.creating = true;
            post('tickets', {
                ticket: state.ticket,
                newCustomer: state.new_customer,
                attachments: state.attachments
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        position: 'bottom-right',
                        type: 'success'
                    });
                    router.push({name: 'view_ticket', params: {ticket_id: response.ticket.id}});
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.creating = false;
                });
        };

        const labelMaker = (name, email) => {
            return `${name} - ${email}`;
        };

        const insertTemplate = (content) => {
            state.editor_ready = false;
            state.ticket.content = state.ticket.content + content;
            nextTick(() => {
                state.editor_ready = true;
            });
        };

        const searchCustomers = () => {
            state.searching = true;
            state.searched = false;
            get('tickets/search-contact', {
                search: state.search_customer
            })
                .then(response => {
                    state.search_results = response;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.searching = false;
                    state.searched = true;
                });
        };

        const customerSelected = (item, provider) => {
            state.new_customer = {...item};
            state.new_customer.provider = provider;
            state.step = 'ticket';
        }

        const isValidEmail = (email) => {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        };

        const resetValidationErrors = () => {
            state.validation_errors.email = '';
            state.validation_errors.username = '';
            state.validation_errors.password = '';
        };

        const validateEmail = () => {
            const email = state.new_customer.email;
            if (!email || !isValidEmail(email)) {
                state.validation_errors.email = translate('Please provide a valid email address.');
                return false;
            }
            return true;
        };

        const validateWpUser = () => {
            if (state.ticket.create_wp_user !== 'yes') return true;

            let isValid = true;

            if (!state.new_customer.username) {
                state.validation_errors.username = translate('Username is required for WordPress user.');
                isValid = false;
            }

            if (!state.new_customer.password) {
                state.validation_errors.password = translate('Password is required.');
                isValid = false;
            }

            return isValid;
        };

        const goToTicketStep = () => {
            resetValidationErrors();

            if (state.ticket.create_customer === 'yes') {
                const isEmailValid = validateEmail();
                const isWpUserValid = validateWpUser();

                if (!isEmailValid || !isWpUserValid) return;
            }

            state.step = 'ticket';
        };


        return {
            ...toRefs(state),
            create,
            labelMaker,
            insertTemplate,
            searchCustomers,
            customerSelected,
            goToTicketStep,
            translate,
        }
    }
}
</script>
