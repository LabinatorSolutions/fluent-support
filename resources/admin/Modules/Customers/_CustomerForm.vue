<template>
    <div v-loading="loading" class="ffc_customer_form">
        <form-builder :fields="fields" :form-data="customer" />
        <el-button @click="updateCustomer()" type="success" v-if="customer.id">Update Customer</el-button>
        <el-button @click="createCustomer()" type="success" v-else>Create Customer</el-button>
    </div>
</template>
<script type="text/babel">
import FormBuilder from '../../Pieces/FormElements/_FormBuilder';

export default {
    name: 'CustomerForm',
    props: ['customer'],
    components: {
        FormBuilder
    },
    data() {
        return {
            fields: {
                first_name: {
                    label: 'First Name',
                    data_type: 'text',
                    placeholder: 'First Name',
                    type: 'input-text'
                },
                last_name: {
                    label: 'Last Name',
                    data_type: 'text',
                    placeholder: 'Last Name',
                    type: 'input-text'
                },
                email: {
                    label: 'Email',
                    data_type: 'email',
                    placeholder: 'Email',
                    type: 'input-text'
                }
            },
            loading: false
        }
    },
    methods: {
        updateCustomer() {
            this.loading = true;
            this.$put(`customers/${this.customer.id}`,  {
                first_name: this.customer.first_name,
                last_name: this.customer.last_name,
                email: this.customer.email,
            })
            .then(response => {
                this.$notify.success(response.message);
                this.$emit('updated', response.customer);
            })
            .catch((errors) => {
                this.$handleError(errors);
            })
            .always(() => {
                this.loading = false;
            });
        },
        createCustomer() {
            this.loading = true;
            this.$post('customers',  {
                first_name: this.customer.first_name,
                last_name: this.customer.last_name,
                email: this.customer.email,
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.$emit('updated', response.customer);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        }
    }
}
</script>
