<template>
    <div v-loading="loading" class="ffc_customer_form">
        <h3>{{translate('Basic Info')}}</h3>
        <form-builder :fields="basic_fields" :form-data="customer"/>
        <h3>{{translate('Address Info')}}</h3>
        <form-builder :fields="address_fields" :form-data="customer"/>

        <el-button @click="updateCustomer()" type="success" v-if="customer.id">{{ translate('Update Customer') }}</el-button>

        <el-button @click="createCustomer()" type="primary" v-else>{{ translate('Create Customer') }}</el-button>
    </div>
</template>
<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';
import { reactive, toRefs} from "vue";
import { useFluentHelper, useNotify } from "@/admin/Bits/FluentFramework";

export default {
    name: 'CustomerForm',
    props: ['customer'],
    components: {
        FormBuilder
    },
    emits:['updated'],
    setup(props, context){
        const emit = context.emit;
        const customer = reactive(props.customer);
        const {
            translate,
            post,
            put,
            handleError
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            basic_fields: {
                first_name: {
                    label: translate('First Name'),
                    data_type: 'text',
                    placeholder: translate('First Name'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                last_name: {
                    label: translate('Last Name'),
                    data_type: 'text',
                    placeholder: translate('Last Name'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field'
                },
                email: {
                    label: translate('Email'),
                    data_type: 'email',
                    placeholder: translate('Email'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                    disabled: !!customer.id
                },
                title: {
                    label: translate('Job Title'),
                    data_type: 'text',
                    placeholder: translate('Job Title'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field'
                },
                note: {
                    label: translate('Note'),
                    data_type: 'textarea',
                    placeholder: translate('Note'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field'
                },
                status: {
                    label: translate('Status'),
                    data_type: 'text',
                    type: 'input-radio',
                    options: {
                        'active': {'id': 'active', 'value': 'active', 'label': translate('Active')},
                        'inactive': {'id': 'inactive', 'value': 'inactive', 'label': translate('Blocked')}
                    },
                    wrapper_class: 'fs_half_field'
                },
                status_html: {
                    dependency: {
                        depends_on: 'status',
                        operator: '=',
                        value: 'inactive'
                    },
                    type: 'html-viewer',
                    wrapper_class: 'fs_warn',
                    html: translate('block_instruction')
                },
            },
            address_fields: {
                address_line_1: {
                    label: translate('Address Line 1'),
                    data_type: 'text',
                    placeholder: translate('Address Line 1'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                address_line_2: {
                    label: translate('Address Line 2'),
                    data_type: 'text',
                    placeholder: translate('Address Line 2'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                city: {
                    label: translate('City'),
                    data_type: 'text',
                    placeholder: translate('City'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                state: {
                    label: translate('State'),
                    data_type: 'text',
                    placeholder: translate('State'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                zip: {
                    label: translate('Zip Code'),
                    data_type: 'text',
                    placeholder: translate('Zip Code'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                country: {
                    label: translate('Country'),
                    placeholder: translate('Country'),
                    type: 'country-selector',
                    wrapper_class: 'fs_half_field',
                }
            },
            loading: false,
        });

        const createCustomer = async () => {
            state.loading = true;
            post('customers', customer)
                .then(response => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    emit('updated', response.customer);
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        }

        const updateCustomer = async () => {
            state.loading = true;
            put(`customers/${customer.id}`, customer)
                .then(response => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    emit('updated', response.customer);
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        }

        return{
            translate,
            handleError,
            createCustomer,
            updateCustomer,
            ...toRefs(state)
        }
    }
}
</script>
