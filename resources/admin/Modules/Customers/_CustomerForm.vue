<template>
    <div v-loading="loading" class="ffc_customer_form">
        <form-builder :fields="fields" :form-data="customer" />
        <el-button @click="updateCustomer()" type="success" v-if="customer.id">{{$t('Update Customer')}}</el-button>
        <el-button @click="createCustomer()" type="success" v-else>{{$t('Create Customer')}}</el-button>
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
                    label: this.$t('First Name'),
                    data_type: 'text',
                    placeholder: this.$t('First Name'),
                    type: 'input-text'
                },
                last_name: {
                    label: this.$t('Last Name'),
                    data_type: 'text',
                    placeholder: this.$t('Last Name'),
                    type: 'input-text'
                },
                email: {
                    label: this.$t('Email'),
                    data_type: 'email',
                    placeholder: this.$t('Email'),
                    type: 'input-text'
                },
                status: {
                    label: this.$t('Status'),
                    data_type: 'text',
                    type: 'input-radio',
                    options:{
                        'active': {'id':'active', 'label':'Active', 'value':'active'},
                        'inactive': {'id':'inactive', 'label':'Inactive', 'value':'inactive'}
                    }
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
                status: this.customer.status
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
                status: this.customer.status
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
