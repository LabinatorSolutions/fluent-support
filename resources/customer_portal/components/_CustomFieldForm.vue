<template>
    <div class="fs_custom_fields_wrap">
        <div v-if="appReady" class="fs_custom_fields fs_tk_row">
            <template v-for="(field, fieldName) in fields" :key="fieldName">
                <div v-if="isRenderable(field, fieldName)" class="fs_tk_col">
                    <el-form-item :label="field.label" :required="field.required=='yes'">
                        <el-input
                            v-if="field.type == 'text' || field.type == 'number' || field.type == 'textarea'"
                            :type="field.type"
                            :placeholder="field.placeholder"
                            v-model="custom_data[field.slug]"
                        />
                        <el-select :placeholder="field.placeholder" clearable filterable v-else-if="field.type == 'select-one'"
                                   v-model="custom_data[field.slug]">
                            <el-option v-for="option in field.options" :key="option"
                                       :value="option" :label="option"></el-option>
                        </el-select>
                        <el-select :placeholder="field.placeholder" clearable filterable v-else-if="field.type == 'select'"
                                   :filterable="field.filterable"
                                   :multiple="field.multiple"
                                   v-model="custom_data[field.slug]">
                            <el-option v-for="option in field.options" :key="option.id"
                                       :value="option.id" :label="option.title"></el-option>
                        </el-select>
                        <el-radio-group v-else-if="field.type == 'radio'" v-model="custom_data[field.slug]">
                            <el-radio v-for="option in field.options" :key="option"
                                      :value="option" :label="option"></el-radio>
                        </el-radio-group>
                        <el-checkbox-group v-else-if="field.type == 'checkbox'" v-model="custom_data[field.slug]">
                            <el-checkbox v-for="option in field.options" :key="option"
                                         :value="option" :label="option"></el-checkbox>
                        </el-checkbox-group>
                        <error :error="errors.get('custom_data.'+field.slug)"/>
                    </el-form-item>
                </div>
            </template>
        </div>
        <div v-else-if="loading_remote" style="width: 100%; height: 20px" v-loading="true"></div>
    </div>

</template>

<script type="text/babel">
import isEmpty from 'lodash/isEmpty';
import isArray from 'lodash/isArray';
import each from 'lodash/each';
import Errors from '../../admin/Bits/Errors';
import Error from '../../admin/Pieces/Error';


export default {
    name: 'CustomFieldForm',
    props: ['custom_data', 'ticket', 'exceptions'],
    components: {
        Error
    },
    watch: {
        'exceptions': function (newVal, oldVal) {
            this.errors.record(newVal);
        }
    },
    data() {
        return {
            appReady: false,
            fields: this.appVars.custom_fields,
            labelPosition: 'top',
            loading_remote: false,
            formattedProducts: {},
            errors: new Errors()
        }
    },
    methods: {
        fetchRemoteFields() {
            this.loading_remote = true;
            this.$get('custom-fields-rendered')
                .then(response => {
                    this.fields = response.custom_fields_rendered;
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

            each(this.fields, (field, fieldName) => {
                if (field.type == 'checkbox') {
                    if (!this.custom_data[fieldName] || !isArray(this.custom_data[fieldName])) {
                        this.custom_data[fieldName] = [];
                    }
                }
            });

            each(this.appVars.support_products, (product) => {
                this.formattedProducts[product.id] = product.title;
            });

            this.appReady = true;
        },
        isRenderable(field, fieldName) {
            if (field.has_logics != 'yes' || !field.conditions || !field.conditions.length) {
                return true;
            }
            let singlePass = false;
            let allPassed = true;
            each(field.conditions, (condition) => {
                if (this.dependancyPass(condition)) {
                    singlePass = true;
                } else {
                    this.custom_data[field.slug] = [];
                    allPassed = false;
                }
            });

            if (field.match_type == 'yes') {
                return singlePass && allPassed;
            }

            return singlePass;
        },
        /**
         * Helper function for show/hide dependent elements
         & @return {Boolean}
         */
        compare(sourceVal, operator, givenVal) {

            if (typeof sourceVal == 'string') {
                sourceVal = sourceVal.toLowerCase();
            }

            if (typeof givenVal == 'string') {
                givenVal = givenVal.toLowerCase();
            }

            switch (operator) {
                case '=':
                    if (isArray(givenVal)) {
                        return givenVal.indexOf(sourceVal) !== -1;
                    }
                    return sourceVal == givenVal
                case '!=':
                    if (isArray(givenVal)) {
                        return givenVal.indexOf(sourceVal) === -1;
                    }
                    return sourceVal != givenVal
                case 'contains':
                    sourceVal = sourceVal.toString();
                    return givenVal.indexOf(sourceVal) !== -1;
                case 'nt_contains':
                    sourceVal = sourceVal.toString();
                    return givenVal.indexOf(sourceVal) === -1;
                case 'lt':
                    return givenVal < sourceVal;
                case 'gt':
                    return givenVal > sourceVal;
                default:
                    return false
            }
        },

        /**
         * Checks if a prop is dependent on another
         * @param condition
         * @return {boolean}
         */
        dependancyPass(condition) {
            if (condition && condition.item_key && condition.operator) {
                let itemKey = condition.item_key;

                let sourceValue = '';

                if (itemKey.indexOf('ticket_') === 0) { // it's a ticket property
                    itemKey = itemKey.replace('ticket_', '');
                    if (itemKey == 'product_id') {
                        sourceValue = this.formattedProducts[this.ticket[itemKey]];
                    } else {
                        sourceValue = this.ticket[itemKey];
                    }
                } else {
                    sourceValue = this.custom_data[itemKey];
                }

                if (!sourceValue) {
                    sourceValue = '';
                }

                if (this.compare(condition.value, condition.operator, sourceValue)) {
                    return true;
                }

                return false;
            }
            return true;
        }
    },
    mounted() {
        if (isEmpty(this.fields)) {
            return false;
        }

        if (this.appVars.has_pro && this.appVars.has_custom_ajax_fields) {
            this.fetchRemoteFields();
        } else {
            this.initFields();
        }

    }
}
</script>
