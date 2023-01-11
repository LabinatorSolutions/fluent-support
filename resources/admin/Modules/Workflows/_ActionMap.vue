<template>
    <div v-loading="loading" class="fcon_trigger_view">
        <div
            @click="action.is_open = !action.is_open"
            class="fcon_provider_info"
        >
            <div class="fc_trigger_titles">
                <span class="fc_trigger_name">{{
                    actions[action.action_name].title
                }}</span>
            </div>
            <div class="fc_trigger_actions">
                <span class="fc_item_open"
                    ><el-icon><ArrowDown /></el-icon
                ></span>
            </div>
        </div>
        <div class="fcon_trigger_details">
            <div v-if="action.is_open" class="fcon_trigger_editor">
                <h3>{{ $t("Action") }}</h3>
                <el-select
                    @change="triggerEventChanged()"
                    :placeholder="$t('Select Integration Event')"
                    v-model="action.action_name"
                >
                    <el-option
                        v-for="(actionItem, triggerName) in actions"
                        :key="triggerName"
                        :value="triggerName"
                        :label="actionItem.title"
                    />
                </el-select>
                <div v-if="action.action_name && builder_ready">
                    <form-builder
                        :fields="actions[action.action_name].fields"
                        :form-data="action.settings"
                    >
                        <template v-slot:form_end>
                            <el-form-item :label="$t('Action Title')">
                                <el-input
                                    type="text"
                                    :placeholder="$t('Action Title')"
                                    v-model="action.title"
                                ></el-input>
                            </el-form-item>
                        </template>
                    </form-builder>

                    <div style="display: block; margin-top: 10px">
                        <el-button @click="emitSave()" type="success">{{
                            $t("Save")
                        }}</el-button>
                        <div
                            style="
                                text-align: right;
                                display: inline-block;
                                float: right;
                            "
                        >
                            <el-popconfirm
                                confirm-button-type="danger"
                                :confirm-button-text="
                                    $t('Yes, Delete this Action')
                                "
                                :cancel-button-text="$t('No')"
                                icon-color="red"
                                @confirm="deleteAction()"
                                :title="$t('delete_action_warning')"
                            >
                                <template #reference>
                                    <el-button
                                        type="danger"
                                        plain
                                        icon="Delete"
                                    ></el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import { ref, onMounted } from "vue";
import FormBuilder from "../../Pieces/FormElements/_FormBuilder";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "ActionMap",
    props: ["action", "actions"],
    components: {
        FormBuilder,
    },
    setup(props, context) {
        const { translate } = useFluentHelper();
        const settingsFields = ref({});
        const loading = ref(false);
        const builderReady = ref(true);

        const triggerEventChanged = () => {
            if (!props.action.action_name) {
                props.action.settings = {};
                return false;
            }
            builderReady.value = false;
            const selectedAction = JSON.parse(
                JSON.stringify(props.actions[props.action.action_name])
            );
            props.action.settings = selectedAction.settings_defaults;
            props.action.title = selectedAction.title;
            setTimeout(() => {
                builderReady.value = true;
            }, 100);
        };

        const emitSave = () => {
            props.action.is_open = !props.action.is_open;
            context.emit("update");
        };

        const deleteAction = () => {
            props.action.is_open = !props.action.is_open;
            context.emit("deleteAction");
        };

        onMounted(() => {
            if (!props.action.settings) {
                props.action.settings = {};
            }
        });

        return {
            settings_fields: settingsFields,
            loading,
            builder_ready: builderReady,
            triggerEventChanged,
            emitSave,
            deleteAction,
            translate,
        };
    },
};
</script>
