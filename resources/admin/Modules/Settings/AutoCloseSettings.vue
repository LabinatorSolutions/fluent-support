<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ translate("Auto Close Settings") }}</h3>
            </div>
            <div class="fs_box_actions"></div>
        </div>
        <div class="fs_narrow_promo" v-if="!appVars.has_pro">
            <h3>
                {{
                    translate(
                        "Auto Close tickets based on active days or based on tags or waiting time."
                    )
                }}
            </h3>
            <p>{{ translate("pro_promo") }}</p>
            <a
                target="_blank"
                rel="noopener"
                href="https://fluentsupport.com"
                class="el-button el-button--success"
                >{{ translate("Upgrade To Pro") }}</a
            >
        </div>
        <template v-else>
            <div
                style="padding: 20px"
                v-if="!fetching"
                v-loading="loading"
                class="fs_box_body"
            >
                <div style="margin-bottom: 20px">
                    <el-checkbox
                        v-model="settings.enabled"
                        true-label="yes"
                        false-label="no"
                    >
                        {{ translate("Enable Auto Closing Inactive Tickets") }}
                    </el-checkbox>
                </div>
                <form-builder
                    v-if="app_ready && settings.enabled == 'yes'"
                    :fields="fields"
                    :form-data="settings"
                    label_position="top"
                >
                </form-builder>

                <div style="margin-top: 20px">
                    <el-button
                        size="default"
                        type="success"
                        @click="saveSettings()"
                        >{{ translate("Save Settings") }}</el-button
                    >
                </div>
            </div>
            <div
                style="padding: 20px; background: white"
                class="fs_box_body"
                v-else
            >
                <el-skeleton :rows="5" animated />
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
import FormBuilder from "../../Pieces/FormElements/_FormBuilder";
import { onMounted, reactive, toRefs } from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "AutoCloseSettings",
    components: {
        FormBuilder,
    },
    setup() {
        const { get, post, translate, handleError, setTitle, appVars } =
            useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            settings: {},
            fields: {},
            fetching: false,
            loading: false,
            app_ready: false,
            settings_key: "auto_close_settings",
        });

        const fetchSettings = async () => {
            state.fetching = true;
            await get("settings/auto-close", {
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
        const saveSettings = async () => {
            state.loading = true;
            await post("settings/auto-close", {
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
            if (appVars.has_pro) {
                fetchSettings();
            }
            setTitle("Auto Close Settings");
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings,
            appVars
        }
    },
};
</script>
