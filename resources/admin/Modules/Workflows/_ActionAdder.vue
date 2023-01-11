<template>
    <div class="fcon_add_trigger">
        <h3>{{ $t("Select Action") }}</h3>
        <ul class="fcon_provider_selectors">
            <el-select @change="actionSuccess()" v-model="action.action_name">
                <el-option
                    v-for="(action, actionName) in all_actions"
                    :value="actionName"
                    :label="action.title"
                ></el-option>
            </el-select>
        </ul>
    </div>
</template>

<script type="text/babel">
import { reactive, computed, toRefs } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "ActionAdder",
    props: ["all_actions"],
    emits: ["success"],
    setup(props, context) {
        const { translate } = useFluentHelper();
        const state = reactive({
            action: {
                title: "",
                action_name: "",
                settings: {},
            },
        });

        const selectedProvider = computed({
            selectedProvider() {
                const selectedProviderKey = state.action.action_provider;
                if (!selectedProviderKey) {
                    return false;
                }

                return props.all_actions[selectedProviderKey];
            },
        });

        const actionSuccess = () => {
            if (state.action.action_name) {
                let defaultSettings = {};
                if (
                    props.all_actions[state.action.action_name]
                        .settings_defaults
                ) {
                    defaultSettings = JSON.parse(
                        JSON.stringify(
                            props.all_actions[state.action.action_name]
                                .settings_defaults
                        )
                    );
                }
                state.action.is_open = true;
                state.action.settings = defaultSettings;
                context.emit("success", state.action);
                setTimeout(() => {
                    state.action = {
                        title: "",
                        action_name: "",
                        settings: {},
                    };
                }, 200);
            }
        };

        return {
            ...toRefs(state),
            selectedProvider,
            actionSuccess,
            translate,
        };
    },
};
</script>
