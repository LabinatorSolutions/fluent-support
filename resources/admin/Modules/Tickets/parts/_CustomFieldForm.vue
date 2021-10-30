<template>
    <div v-if="appReady" class="fs_custom_fields">
        <el-row :gutter="30">
            <el-col v-for="(field, fieldName) in fields" :key="fieldName" :xs="24" :md="12">
                <el-form :label-position="labelPosition">
                    <el-form-item :label="field.label">
                        <el-input v-if="field.type == 'text' || field.type == 'number' || field.type == 'textarea'" :type="field.type" v-model="custom_data[field.slug]" />
                        <el-select v-else-if="field.type == 'select-one'" v-model="custom_data[field.slug]">
                            <el-option v-for="option in field.options" :key="option"
                                       :value="option" :label="option"></el-option>
                        </el-select>
                        <el-radio-group v-else-if="field.type == 'radio'" v-model="custom_data[field.slug]">
                            <el-radio v-for="option in field.options" :key="option"
                                      :value="option" :label="option"></el-radio>
                        </el-radio-group>
                        <el-checkbox-group v-else-if="field.type == 'checkbox'" v-model="custom_data[field.slug]">
                            <el-checkbox v-for="option in field.options" :key="option"
                                      :value="option" :label="option"></el-checkbox>
                        </el-checkbox-group>
                        <pre v-else>{{field}}</pre>
                    </el-form-item>
                </el-form>
            </el-col>
        </el-row>
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
            labelPosition: 'top'
        }
    },
    mounted() {
        if (isEmpty(this.fields)) {
            return false;
        }

        each(this.fields, (field, fieldName) => {
            if(field.type == 'checkbox') {
                if(!this.custom_data[fieldName] || !isArray(this.custom_data[fieldName])) {
                    this.custom_data[fieldName] = [];
                }
            }
        });

        this.appReady = true;
    }
}
</script>
