<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ translate("Ticket Form Settings") }}</h3>
            </div>
            <div class="fs_box_actions"></div>
        </div>
        <template v-if="has_pro">
            <div style="padding: 20px" v-if="!fetching" class="fs_box_body">
                <form-builder :fields="settings_fields" :form-data="settings" />
                <div style="display: block">
                    <el-button
                        @click="saveSettings()"
                        :disabled="saving"
                        v-loading="saving"
                        type="success"
                    >
                        {{ translate("Save Settings") }}
                    </el-button>
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
        <div class="fs_narrow_promo" v-else>
            <h3>{{ translate("ticket_form_notice_for_pro") }}</h3>
            <p>{{ translate("pro_promo") }}</p>
            <a
                target="_blank"
                rel="noopener"
                href="https://fluentsupport.com"
                class="el-button el-button--success"
                >{{ translate("Upgrade To Pro") }}</a
            >
        </div>
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
    name: "TicketFormConfig",
    components: {
        FormBuilder,
    },
    setup() {
        const { get, post, handleError, has_pro, setTitle, translate } =
            useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            settings: {},
            fetching: false,
            settings_fields: {},
            saving: false,
            settings_key: "ticket_form_settings",
        });

        const fetchSettings = () => {
            state.fetching = true;
            get("pro/form-settings", {
                with: ["fields"],
            })
                .then((response) => {
                    state.settings = response.settings;
                    state.settings_fields = response.settings_fields;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };

        const saveSettings = () => {
            state.saving = true;
            post("pro/form-settings", {
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
                    state.saving = false;
                });
        };

        onMounted(() => {
            if (has_pro) {
                fetchSettings();
            }
            setTitle("Ticket Form Config");
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            saveSettings,
        };
    },
};
</script>
