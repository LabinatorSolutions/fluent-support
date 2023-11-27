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
import {onMounted, reactive, toRefs} from "vue";
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

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
            get,
            handleError
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            basic_fields: {},
            address_fields: {},
            loading: false,
        });

        const customerField = reactive({
            data: {},
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

        const fetchCustomerField = async () => {
            get('customers/customerField')
                .then(response => {
                    state.address_fields = response.customerField.address_fields;
                    state.basic_fields = response.customerField.basic_fields;
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
        onMounted(() => {
            fetchCustomerField();
        });

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
