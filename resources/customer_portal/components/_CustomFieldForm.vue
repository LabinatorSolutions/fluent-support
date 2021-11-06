<template>
    <div class="fs_custom_fields_wrap">
        <div v-if="appReady" class="fs_custom_fields fs_tk_row">
            <div class="fs_tk_col" v-for="(field, fieldName) in fields" :key="fieldName">
                <el-form-item :label="field.label">
                    <el-input v-if="field.type == 'text' || field.type == 'number' || field.type == 'textarea'"
                              :type="field.type" v-model="custom_data[field.slug]"/>
                    <el-select v-else-if="field.type == 'select-one'" v-model="custom_data[field.slug]">
                        <el-option v-for="option in field.options" :key="option"
                                   :value="option" :label="option"></el-option>
                    </el-select>
                    <el-select v-else-if="field.type == 'select'" :filterable="field.filterable" :multiple="field.multiple"
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
                </el-form-item>
            </div>
        </div>
        <div v-else-if="loading_remote" style="width: 100%; height: 20px" v-loading="true"></div>
    </div>

</template>

<script type="text/babel">
import isEmpty from 'lodash/isEmpty';
import isArray from 'lodash/isArray';
import each from 'lodash/each';

export default {
    name: 'CustomFieldForm',
    props: ['custom_data'],
    data() {
        return {
            appReady: false,
            fields: this.appVars.custom_fields,
            labelPosition: 'top',
            loading_remote: false
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
                    this.loading_remote = true;
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
            this.appReady = true;
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
