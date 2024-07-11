<template>
    <div class="fcon_triggers_wrap">
        <div>
            <draggable
                v-model="actionsParam"
                item-key="id"
            >
                <template #item="{ element, index }">
                    <div :key="element.id" class="fs_trigger_map">
                        <action-map
                            @deleteAction="removeAction(index)"
                            @update="triggerUpdate()"
                            :action="element"
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
    props: ["workflow_id", "actions", "all_actions", "actionSequence"],
    components: {
        ActionMap,
        ActionAdder,
        draggable,
    },
    setup(props, context) {
        const { translate } = useFluentHelper();

        const actionsParam = ref([]);
        const showAdder = ref(false);

        const appendAction = (action) => {
            showAdder.value = false;
            action.workflow_id = props.workflow_id;
            actionsParam.value.push(action);
            props.actions.push(action);
        };

        watch(actionsParam, (newVal) => {
            const updatedSequence = newVal.map(action => action.id);
            context.emit("updateActionSequence", updatedSequence);
        });

        const triggerUpdate = () => {
            context.emit("updateWorkFlow");
        };

        const removeAction = (actionIndex) => {
            props.actions.splice(actionIndex, 1);
            actionsParam.value.splice(actionIndex, 1);
            context.emit("updateWorkFlow");
        };

        onMounted(() => {
            if (!props.actions.length) {
                showAdder.value = true;
            }
            actionsParam.value = Object.values(props.actions).map((action) => ({
                id: action.id,
                title: action.title,
                action_name: action.action_name,
                workflow_id: action.workflow_id,
                settings: action.settings,
            }));
        });

        return {
            show_adder: showAdder,
            appendAction,
            triggerUpdate,
            removeAction,
            translate,
            actionsParam,
        };
    },
};
</script>

<style scoped>
</style>
