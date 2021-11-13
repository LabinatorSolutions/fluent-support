<template>
    <el-form label-position="top" :data="item">
        <el-form-item :label="$t('Field Type')">
            <el-select @change="changeFieldType()" :placeholder="$t('Select Field Type')" v-model="item.field_key">
                <el-option
                    v-for="(fieldType, fieldKey) in field_types"
                    :key="fieldKey"
                    :label="fieldType.label"
                    :value="fieldKey"
                ></el-option>
            </el-select>

            <template v-if="item.type">
                <el-form-item :label="$t('Public Label')">
                    <el-input @keyup.native="maybeSetSlug()" :placeholder="$t('Custom Field Public Label')"
                              v-model="item.label"></el-input>
                </el-form-item>
                <el-form-item :label="$t('Admin Label (Optional)')">
                    <el-input @keyup.native="maybeSetSlug()" :placeholder="$t('Custom Field Admin Label')"
                              v-model="item.admin_label"></el-input>
                </el-form-item>
                <el-form-item :label="$t('Slug (Optional)')">
                    <el-input maxlength="20" :placeholder="$t('Custom Field Slug')" :disabled="form_type == 'update'"
                              v-model="item.slug">
                        <template v-if="form_type == 'new'" #prepend>cf_</template>
                    </el-input>
                    <p v-if="form_type == 'new'">You can not change the slug once save a custom field</p>
                </el-form-item>
                <el-form-item v-if="hasOptions(item.type)" :label="$t('Field Value Options')">
                    <ul class="fluentcrm_option_lists">
                        <li v-for="(optionName, optionIndex) in item.options" :key="optionIndex">
                            {{ optionName }}
                            <i @click="removeOptionItem(optionIndex)" class="fluentcrm_clickable el-icon-close"></i>
                        </li>
                    </ul>
                    <el-input
                        class="input-new-tag"
                        v-if="optionInputVisible"
                        v-model="optionInputValue"
                        ref="saveTagInput"
                        placeholder="type and press enter"
                        size="mini"
                        @keyup.enter.native="handleOptionInputConfirm"
                        @blur="handleOptionInputConfirm"
                    >
                    </el-input>
                    <el-button v-else class="button-new-tag" size="small" @click="showOptionInput">
                        + New Option
                    </el-button>
                </el-form-item>
                <el-form-item style="margin-top: 10px;">
                    <el-checkbox true-label="yes" false-label="no" v-model="item.admin_only">This is an agent only field</el-checkbox>
                </el-form-item>
            </template>
        </el-form-item>
    </el-form>
</template>

<script type="text/babel">
export default {
    name: 'FieldForm',
    props: ['field_types', 'item', 'form_type'],
    data() {
        return {
            optionInputVisible: false,
            optionInputValue: ''
        }
    },
    methods: {
        maybeSetSlug() {
            if (this.form_type != 'new') {
                return false;
            }
            const slug = this.item.label.toLowerCase().replace(/đ/gi, 'd').replace(/\s*$/g, '').replace(/\s+/g, '_').substring(0, 25);
            this.$set(this.item, 'slug', slug);
        },
        changeFieldType() {
            const selectedType = this.item.field_key;
            const field = this.field_types[selectedType];

            this.item.type = field.type;
            this.item.label = '';

            if (this.hasOptions(field.type)) {
                if (!this.item.options) {
                    this.item.options = [
                        'Value Option 1'
                    ];
                }
            } else {
                delete this.item.options;
            }
        },
        hasOptions(type) {
            const optionTypeFields = ['select-one', 'select-multi', 'radio', 'checkbox'];
            return optionTypeFields.indexOf(type) !== -1
        },
        showOptionInput() {
            this.optionInputVisible = true;
            this.$nextTick(_ => {
                this.$refs.saveTagInput.$refs.input.focus();
            });
        },
        handleOptionInputConfirm() {
            const inputValue = this.optionInputValue;
            if (inputValue) {
                this.item.options.push(inputValue);
            }
            this.optionInputVisible = false;
            this.optionInputValue = '';
        },
        removeOptionItem(fieldIndex) {
            this.item.options.splice(fieldIndex, 1);
        }
    },
    mounted() {

    }
}
</script>
