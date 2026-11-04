<template>
    <div class="fs_wf_action_editor">
        <el-form label-position="top">
            <el-form-item :label="$t('Action')" class="fs_form_item">
                <el-select
                    @change="actionTypeChanged()"
                    :placeholder="$t('Select Integration Event')"
                    v-model="action.action_name"
                    filterable
                    class="fs_select_field"
                >
                    <el-option
                        v-for="(actionItem, actionName) in actions"
                        :key="actionName"
                        :value="actionName"
                        :label="actionItem.title"
                    />
                </el-select>
            </el-form-item>
        </el-form>
        <p v-if="action.action_name && !actions[action.action_name]" class="fs_wf_warning">
            {{ $t("This action is not available for the selected trigger. Choose another action or remove it.") }}
        </p>
        <form-builder
            v-if="action.action_name && actions[action.action_name] && builder_ready"
            :fields="actions[action.action_name].fields || []"
            :form-data="action.settings"
        >
            <template v-slot:form_end>
                <el-form-item :label="$t('Action Title')" class="fs_action_title_form_item">
                    <el-input
                        :placeholder="$t('Action Title')"
                        v-model="action.title"
                        class="fs_text_input"
                    ></el-input>
                </el-form-item>
            </template>
        </form-builder>
    </div>
</template>

<script type="text/babel">
import FormBuilder from "../../Pieces/FormElements/_FormBuilder";

export default {
    name: "ActionMap",
    props: ["action", "actions"],
    components: {
        FormBuilder
    },
    data() {
        return {
            builder_ready: true,
        };
    },
    created() {
        if (!this.action.settings) {
            this.action.settings = {};
        }
    },
    methods: {
        actionTypeChanged() {
            const selectedAction = this.actions[this.action.action_name];
            if (!selectedAction) {
                this.action.settings = {};
                return;
            }

            // Remount the form so its fields read the new defaults.
            this.builder_ready = false;
            // PHP sends an empty defaults array as [].
            const defaults = selectedAction.settings_defaults;
            this.action.settings = defaults && !Array.isArray(defaults) ? JSON.parse(JSON.stringify(defaults)) : {};
            this.action.title = selectedAction.title || '';
            this.$nextTick(() => {
                this.builder_ready = true;
            });
        },
    },
};
</script>
