<template>
    <tr>
        <td style="width: 190px">
            {{ ucFirst(itemConfig.provider) }} <span class="fs_provider_separator">/</span> {{ itemConfig.label }}
        </td>
        <td style="width: 190px" class="fs_filter_operator">
            <el-select size="mini" placeholder="Select Operator" @visible-change="maybeOperatorSelected"
                       v-model="item.operator">
                <el-option v-for="(optionLabel,option) in operatorOptions" :key="option" :value="option"
                           :label="optionLabel"></el-option>
            </el-select>
        </td>
        <td class="fs_filter_value">
            <el-input size="mini" v-if="!itemConfig.type || itemConfig.type === 'text'" placeholder="Condition Value"
                      type="text" v-model="item.value"/>
            <template v-if="itemConfig.type === 'selections'">
                <template v-if="itemConfig.component === 'options_selector'">
                    <option-selector v-model="item.value" :field="{
                        is_multiple: itemConfig.is_multiple,
                        size: 'mini',
                        option_key: itemConfig.option_key
                    }"></option-selector>
                </template>
                <template v-else-if="itemConfig.options">
                    <el-select size="mini" :multiple="itemConfig.is_multiple" placeholder="Select Option"
                               v-model="item.value">
                        <el-option v-for="(optionLabel,option) in itemConfig.options" :key="option" :value="option"
                                   :label="optionLabel"></el-option>
                    </el-select>
                </template>
                <pre v-else>{{ itemConfig }}</pre>
            </template>
            <template v-else-if="itemConfig.type === 'dates'">
                <el-input size="mini" v-if="item.operator === 'days_before' || item.operator === 'days_within'"
                          type="number" placeholder="Days" v-model="item.value"/>
                <el-date-picker value-format="YYYY-MM-DD" v-else-if="item.operator" size="mini"
                                v-model="item.value"></el-date-picker>
            </template>
        </td>
        <td style="width: 50px; text-align: right;">
            <el-button @click="removeItem()" type="danger" size="mini" plain icon="el-icon-delete"></el-button>
        </td>
    </tr>
</template>

<script>
import isArray from 'lodash/isArray';
import OptionSelector from '../../../../Pieces/FormElements/_OptionSelector';

export default {
    name: "RichFilterItem",
    props: ['item', 'filterLabels'],
    components: {
        OptionSelector
    },
    data() {
        return {}
    },
    computed: {
        operatorOptions() {
            const type = this.itemConfig.type;
            if (!type || type === 'text') {
                if (this.itemConfig.provider === 'tickets') {
                    return {
                        contains: 'includes',
                        not_contains: 'does not includes'
                    }
                }
                return {
                    '=': 'equal',
                    '!=': 'does not equal',
                    contains: 'includes',
                    not_contains: 'does not includes'
                }
            }
            if (type === 'selections') {

                if(this.itemConfig.is_singular_value) {
                    return {
                        in: 'includes in',
                        not_in: 'not includes in'
                    };
                }

                if (this.itemConfig.is_multiple) {
                    return {
                        in: 'includes in any of',
                        not_in: 'not includes in any',
                        in_all: 'includes all of',
                        not_in_all: 'includes none of'
                    };
                }

                if (this.itemConfig.option_key === 'waiting_for_reply') {
                    return {
                        '=': 'equal',
                        '!=': 'does not equal',
                    }
                }

                return {
                    in: 'includes in',
                    not_in: 'not includes in'
                };
            }
            if (type === 'dates') {
                return {
                    before: 'before',
                    after: 'after',
                    date_equal: 'in the date',
                    days_before: 'before days',
                    days_within: 'within days'
                }
            }
            return {}
        },
        itemConfig() {
            const key = this.item.source.join('-');
            return this.filterLabels[key] || {}
        }
    },
    methods: {
        closingSource(status) {
            if (!status) {
                setTimeout(() => {
                    jQuery(this.$el).find('.fs_filter_operator .el-select').trigger('click');
                }, 300);
            }
        },
        maybeOperatorSelected(status) {
            if (!status && this.item.operator) {
                if (this.itemConfig.type == 'dates') {
                    this.item.value = '';
                }
                setTimeout(() => {
                    jQuery(this.$el).find('.fs_filter_value input').focus();
                }, 200);
            }
        },
        removeItem() {
            this.$emit('removeItem');
        }
    },
    mounted() {
        if (this.itemConfig.is_multiple && !isArray(this.item.value)) {
            this.item.value = [];
        }
        if (!this.item.operator) {
            const objectValues = Object.keys(this.operatorOptions);
            if (objectValues.length) {
                this.item.operator = objectValues[0];
                jQuery(this.$el).find('.fs_filter_operator .el-select').trigger('click');
            }
        }
    }
}
</script>

<style scoped>

</style>
