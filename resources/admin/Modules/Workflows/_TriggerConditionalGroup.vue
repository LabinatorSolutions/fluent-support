<template>
    <div v-if="app_ready" class="fs_conditional_group">

        <div v-for="(setting, settingIndex) in settings" :key="settingIndex">
            <div class="fs_condition_line">
                <div class="fs_cond_block fc_cond_data">
                    <el-select @change="maybeValueReset(setting, 'data_key')" v-model="setting.data_key">
                        <el-option-group
                            v-for="(group, groupKey) in conditional_fields"
                            :key="groupKey"
                            :label="groupKey"
                        >
                            <el-option
                                v-for="(condition, conditionKey) in group.children"
                                :key="condition.condition_key"
                                :label="condition.title"
                                :value="condition.condition_key"
                            />
                        </el-option-group>
                    </el-select>
                </div>
                <template v-if="setting.data_key && all_conditions[setting.data_key]">
                    <div class="fs_cond_block fc_cond_operator">
                        <el-select @change="maybeValueReset(setting, 'operator')" placeholder="Operator"
                                   v-model="setting.data_operator">
                            <template v-if="all_conditions[setting.data_key].data_type == 'string'">
                                <el-option value="contains" label="Contains"></el-option>
                                <el-option value="not_contains" label="Not contains"></el-option>
                            </template>
                            <template v-if="all_conditions[setting.data_key].data_type == 'single_dropdown'">
                                <el-option value="equal" label="Equal"></el-option>
                                <el-option value="not_equal" label="Not Equal"></el-option>
                            </template>
                            <template
                                v-else-if="all_conditions[setting.data_key].data_type == 'yes_no'">
                                <el-option v-for="(option, optionKey) in all_conditions[setting.data_key].options"
                                           :value="optionKey" :key="optionKey" :label="option"></el-option>
                            </template>

                            <template v-else-if="all_conditions[setting.data_key].data_type == 'date_range'">
                                <el-option value=">" label="Is before"></el-option>
                                <el-option value="<" label="Is after"></el-option>
                                <el-option value="range" label="Is between"></el-option>
                            </template>
                            <template v-else-if="all_conditions[setting.data_key].data_type == 'time_range'">
                                <el-option value="range" label="Is between"></el-option>
                            </template>
                        </el-select>
                    </div>
                    <div v-if="setting.data_operator" class="fs_cond_block fc_cond_value">
                        <el-input v-if="all_conditions[setting.data_key].data_type == 'string'" type="text" v-model="setting.data_value" placeholder="Condition Value" />
                        <el-select v-if="all_conditions[setting.data_key].data_type == 'single_dropdown'" v-model="setting.data_value" >
                            <el-option v-for="(option, optionKey) in all_conditions[setting.data_key].options"
                                       :value="optionKey" :key="optionKey" :label="option"></el-option>
                        </el-select>
                        <template v-else-if="all_conditions[setting.data_key].data_type == 'date_range'">
                            <el-date-picker value-format="YYYY-MM-DD HH:mm" v-if="setting.data_operator == '>' || setting.data_operator == '<'" v-model="setting.data_value" type="datetime" placeholder="Pick a day" />
                            <div v-else-if="setting.data_operator == 'range'" class="fs_range_inputs">
                                <el-date-picker value-format="YYYY-MM-DD HH:mm" v-model="setting.value_1" type="datetime" placeholder="From" />
                                <el-date-picker value-format="YYYY-MM-DD HH:mm" v-model="setting.value_2" type="datetime" placeholder="To" />
                            </div>
                        </template>
                        <template v-else-if="all_conditions[setting.data_key].data_type == 'time_range'">
                            <div v-if="setting.data_operator == 'range'" class="fs_range_inputs">
                                <el-time-select start="00:00"
                                                step="00:10"
                                                end="23:59" v-model="setting.value_1" placeholder="From" />
                                <el-time-select start="00:00"
                                                step="00:10"
                                                end="23:59" v-model="setting.value_2" placeholder="To" />
                            </div>
                        </template>
                    </div>
                </template>
                <div class="fs_cond_block fs_cond_action">
                    <el-button @click="removeCondition(settingIndex)" type="text" icon="el-icon-delete"></el-button>
                </div>
            </div>
            <p v-if="(settingIndex+1) != settings.length" class="fs_cond_or">OR</p>
        </div>

        <div class="fs_add_new_cond text-align-center">
            <el-button @click="addMore()" size="small" type="text" icon="el-icon-plus">OR</el-button>
        </div>

    </div>
</template>

<script type="text/babel">
import isArray from 'lodash/isArray';
export default {
    name: 'TriggerConditionGroup',
    props: ['settings', 'conditional_fields', 'all_conditions'],
    data() {
        return {
            app_ready: false
        }
    },
    methods: {
        addMore() {
            this.settings.push({
                data_key: '',
                data_operator: '',
                data_value: ''
            });
        },
        maybeValueReset(setting, type) {
            if (type === 'data_key') {
                setting.data_operator = '';
                setting.data_value = '';
            } else if (type === 'operator' && setting.data_key && setting.data_operator) {
                if (setting.data_operator === 'range') {
                    setting.value_1 = '';
                    setting.value_2 = '';
                }
            }
        },
        removeCondition(index) {
            if(this.settings.length == 1) {
                this.$emit('deleteGroup');
            } else {
                this.settings.splice(index, 1);
            }
        }
    },
    mounted() {
        if (!this.settings || !this.settings.length) {
            this.settings.push({
                data_key: '',
                data_operator: '',
                data_value: ''
            });
        }

        this.app_ready = true;
    }
}
</script>
