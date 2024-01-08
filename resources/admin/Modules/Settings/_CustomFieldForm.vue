<template>
    <el-form label-position="top" :data="item">
        <el-form-item :label="translate('Field Type')">
            <el-select @change="changeFieldType()" :placeholder="translate('Select Field Type')" v-model="item.field_key">
                <el-option
                    v-for="(fieldType, fieldKey) in field_types"
                    :key="fieldKey"
                    :label="fieldType.label"
                    :value="fieldKey"
                ></el-option>
            </el-select>
        </el-form-item>
        <template v-if="item.type">
            <el-row :gutter="30">
                <el-col :sm="12" :xs="24">
                    <el-form-item :label="translate('Public Label')">
                        <el-input @keyup.native="maybeSetSlug()" :placeholder="translate('Custom Field Public Label')"
                                  v-model="item.label"></el-input>
                    </el-form-item>
                </el-col>
                <el-col :sm="12" :xs="24">
                    <el-form-item :label="translate('Admin Label (Optional)')">
                        <el-input @keyup.native="maybeSetSlug()" :placeholder="translate('Custom Field Admin Label')"
                                  v-model="item.admin_label"></el-input>
                    </el-form-item>
                </el-col>
            </el-row>
            <el-row :gutter="30">
                <el-col :sm="12" :xs="24">
                    <el-form-item :label="translate('Slug (Optional)')">
                        <el-input maxlength="20" :placeholder="translate('Custom Field Slug')" :disabled="form_type == 'update'"
                                  v-model="item.slug">
                            <template v-if="form_type == 'new'" #prepend>cf_</template>
                        </el-input>
                        <p v-if="form_type == 'new'">{{translate('You can not change the slug once save a custom field')}}</p>
                    </el-form-item>
                </el-col>
                <el-col :sm="12" :xs="24">
                    <el-form-item :label="translate('Placeholder')">
                        <el-input :placeholder="translate('Field Placeholder')" v-model="item.placeholder" />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item v-if="hasOptions(item.type)" :label="translate('Field Value Options')">
                <ul class="fluentcrm_option_lists">
                    <li v-for="(optionName, optionIndex) in item.options" :key="optionIndex">
                        <el-input :placeholder="translate('Option Value')" v-model="item.options[optionIndex]" type="text">
                            <template #suffix>
                                <i @click="removeOptionItem(optionIndex)" class="fluentcrm_clickable el-icon-close"></i>
                            </template>
                        </el-input>
                    </li>
                </ul>
                <el-input
                    class="input-new-tag"
                    v-if="optionInputVisible"
                    v-model="optionInputValue"
                    ref="saveTagInput"
                    :placeholder="translate('type and press enter')"
                    @keyup.enter.native="handleOptionInputConfirm"
                    @blur="handleOptionInputConfirm"
                >
                </el-input>
                <el-button v-else class="button-new-tag" size="small" @click="showOptionInput">
                    + {{translate('New Option')}}
                </el-button>
            </el-form-item>

            <el-row style="margin-top: 10px;" :gutter="30">
                <el-col :sm="12" :xs="24">
                    <el-form-item>
                        <el-checkbox true-label="yes" false-label="no" v-model="item.admin_only">
                            {{translate('This is an agent only field')}}
                        </el-checkbox>
                    </el-form-item>
                </el-col>
                <el-col :sm="12" :xs="24">
                    <el-form-item>
                        <el-checkbox @change="initConditions(item)" v-model="item.has_logics" true-label="yes"
                                     false-label="no">{{translate('Enable Conditional Logics')}}
                        </el-checkbox>
                    </el-form-item>
                </el-col>
                <el-col :sm="12" :xs="24">
                    <el-form-item>
                        <el-checkbox @change="initConditions(item)" v-model="item.required" true-label="yes"
                                     false-label="no">{{translate('Required')}}
                        </el-checkbox>
                    </el-form-item>
                </el-col>
            </el-row>

            <template v-if="item.has_logics == 'yes' && item.conditions">
                <table style="margin-bottom: 20px;" class="fs_table fs_stripe">
                    <thead>
                    <tr>
                        <th>{{translate('Field')}}</th>
                        <th>{{translate('Operator')}}</th>
                        <th>{{translate('Match Value')}}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(condition,conditionIndex) in item.conditions" :key="conditionIndex">
                        <td>
                            <el-select filterable v-model="condition.item_key" :placeholder="translate('Select Field')">
                                <el-option
                                    v-for="(field, fieldKey) in keyedFields"
                                    :key="field.slug" :value="field.slug"
                                    :disabled="field.slug == item.slug"
                                    :label="field.admin_label || field.label"></el-option>
                            </el-select>
                        </td>
                        <td>
                            <el-select v-if="condition.item_key && keyedFields[condition.item_key]" filterable
                                       v-model="condition.operator"
                                       :placeholder="translate('Select Field')">
                                <el-option :label="translate('Equal')" value="="/>
                                <el-option :label="translate('Not Equal')" value="!="/>
                                <template v-if="keyedFields[condition.item_key].type == 'number'">
                                    <el-option :label="translate('Less than')" value="lt"/>
                                    <el-option :label="translate('Greater Than')" value="gt"/>
                                </template>
                                <template v-else-if="!keyedFields[condition.item_key].options">
                                    <el-option :label="translate('Contains')" value="contains"/>
                                    <el-option :label="translate('Not Contains')" value="not_contains"/>
                                </template>
                            </el-select>
                        </td>
                        <td>
                            <template
                                v-if="condition.operator && condition.item_key && keyedFields[condition.item_key]">
                                <el-select v-if="keyedFields[condition.item_key].options" v-model="condition.value"
                                           :placeholder="translate('Select Value')">
                                    <el-option v-for="option in keyedFields[condition.item_key].options" :key="option"
                                               :label="option" :value="option"></el-option>
                                </el-select>
                                <el-input v-else
                                          :type="(keyedFields[condition.item_key].type == 'number') ? 'number' : 'text'"
                                          v-model="condition.value" :placeholder="translate('Type Compare Value')"/>
                            </template>
                        </td>
                        <td>
                            <el-button @click="addCondition(conditionIndex)" size="small" type="success"
                                       icon="Plus"/>
                            <el-button @click="removeCondition(conditionIndex)" :disabled="item.conditions.length == 1"
                                       size="small" type="danger"
                                       icon="Delete"/>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <el-form-item :label="translate('Condition Match Type')">
                    <el-radio-group v-model="item.match_type">
                        <el-radio :label="translate('all')">{{translate('Match all conditions')}}</el-radio>
                        <el-radio :label="translate('any')">{{translate('Match any conditions')}}</el-radio>
                    </el-radio-group>
                </el-form-item>
            </template>
        </template>
    </el-form>
