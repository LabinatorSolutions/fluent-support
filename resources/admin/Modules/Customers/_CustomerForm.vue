<template>
    <div v-loading="loading" class="ffc_customer_form">
        <h3>Basic Info</h3>
        <form-builder :fields="basic_fields" :form-data="customer"/>
        <h3>Address Info</h3>
        <form-builder :fields="address_fields" :form-data="customer"/>

        <el-button @click="updateCustomer()" type="success" v-if="customer.id">{{ $t('Update Customer') }}</el-button>
        <el-button @click="createCustomer()" type="success" v-else>{{ $t('Create Customer') }}</el-button>
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
            basic_fields: {
                first_name: {
                    label: this.$t('First Name'),
                    data_type: 'text',
                    placeholder: this.$t('First Name'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                last_name: {
                    label: this.$t('Last Name'),
                    data_type: 'text',
                    placeholder: this.$t('Last Name'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field'
                },
                email: {
                    label: this.$t('Email'),
                    data_type: 'email',
                    placeholder: this.$t('Email'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                    disabled: !!this.customer.id
                },
                title: {
                    label: this.$t('Job Title'),
                    data_type: 'text',
                    placeholder: this.$t('Job Title'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field'
                },
                note: {
                    label: this.$t('Note'),
                    data_type: 'textarea',
                    placeholder: this.$t('Note'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field'
                },
                status: {
                    label: this.$t('Status'),
                    data_type: 'text',
                    type: 'input-radio',
                    options: {
                        'active': {'id': 'active', 'value': 'active', 'label': 'Active'},
                        'inactive': {'id': 'inactive', 'value': 'inactive', 'label': 'Inactive'}
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
                    html: 'If you select <b>Inactive</b> status then this customer can not submit a ticket or any response'
                },
            },
            address_fields: {
                address_line_1: {
                    label: this.$t('Address Line 1'),
                    data_type: 'text',
                    placeholder: this.$t('Address Line 1'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                address_line_2: {
                    label: this.$t('Address Line 2'),
                    data_type: 'text',
                    placeholder: this.$t('Address Line 2'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                city: {
                    label: this.$t('Address Line 2'),
                    data_type: 'text',
                    placeholder: this.$t('Address Line 2'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                state: {
                    label: this.$t('State'),
                    data_type: 'text',
                    placeholder: this.$t('State'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                zip: {
                    label: this.$t('Zip Code'),
                    data_type: 'text',
                    placeholder: this.$t('Zip Code'),
                    type: 'input-text',
                    wrapper_class: 'fs_half_field',
                },
                country: {
                    label: this.$t('Country'),
                    placeholder: this.$t('Country'),
                    type: 'country-selector',
                    wrapper_class: 'fs_half_field',
                }
            },
            loading: false
        }
    },
    methods: {
        updateCustomer() {
            this.loading = true;
            this.$put(`customers/${this.customer.id}`, this.customer)
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
            this.$post('customers', this.customer)
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
