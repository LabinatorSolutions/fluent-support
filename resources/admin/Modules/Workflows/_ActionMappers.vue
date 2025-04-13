<template>
    <div class="fs_triggers_wrap">
        <div>
            <draggable
                v-model="actionsParam"
                ghost-class="ghost"
                class="fs_all_triggers"
                item-key="id"
                @end="onDragEnd"
            >
                <template #item="{ element, index }">
                    <div :key="element.id" class="fs_trigger_map">
                        <action-map
                            :dragKey="dragKey"
                            @deleteAction="removeAction(index)"
                            @update="triggerUpdate"
                            :action="element"
                            :activeName="element.activeName"
                            :actions="all_actions"
                        />
                    </div>
                </template>
            </draggable>
        </div>
        <action-adder
            @success="appendAction"
            v-show="show_adder"
            :all_actions="all_actions"
        />
        <el-button
            style="margin-top: 30px"
            type="info"
            @click="show_adder = true"
            v-if="!show_adder"
        >
            {{ translate("Add Another Action") }}
        </el-button>
    </div>
</template>

<script>
import ActionMap from "./_ActionMap.vue";
import draggable from "vuedraggable";
import ActionAdder from "./_ActionAdder.vue";
import { onMounted, ref, watch } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "ActionMappers",
    props: ["workflow_id", "actions", "all_actions"],
    components: {
        ActionMap,
        ActionAdder,
        draggable,
    },
    setup(props, context) {
        const { translate } = useFluentHelper();
        const actionsParam = ref([]);
        const showAdder = ref(false);
        const dragKey = ref(Date.now());

        const appendAction = (action) => {
            showAdder.value = false;
            const newAction = {
                ...action,
                workflow_id: props.workflow_id,
                activeName: `action_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            };
            actionsParam.value.push(newAction);
        };

        const removeAction = (actionIndex) => {
            actionsParam.value.splice(actionIndex, 1);
            context.emit("updateActions", actionsParam.value);
        };

        const onDragEnd = () => {
            dragKey.value = Date.now();
            context.emit("updateActions", actionsParam.value);
        };

        const triggerUpdate = () => {
            context.emit("updateWorkFlow");
        };

        const emitUpdatedActions = () => {
            context.emit("update:actions", actionsParam.value);
        };

        watch(actionsParam, (newVal, oldVal) => {
            if (oldVal && newVal.length === oldVal.length && JSON.stringify(newVal) !== JSON.stringify(oldVal)) {
                const updatedSequence = newVal.map(action => action.id);
                context.emit("updateActionSequence", updatedSequence);
            }

            emitUpdatedActions();
        }, { deep: true });

        onMounted(() => {
            if (!props.actions.length) {
                showAdder.value = true;
            }

            actionsParam.value = JSON.parse(JSON.stringify(props.actions));
        });

        return {
            show_adder: showAdder,
            appendAction,
            removeAction,
            triggerUpdate,
            translate,
            actionsParam,
            dragKey,
            onDragEnd,
        };
    },
};
</script>
