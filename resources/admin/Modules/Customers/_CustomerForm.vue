<template>
    <div v-loading="loading" class="ffc_customer_form">
        <form-builder :fields="fields" :form-data="customer" />
        <el-button @click="updateCustomer()" type="success" v-if="customer.id">{{$t('Update Customer')}}</el-button>
        <el-button @click="deleteCustomer()" type="danger" v-if="customer.id">{{$t('Delete Customer')}}</el-button>
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
                title: {
                    label: this.$t('Job Title'),
                    data_type: 'text',
                    placeholder: this.$t('Job Title'),
                    type: 'input-text'
                },
                note: {
                    label: this.$t('Note'),
                    data_type: 'textarea',
                    placeholder: this.$t('Note'),
                    type: 'input-text'
                },
                status: {
                    label: this.$t('Status'),
                    data_type: 'text',
                    type: 'input-radio',
                    options:{
                        'active': {'id':'active', 'value':'active', 'label':'Active'},
                        'inactive': {'id':'inactive', 'value':'inactive', 'label':'Inactive'}
                    },
                },
                status_html: {
                    dependency: {
                        depends_on: 'status',
                        operator: '=',
                        value: 'inactive'
                    },
                    type: 'html-viewer',
                    wrapper_class: 'fs_warn',
                    html: 'If you select <b>Inactive</b> status then this customer can not submit a ticket or any response'
                }
            },
            loading: false
        }
    },
    methods: {
        customersInformation() {
            return {
                first_name: this.customer.first_name,
                last_name: this.customer.last_name,
                email: this.customer.email,
                status: this.customer.status,
                title: this.customer.title,
                note: this.customer.note
            }
        },
        updateCustomer() {
            this.loading = true;
            this.$put(`customers/${this.customer.id}`,  this.customersInformation())
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
            this.$post('customers',  this.customersInformation())
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
        deleteCustomer() {
            this.loading = !this.loading;
            this.$del(`customers/${this.customer.id}`)
            .then(response => {
                this.$router.go(-1);
                this.$notify.success(response.message)
            })
            .catch(errors => {
                this.$handleError(errors)
            })
            .always()
        }
    }
}
</script>
