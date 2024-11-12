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
                            @update="triggerUpdate()"
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
        const activeName = ref({});
        const dragKey = ref(Date.now()); // Unique key that updates after each drag

        const appendAction = (action) => {
            showAdder.value = false;
            actionsParam.value.push({
                ...action,
                workflow_id: props.workflow_id,
                activeName: `action_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            });
            props.actions.push(action);
        };

        watch(actionsParam, (newVal, oldVal) => {
            if (oldVal && newVal.length === oldVal.length && JSON.stringify(newVal) !== JSON.stringify(oldVal)) {
                const updatedSequence = newVal.map(action => action.id);
                context.emit("updateActionSequence", updatedSequence);
            }
        });

        const triggerUpdate = () => {
            context.emit("updateWorkFlow");
        };

        const removeAction = (actionIndex) => {
            props.actions.splice(actionIndex, 1);
            actionsParam.value.splice(actionIndex, 1);
            context.emit("updateWorkFlow");
        };

        const onDragEnd = () => {
            dragKey.value = Date.now();
        };

        onMounted(() => {
            if (!props.actions.length) {
                showAdder.value = true;
            }
            actionsParam.value = props.actions
        });

        return {
            show_adder: showAdder,
            appendAction,
            triggerUpdate,
            removeAction,
            translate,
            actionsParam,
            activeName,
            dragKey,
            onDragEnd,
        };
    },
};
</script>