</template>

<script type="text/babel">
import each from 'lodash/each';
import { reactive, toRefs, computed, nextTick, ref } from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'FieldForm',
    props: ['field_types', 'item', 'form_type', 'fields'],

    setup(props) {
        const { translate, appVars } =
            useFluentHelper();
        const saveTagInput = ref('');
        const state = reactive({
            optionInputVisible: false,
            optionInputValue: ''
        })

        const keyedFields = computed(() => {
            const supportProducts = [];
            each(appVars.support_products, (product) => {
                supportProducts.push(product.title);
            });
            const formattedFields = {
                ticket_title: {
                    label: 'Ticket Title',
                    slug: 'ticket_title',
                    type: 'text'
                },
                ticket_content: {
                    label: 'Ticket Content',
                    slug: 'ticket_content',
                    type: 'text'
                },
                ticket_client_priority: {
                    label: 'Ticket Client Priority',
                    slug: 'ticket_client_priority',
                    type: 'select-one',
                    options: Object.keys(appVars.client_priorities)
                },
                ticket_product_id: {
                    label: 'Selected Product or Service',
                    slug: 'ticket_product_id',
                    type: 'select-one',
                    options: supportProducts
                }
            };
            each(props.fields, (field) => {
                formattedFields[field.slug] = field;
            });

            return formattedFields;
        });

        const maybeSetSlug = () => {
            if (props.form_type != 'new') {
                return false;
            }
            const slug = props.item.label.toLowerCase().replace(/đ/gi, 'd').replace(/\s*$/g, '').replace(/\s+/g, '_').substring(0, 25);
            props.item['slug'] = slug;
        };

        const changeFieldType = () => {
            const selectedType = props.item.field_key;
            const field = props.field_types[selectedType];

            props.item.type = field.type;
            props.item.label = '';

            if (hasOptions(field.type)) {
                if (!props.item.options) {
                    props.item.options = [
                        'Value Option 1'
                    ];
                }
            } else {
                delete props.item.options;
            }
        };

        const hasOptions = (type) => {
            const optionTypeFields = ['select-one', 'select-multi', 'radio', 'checkbox'];
            return optionTypeFields.indexOf(type) !== -1
        };

        const showOptionInput = () => {
            state.optionInputVisible = true;
            nextTick(() => {
                saveTagInput.value.focus();
            });
        };

        const handleOptionInputConfirm = () => {
            const inputValue = state.optionInputValue;
            if (inputValue) {
                props.item.options.push(inputValue);
            }
            state.optionInputVisible = false;
            state.optionInputValue = '';
        };

        const removeOptionItem = (fieldIndex) => {
            props.item.options.splice(fieldIndex, 1);
        };

        const initConditions = (item) => {
            if (!item.conditions || !item.conditions.length) {
                item.conditions = [
                    {
                        item_key: '',
                        operator: '=',
                        value: ''
                    }
                ];
            }

            if (!item.match_type) {
                item.match_type = 'all';
            }
        };

        const addCondition = (conditionIndex) => {
            props.item.conditions.splice(conditionIndex + 1, 0, {
                item_key: '',
                operator: '=',
                value: ''
            });
        };

        const removeCondition = (conditionIndex) => {
            props.item.conditions.splice(conditionIndex, 1);
        };

        return {
            ...toRefs(state),
            maybeSetSlug,
            changeFieldType,
            hasOptions,
            showOptionInput,
            handleOptionInputConfirm,
            removeOptionItem,
            initConditions,
            addCondition,
            removeCondition,
            translate,
            keyedFields,
            saveTagInput,
        }
    }
}
</script>
