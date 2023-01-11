<template>
    <div v-if="app_ready" class="fs_conditional_group">
        <div v-for="(setting, settingIndex) in settings" :key="settingIndex">
            <div class="fs_condition_line">
                <div class="fs_cond_block fc_cond_data">
                    <el-select
                        @change="maybeValueReset(setting, 'data_key')"
                        v-model="setting.data_key"
                    >
                        <el-option-group
                            v-for="(group, groupKey) in conditional_fields"
                            :key="groupKey"
                            :label="groupKey"
                        >
                            <el-option
                                v-for="(
                                    condition, conditionKey
                                ) in group.children"
                                :key="condition.condition_key"
                                :label="condition.title"
                                :value="condition.condition_key"
                            />
                        </el-option-group>
                    </el-select>
                </div>
                <template
                    v-if="setting.data_key && all_conditions[setting.data_key]"
                >
                    <div class="fs_cond_block fc_cond_operator">
                        <el-select
                            @change="maybeValueReset(setting, 'operator')"
                            :placeholder="translate('Operator')"
                            v-model="setting.data_operator"
                        >
                            <template
                                v-if="
                                    all_conditions[setting.data_key]
                                        .data_type == 'string'
                                "
                            >
                                <el-option
                                    value="contains"
                                    :label="translate('Contains')"
                                ></el-option>
                                <el-option
                                    value="not_contains"
                                    :label="translate('Not contains')"
                                ></el-option>
                            </template>
                            <template
                                v-if="
                                    all_conditions[setting.data_key]
                                        .data_type == 'single_dropdown'
                                "
                            >
                                <el-option
                                    value="equal"
                                    :label="translate('Equal')"
                                ></el-option>
                                <el-option
                                    value="not_equal"
                                    :label="translate('Not Equal')"
                                ></el-option>
                            </template>

                            <template
                                v-if="
                                    all_conditions[setting.data_key]
                                        .data_type == 'multiple_select'
                                "
                            >
                                <el-option
                                    value="includes_in"
                                    :label="translate('Includes In')"
                                ></el-option>
                                <el-option
                                    value="not_includes_in"
                                    :label="translate('Not Includes In')"
                                ></el-option>
                            </template>

                            <template
                                v-else-if="
                                    all_conditions[setting.data_key]
                                        .data_type == 'yes_no'
                                "
                            >
                                <el-option
                                    v-for="(
                                        option, optionKey
                                    ) in all_conditions[setting.data_key]
                                        .options"
                                    :value="optionKey"
                                    :key="optionKey"
                                    :label="option"
                                ></el-option>
                            </template>

                            <template
                                v-else-if="
                                    all_conditions[setting.data_key]
                                        .data_type == 'date_range'
                                "
                            >
                                <el-option
                                    value="<"
                                    :label="translate('Is before')"
                                ></el-option>
                                <el-option
                                    value=">"
                                    :label="translate('Is after')"
                                ></el-option>
                                <el-option
                                    value="range"
                                    :label="translate('Is between')"
                                ></el-option>
                            </template>
                            <template
                                v-else-if="
                                    all_conditions[setting.data_key]
                                        .data_type == 'time_range'
                                "
                            >
                                <el-option
                                    value="range"
                                    :label="translate('Is between')"
                                ></el-option>
                            </template>
                        </el-select>
                    </div>
                    <div
                        v-if="setting.data_operator"
                        class="fs_cond_block fc_cond_value"
                    >
                        <el-input
                            v-if="
                                all_conditions[setting.data_key].data_type ==
                                'string'
                            "
                            type="text"
                            v-model="setting.data_value"
                            placeholder="Condition Value"
                        />
                        <el-select
                            v-if="
                                all_conditions[setting.data_key].data_type ==
                                'single_dropdown'
                            "
                            v-model="setting.data_value"
                        >
                            <el-option
                                v-for="(option, optionKey) in all_conditions[
                                    setting.data_key
                                ].options"
                                :value="optionKey"
                                :key="optionKey"
                                :label="option"
                            ></el-option>
                        </el-select>
                        <el-select
                            v-if="
                                all_conditions[setting.data_key].data_type ==
                                'multiple_select'
                            "
                            v-model="setting.data_value"
                            multiple
                            collapse-tags
                        >
                            <el-option
                                v-for="(option, optionKey) in all_conditions[
                                    setting.data_key
                                ].options"
                                :value="optionKey"
                                :key="optionKey"
                                :label="option"
                            ></el-option>
                        </el-select>
                        <template
                            v-else-if="
                                all_conditions[setting.data_key].data_type ==
                                'date_range'
                            "
                        >
                            <el-date-picker
                                value-format="YYYY-MM-DD HH:mm"
                                v-if="
                                    setting.data_operator == '>' ||
                                    setting.data_operator == '<'
                                "
                                v-model="setting.data_value"
                                type="datetime"
                                :placeholder="translate('Pick a day')"
                            />
                            <div
                                v-else-if="setting.data_operator == 'range'"
                                class="fs_range_inputs"
                            >
                                <el-date-picker
                                    value-format="YYYY-MM-DD HH:mm"
                                    v-model="setting.value_1"
                                    type="datetime"
                                    :placeholder="translate('From')"
                                />
                                <el-date-picker
                                    value-format="YYYY-MM-DD HH:mm"
                                    v-model="setting.value_2"
                                    type="datetime"
                                    :placeholder="translate('To')"
                                />
                            </div>
                        </template>
                        <template
                            v-else-if="
                                all_conditions[setting.data_key].data_type ==
                                'time_range'
                            "
                        >
                            <div
                                v-if="setting.data_operator == 'range'"
                                class="fs_range_inputs"
                            >
                                <el-time-select
                                    start="00:00"
                                    step="00:10"
                                    end="23:59"
                                    v-model="setting.value_1"
                                    :placeholder="translate('From')"
                                />
                                <el-time-select
                                    start="00:00"
                                    step="00:10"
                                    end="23:59"
                                    v-model="setting.value_2"
                                    :placeholder="translate('To')"
                                />
                            </div>
                        </template>
                    </div>
                </template>
                <div class="fs_cond_block fs_cond_action">
                    <el-button
                        @click="removeCondition(settingIndex)"
                        type="text"
                        icon="Delete"
                        style="color: #ff0000"
                    ></el-button>
                </div>
            </div>
            <p v-if="settingIndex + 1 != settings.length" class="fs_cond_or">
                {{ translate("OR") }}
            </p>
        </div>

        <div class="fs_add_new_cond text-align-center">
            <el-button
                @click="addMore()"
                size="small"
                type="text"
                icon="Plus"
                >{{ translate("OR") }}</el-button
            >
        </div>
    </div>
