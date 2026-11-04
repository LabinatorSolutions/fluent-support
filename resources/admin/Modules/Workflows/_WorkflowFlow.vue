<template>
    <div class="fs_wf_flow">
        <template v-if="workflow.trigger_type == 'automatic'">
            <div
                class="fs_wf_step"
                :class="{ fs_wf_step_active: selected === 'trigger' }"
                role="button"
                tabindex="0"
                @click="$emit('edit', 'trigger')"
                @keydown.enter.prevent="$emit('edit', 'trigger')"
            >
                <div class="fs_wf_step_label">{{ $t("When") }}</div>
                <div class="fs_wf_step_title">
                    <span class="fs_wf_step_icon fs_wf_step_icon_trigger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </span>
                    <span v-if="trigger">{{ trigger.title }}</span>
                    <span v-else class="fs_wf_muted">{{ $t("Choose a trigger") }}</span>
                </div>
                <div v-if="trigger && trigger.description" class="fs_wf_step_detail">{{ trigger.description }}</div>
            </div>

            <div class="fs_wf_connector"></div>

            <div
                class="fs_wf_step"
                :class="{ fs_wf_step_active: selected === 'conditions' }"
                role="button"
                tabindex="0"
                @click="$emit('edit', 'conditions')"
                @keydown.enter.prevent="$emit('edit', 'conditions')"
            >
                <div class="fs_wf_step_label">{{ $t("Only if") }}</div>
                <div v-if="!conditionLines.length" class="fs_wf_step_detail">
                    {{ $t("Every ticket. Click to add conditions.") }}
                </div>
                <div v-else class="fs_wf_conditions">
                    <template v-for="(group, groupIndex) in conditionLines" :key="groupIndex">
                        <div v-if="groupIndex" class="fs_wf_pill fs_wf_pill_and">{{ $t("AND") }}</div>
                        <div class="fs_wf_condition_group">
                            <template v-for="(line, lineIndex) in group" :key="lineIndex">
                                <div v-if="lineIndex" class="fs_wf_pill">{{ $t("OR") }}</div>
                                <div class="fs_wf_condition_line">{{ line }}</div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <div v-else class="fs_wf_step fs_wf_step_static">
            <div class="fs_wf_step_label">{{ $t("When") }}</div>
            <div class="fs_wf_step_title">
                <span class="fs_wf_step_icon fs_wf_step_icon_trigger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </span>
                <span>{{ $t("An agent runs it") }}</span>
            </div>
            <div class="fs_wf_step_detail">{{ $t("This is a manual workflow. Agents run it from the Workflows menu on a ticket or on selected tickets.") }}</div>
        </div>

        <div class="fs_wf_connector fs_wf_connector_insert">
            <el-button
                class="fs_wf_insert_btn"
                circle
                size="small"
                icon="Plus"
                :aria-label="$t('Add an action here')"
                @click="$emit('insert', 0)"
            />
        </div>

        <div v-if="!actions.length" class="fs_wf_step fs_wf_step_empty" role="button" tabindex="0" @click="$emit('insert', 0)" @keydown.enter.prevent="$emit('insert', 0)">
            <div class="fs_wf_step_label">{{ $t("Then") }}</div>
            <div class="fs_wf_step_detail">{{ $t("No actions yet. Add the first thing this workflow should do.") }}</div>
        </div>

        <draggable
            v-else
            :list="actions"
            item-key="_key"
            handle=".fs_wf_drag_handle"
            ghost-class="fs_wf_ghost"
        >
            <template #item="{ element, index }">
                <div>
                    <div
                        class="fs_wf_step"
                        :class="{ fs_wf_step_active: selected === element._key, fs_wf_step_invalid: !actionFields[element.action_name] }"
                        role="button"
                        tabindex="0"
                        @click="$emit('edit', element._key)"
                        @keydown.enter.prevent="$emit('edit', element._key)"
                    >
                        <div class="fs_wf_step_label">
                            <span
                                class="fs_wf_drag_handle"
                                :title="$t('Drag to reorder')"
                                @click.stop
                            >
                                <DynamicIcon name="DragAndDrop"/>
                            </span>
                            <!-- translators: %d is the position of an action in a workflow (1, 2, 3...) -->
                            {{ $t("Then, step %d", index + 1) }}
                        </div>
                        <div class="fs_wf_step_title">
                            <span>{{ element.title || (actionFields[element.action_name] && actionFields[element.action_name].title) || element.action_name }}</span>
                        </div>
                        <div v-if="!actionFields[element.action_name]" class="fs_wf_step_detail fs_wf_warning">
                            {{ $t("Not available for this trigger") }}
                        </div>
                        <div v-else-if="detail(element)" class="fs_wf_step_detail">{{ detail(element) }}</div>
                    </div>
                    <div class="fs_wf_connector fs_wf_connector_insert">
                        <el-button
                            class="fs_wf_insert_btn"
                            circle
                            size="small"
                            icon="Plus"
                            :aria-label="$t('Add an action here')"
                            @click="$emit('insert', index + 1)"
                        />
                    </div>
                </div>
            </template>
        </draggable>
    </div>
</template>

<script type="text/babel">
import draggable from "vuedraggable";
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import { conditionGroups, actionDetail } from "./workflowSummary";

export default {
    name: "WorkflowFlow",
    components: {
        draggable,
        DynamicIcon
    },
    props: ["workflow", "conditions", "trigger_fields", "actions", "actionFields", "selected"],
    emits: ["edit", "insert"],
    computed: {
        trigger() {
            const triggers = (this.trigger_fields && this.trigger_fields.triggers) || {};
            return triggers[this.workflow.trigger_key] || null;
        },
        conditionLines() {
            return conditionGroups(this.conditions, (this.trigger_fields && this.trigger_fields.conditions) || {});
        }
    },
    methods: {
        detail(action) {
            return actionDetail(action, this.actionFields[action.action_name], this.appVars);
        }
    }
};
</script>
