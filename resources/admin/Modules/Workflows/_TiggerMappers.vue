<template>
    <div class="fs_trigger_mappers">
        <el-form-item label="Workflow Trigger">
            <el-select v-model="workflow.trigger_key">
                <el-option v-for="(trigger, triggerKey) in trigger_fields.triggers" :value="triggerKey"
                           :key="triggerKey" :label="trigger.title"></el-option>
            </el-select>
        </el-form-item>

        <div class="fs_trigger_conditions" v-if="workflow.trigger_key && app_ready">
            <h3>Conditions</h3>
            <div class="fs_each_cond_group" v-for="(conditionGroup, condIndex) in workflow_conditions"
                 :key="condIndex">
                <trigger-conditional-group @deleteGroup="deleteGroup(condIndex)"
                                           :conditional_fields="conditional_fields"
                                           :all_conditions="trigger_fields.conditions" :settings="conditionGroup"/>
                <p v-if="(condIndex+1) != workflow_conditions.length" class="fs_cond_and">
                    <em>AND</em>
                </p>
            </div>
            <div class="fs_cond_and">
                <em style="cursor: pointer;color: #07c;font-weight: bold;" @click="addMore()"><i
                    class="el-icon-plus"></i> AND</em>
            </div>
        </div>

    </div>
</template>

<script type="text/babel">
import each from 'lodash/each';
import isArray from 'lodash/isArray';
import TriggerConditionalGroup from './_TriggerConditionalGroup';

export default {
    name: 'TriggerMappers',
    props: ['workflow', 'trigger_fields', 'workflow_conditions'],
    components: {
        TriggerConditionalGroup
    },
    data() {
        return {
            app_ready: false
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
                    if (!validConditions[condition.group]) {
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
    },
    methods: {
        addMore() {
            this.workflow_conditions.push([{
                data_key: '',
                data_operator: '',
                data_value: ''
            }]);
        },
        deleteGroup(condIndex) {
            if (this.workflow_conditions.length > 1) {
                this.workflow_conditions.splice(condIndex, 1);
            }
        }
    },
    mounted() {
        this.app_ready = true;
    }
}
</script>