</template>

<script type="text/babel">
import isArray from "lodash/isArray";
import { ref, onMounted } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "TriggerConditionGroup",
    props: ["settings", "conditional_fields", "all_conditions"],
    setup(props, context) {
        const { translate } = useFluentHelper();
        const app_ready = ref(false);

        const addMore = () => {
            props.settings.push({
                data_key: "",
                data_operator: "",
                data_value: "",
            });
        };

        const maybeValueReset = (setting, type) => {
            if (type === "data_key") {
                setting.data_operator = "";
                setting.data_value = "";
            } else if (
                type === "operator" &&
                setting.data_key &&
                setting.data_operator
            ) {
                if (setting.data_operator === "range") {
                    setting.value_1 = "";
                    setting.value_2 = "";
                }
            }
        };

        const removeCondition = (index) => {
            if (this.settings.length == 1) {
                context.emit("deleteGroup");
            } else {
                props.settings.splice(index, 1);
            }
        };

        onMounted(() => {
            if (!props.settings || !props.settings.length) {
                props.settings.push({
                    data_key: "",
                    data_operator: "",
                    data_value: "",
                });
            }

            app_ready.value = true;
        });

        return {
            app_ready,
            addMore,
            maybeValueReset,
            removeCondition,
            translate,
        };
    },
};
</script>
