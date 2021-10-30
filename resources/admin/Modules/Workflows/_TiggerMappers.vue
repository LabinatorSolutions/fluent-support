<template>
    <div class="fs_trigger_mappers">
        <el-form-item label="Workflow Trigger">
            <el-select v-model="workflow.trigger_key">
                <el-option v-for="(trigger, triggerKey) in trigger_fields.triggers" :value="triggerKey"
                           :key="triggerKey" :label="trigger.title"></el-option>
            </el-select>
        </el-form-item>

        <div class="fs_trigger_conditions" v-if="workflow.trigger_key">
            <h3>Conditions</h3>
            <div class="fs_each_cond_group" v-for="(conditionGroup, condIndex) in workflow.settings.conditions" :key="condIndex">
                <trigger-conditional-group :conditional_fields="conditional_fields" :all_conditions="trigger_fields.conditions" :settings="conditionGroup" />
            </div>
        </div>

    </div>
</template>

<script type="text/babel">
import each from 'lodash/each';
import TriggerConditionalGroup from './_TriggerConditionalGroup';

export default {
    name: 'TriggerMappers',
    props: ['workflow', 'trigger_fields'],
    components: {
        TriggerConditionalGroup
    },
    data() {
        return {
            settings: []
        }
    },
    computed: {
        conditional_fields() {
            if (!this.workflow.trigger_key) {
                return [];
            }

            const validKeys = this.trigger_fields.triggers[this.workflow.trigger_key].supported_conditions;

            const validConditions = {};

            each(this.trigger_fields.conditions, (condition, conditionKey) => {
                if (validKeys.indexOf(conditionKey) !== -1) {
                    if(!validConditions[condition.group]) {
                        validConditions[condition.group] = {
                            title: condition.group,
                            children: []
                        }
                    }
                    condition.condition_key = conditionKey;
                    validConditions[condition.group].children.push(condition);
                }
            });

            return validConditions;
        }
    }
}
</script>
