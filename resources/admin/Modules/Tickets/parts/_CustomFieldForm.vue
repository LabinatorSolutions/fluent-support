<template>
    <div class="fs_custom_fields">
        <template v-if="appReady">
            <el-row v-if="fields" :gutter="30">
                <el-col v-for="(field, fieldName) in fields" :key="fieldName" :xs="24" :md="12">
                    <el-form :data="formData" :label-position="labelPosition">
                        <el-form-item :label="field.label">
                            <el-input v-if="field.type == 'text' || field.type == 'number' || field.type == 'textarea'"
                                      :type="field.type" v-model="formData[field.slug]"/>
                            <el-select v-else-if="field.type == 'select-one'" v-model="formData[field.slug]">
                                <el-option v-for="option in field.options" :key="option"
                                           :value="option" :label="option"></el-option>
                            </el-select>
                            <el-select v-else-if="field.type == 'select'" :filterable="field.filterable"
                                       :multiple="field.multiple"
                                       v-model="formData[field.slug]">
                                <el-option v-for="option in field.options" :key="option.id"
                                           :value="option.id" :label="option.title"></el-option>
                            </el-select>
                            <el-radio-group v-else-if="field.type == 'radio'" v-model="formData[field.slug]">
                                <el-radio v-for="option in field.options" :key="option"
                                          :value="option" :label="option"></el-radio>
                            </el-radio-group>
                            <el-checkbox-group v-else-if="field.type == 'checkbox'" v-model="formData[field.slug]">
                                <el-checkbox v-for="option in field.options" :key="option"
                                             :value="option" :label="option"></el-checkbox>
                            </el-checkbox-group>
                            <p v-else>Not editable</p>
                        </el-form-item>
                    </el-form>
                </el-col>
            </el-row>
            <el-button v-if="ticket_id" v-loading="saving" :disabled="saving" @click="saveEditedCustomFieldData()"
                       type="primary">
                {{ $t('Save') }}
            </el-button>
        </template>
        <el-skeleton v-else-if="loading_remote" :rows="3" animated/>
    </div>
</template>

<script type="text/babel">
import isEmpty from 'lodash/isEmpty';
import isArray from 'lodash/isArray';
import each from 'lodash/each';

export default {
    name: 'CustomFieldForm',
    props: ['custom_data', 'ticket_id'],
    emits: ['syncData'],
    data() {
        return {
            appReady: false,
            fields: false,
            labelPosition: 'top',
            formData: {},
            saving: false,
            loading_remote: false
        }
    },
    methods: {
        fetchRemoteFields() {
            this.loading_remote = true;
            this.$get('tickets/' + this.ticket_id + '/custom-data', {
                with: ['rendered_fields']
            })
                .then(response => {
                    this.formData = response.custom_data;
                    this.fields = response.rendered_fields;
                    this.initFields();
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.loading_remote = false;
                });
        },
        initFields() {
            if (isEmpty(this.fields)) {
                return false;
            }

            if (!this.ticket_id) {
                this.formData = this.custom_data;
            }

            if (!isEmpty(this.custom_data)) {
                this.formData = this.custom_data;
            }

            if (isEmpty(this.formData)) {
                this.formData = {};
            }

            each(this.fields, (field, fieldName) => {
                if (field.type == 'checkbox') {
                    if (!this.formData[fieldName] || !isArray(this.formData[fieldName])) {
                        this.formData[fieldName] = [];
                    } else if (!this.formData[fieldName]) {
                        this.formData[fieldName] = '';
                    }
                }
            });

            this.appReady = true;
        },
        saveEditedCustomFieldData() {
            this.saving = true;
            this.$post(`ticket-custom-fields/${this.ticket_id}/sync`, {
                custom_fields: this.formData
            })
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });

                    this.$emit('syncData', response.custom_data_rendered);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
    },
    mounted() {
        if (isEmpty(this.appVars.custom_fields)) {
            return false;
        }

        if (this.appVars.has_custom_ajax_fields && this.ticket_id) {
            this.fetchRemoteFields();
        } else {
            this.fields = this.appVars.custom_fields;
            this.initFields();
        }

    }
}
</script>
