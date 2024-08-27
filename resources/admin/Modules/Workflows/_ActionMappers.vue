<template>
    <div class="fcon_triggers_wrap">
        <div
            v-for="(action, actionIndex) in actions"
            :key="actionIndex"
            class="fc_trigger_map"
        >
            <action-map
                @deleteAction="removeAction(actionIndex)"
                @update="triggerUpdate()"
                :action="action"
                :actions="all_actions"
            />
        </div>
        <action-adder
            v-show="show_adder"
            @success="appendAction"
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
import ActionAdder from "./_ActionAdder.vue";
import { ref, onMounted } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "ActionMappers",
    props: ["workflow_id", "actions", "all_actions"],
    components: {
        ActionMap,
        ActionAdder,
    },
    setup(props, context) {
        const { translate } = useFluentHelper();

        const showAdder = ref(false);

        const appendAction = (action) => {
            showAdder.value = false;
            action.workflow_id = props.workflow_id;
            props.actions.push(action);
        };

        const triggerUpdate = () => {
            context.emit("update");
        };

        const removeAction = (actionIndex) => {
            props.actions.splice(actionIndex, 1);
            context.emit("update");
        };

        onMounted(() => {
            if (!props.actions.length) {
                showAdder.value = true;
            }
        });

        return {
            show_adder: showAdder,
            appendAction,
            triggerUpdate,
            removeAction,
            translate,
        };
    },
};
</script>
