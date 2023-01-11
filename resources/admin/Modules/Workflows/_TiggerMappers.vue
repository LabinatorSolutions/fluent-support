<template>
    <div class="fs_trigger_mappers">
        <el-form-item label="Workflow Trigger">
            <el-select v-model="workflow.trigger_key">
                <el-option
                    v-for="(trigger, triggerKey) in trigger_fields.triggers"
                    :value="triggerKey"
                    :key="triggerKey"
                    :label="trigger.title"
                ></el-option>
            </el-select>
        </el-form-item>

        <div
            class="fs_trigger_conditions"
            v-if="workflow.trigger_key && app_ready"
        >
            <h3>{{ translate("Conditions") }}</h3>
            <div
                class="fs_each_cond_group"
                v-for="(conditionGroup, condIndex) in workflow_conditions"
                :key="condIndex"
            >
                <trigger-conditional-group
                    @deleteGroup="deleteGroup(condIndex)"
                    :conditional_fields="conditional_fields"
                    :all_conditions="trigger_fields.conditions"
                    :settings="conditionGroup"
                />
                <p
                    v-if="condIndex + 1 != workflow_conditions.length"
                    class="fs_cond_and"
                >
                    <em>{{ translate("AND") }}</em>
                </p>
            </div>
            <div class="fs_cond_and">
                <em
                    style="cursor: pointer; color: #07c; font-weight: bold"
                    @click="addMore()"
                    ><el-icon><Plus /></el-icon> {{ translate("AND") }}</em
                >
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import each from "lodash/each";
import isArray from "lodash/isArray";
import TriggerConditionalGroup from "./_TriggerConditionalGroup";
import { ref, onMounted, computed } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "TriggerMappers",
    props: ["workflow", "trigger_fields", "workflow_conditions"],
    components: {
        TriggerConditionalGroup,
    },

    setup(props) {
        const { translate } = useFluentHelper();
        const app_ready = ref(false);

        const conditional_fields = computed(() => {
            if (!props.workflow.trigger_key) {
                return [];
            }

            const validKeys =
                props.trigger_fields.triggers[props.workflow.trigger_key]
                    .supported_conditions;

            const validConditions = {};

            each(props.trigger_fields.conditions, (condition, conditionKey) => {
                if (validKeys.indexOf(conditionKey) !== -1) {
                    if (!validConditions[condition.group]) {
                        validConditions[condition.group] = {
                            title: condition.group,
                            children: [],
                        };
                    }
                    condition.condition_key = conditionKey;
                    validConditions[condition.group].children.push(condition);
                }
            });

            return validConditions;
        });

        const addMore = () => {
            props.workflow_conditions.push([
                {
                    data_key: "",
                    data_operator: "",
                    data_value: "",
                },
            ]);
        };

        const deleteGroup = (condIndex) => {
            if (props.workflow_conditions.length > 1) {
                props.workflow_conditions.splice(condIndex, 1);
            }
        };

        onMounted(() => {
            app_ready.value = true;
        });

        return {
            app_ready,
            conditional_fields,
            addMore,
            deleteGroup,
            translate,
        };
    },
};
</script>
