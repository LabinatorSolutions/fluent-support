<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ translate("Global Settings") }}</h3>
            </div>
            <div class="fs_box_actions"></div>
        </div>
        <div
            style="padding: 20px"
            v-if="!fetching"
            v-loading="loading"
            class="fs_box_body"
        >
            <form-builder
                v-if="app_ready"
                :fields="fields"
                :form-data="settings"
                label_position="top"
            >
                <template #form_end>
                    <el-button
                        size="default"
                        type="success"
                        @click="saveSettings()"
                        >{{ translate("Save Settings") }}</el-button
                    >
                </template>
            </form-builder>
        </div>
        <div
            style="padding: 20px; background: white"
            class="fs_box_body"
            v-else
        >
            <el-skeleton :rows="5" animated />
        </div>
    </div>
</template>

<script type="text/babel">
import FormBuilder from "../../Pieces/FormElements/_FormBuilder";
import { onMounted, reactive, toRefs } from "vue";
import { useFluentHelper, useNotify } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "BusinessSettings",
    components: {
        FormBuilder,
    },
    setup() {

        const {
            get,
            post,
            handleError,
            setTitle,
            translate
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            settings: {},
            fields: {},
            fetching: false,
            loading: false,
            app_ready: false,
            settings_key: "global_business_settings",
        });

        const fetchSettings = async() => {
            state.fetching = true;
            await get("settings", {
                settings_key: state.settings_key,
                with: ["fields"],
            })
                .then((response) => {
                    state.settings = response.settings;
                    state.fields = response.fields;
                    state.app_ready = true;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };

        const saveSettings = async() => {
            state.loading = true;
            await post("settings", {
                settings_key: state.settings_key,
                settings: state.settings,
            })
                .then((response) => {
                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        onMounted(() => {
            fetchSettings();
            setTitle("Business Settings");
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings
        }
    },
};
</script>
<style>
.el-form-item__content {
    display: block;
}
</style>
