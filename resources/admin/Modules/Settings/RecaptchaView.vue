<template>
    <div class="fs_reCaptcha_settings_body" v-if="load">
        <el-row class="setting_header">
            <el-col :md="24">
                <h2>{{ translate("Recaptcha_heading") }}</h2>
            </el-col>
        </el-row>

        <div class="settings-body">
            <el-form label-position="top" label-width="140px">
                <el-switch
                    v-model="reCaptchaEnabled"
                    class="ml-2"
                    style="
                        --el-switch-on-color: #13ce66;
                        --el-switch-off-color: #ff4949;
                    "
                    active-text="Enabled"
                    inactive-text="Disabled"
                    @change="loadRecaptchaResponse"
                />
                <el-form-item
                    :label="translate('Recaptcha Version')"
                    style="margin-right: 20px"
                >
                    <el-radio-group
                        @change="loadRecaptchaResponse"
                        v-model="reCaptchaVersion"
                        style="width: 90%; margin-right: 0.2em"
                    >
                        <el-radio label="recaptcha_v2">{{
                            translate("Version 2")
                        }}</el-radio>
                        <el-radio label="recaptcha_v3">{{
                            $t("Version 3")
                        }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item
                    :label="translate('Use Recaptcha')"
                    @change="loadRecaptchaResponse"
                >
                    <el-checkbox
                        v-model="formContainingReCaptcha.login_form"
                        @change="loadRecaptchaResponse"
                        true-label="yes"
                        false-label="no"
                        name="type"
                        >{{ translate("Login Form") }}</el-checkbox
                    >
                    <el-checkbox
                        v-model="formContainingReCaptcha.signup_form"
                        true-label="yes"
                        false-label="no"
                        name="type"
                        >{{ translate("Signup Form") }}</el-checkbox
                    >
                </el-form-item>

                <el-form-item label="Site Key">
                    <el-input
                        v-model="siteKey"
                        @change="loadRecaptchaResponse"
                    />
                </el-form-item>

                <el-form-item label="Secret Key">
                    <el-input
                        type="password"
                        v-model="secretKey"
                        @change="loadRecaptchaResponse"
                    />
                </el-form-item>

                <el-form-item
                    v-if="'recaptcha_v2' === reCaptchaVersion && siteKey"
                    class="hidden"
                    :label="translate('Validate Captcha')"
                >
                    <div
                        class="g-recaptcha"
                        id="recaptchaContainer"
                        :data-sitekey="siteKey"
                    ></div>
                </el-form-item>

                <el-form-item>
                    <el-button
                        type="success"
                        @click="saveSettings"
                        :disabled="disabled"
                    >
                        {{ translate("Save Settings") }}
                    </el-button>
                    <el-button type="danger" @click="clearSettings">
                        {{ translate("Clear Settings") }}
                    </el-button>
                </el-form-item>
            </el-form>
        </div>
    </div>
</template>

<script>
import { reactive, toRefs, onMounted, ref } from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "RecaptchaView",
    setup() {
        const { get, post, translate, handleError, setTitle } =
            useFluentHelper();
        const { notify } = useNotify();

        const state = reactive({
            reCaptchaVersion: "recaptcha_v2",
            formContainingReCaptcha: {
                login_form: "no",
                signup_form: "no",
            },
            siteKey: "",
            secretKey: "",
            captchaResponse: "",
            reCaptchaEnabled: false,
        });
        const load = ref(false);
        const disabled = ref(true);

        const loadRecaptchaResponse = async () => {
            disabled.value = !validate();

            if ("recaptcha_v3" === state.reCaptchaVersion) {
                loadRecaptchaV3Script();

                window.grecaptcha.ready(() => {
                    window.grecaptcha
                        .execute(state.siteKey, { action: "submit" })
                        .then(function (token) {
                            state.captchaResponse = token;
                            disabled.value = false;
                        });
                });
            } else {
                document.querySelector(".grecaptcha-badge")?.remove();

                let reCaptcha = document.getElementById("recaptchaContainer");
                reCaptcha.innerHTML = "";

                window.___grecaptcha_cfg.clients = {};

                if (window.grecaptcha) {
                    window.grecaptcha.render("recaptchaContainer", {
                        sitekey: state.siteKey,
                        callback: function (token) {
                            state.captchaResponse = token;
                            disabled.value = false;
                        },
                    });
                }
            }
        };

        const loadRecaptchaV3Script = () => {
            if ("recaptcha_v3" === state.reCaptchaVersion) {
                var v3Script = document.createElement("script");
                v3Script.src = `https://www.google.com/recaptcha/api.js?render=${state.siteKey}`;
                document.head.appendChild(v3Script);
            }
        };

        const saveSettings = async () => {
            if (!validate()) {
                return notify({
                    message: "Missing required fields.",
                    type: "error",
                    position: "bottom-right",
                });
            }
            await post("settings/recaptcha-settings", {
                key: "reCaptcha",
                reCaptcha: state,
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                })
                .catch((errors) => {
                    console.log(errors);
                    handleError(errors);
                });
        };

        const fetchSettings = async () => {
            await get("settings/recaptcha-settings")
                .then((response) => {
                    const data = response;
                    console.log(data);
                    if (data) {
                        state.reCaptchaVersion = data.reCaptcha_version;
                        state.siteKey = data.siteKey;
                        state.secretKey = data.secretKey;
                        state.formContainingReCaptcha =
                            data.formContainingReCaptcha;
                        state.reCaptchaEnabled = data.is_enabled === "true";
                    }
                    loadRecaptchaV3Script();
                    load.value = true;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {});
        };

        const validate = () => {
            return !!(state.siteKey && state.secretKey);
        };

        const clearSettings = async () => {
            await post("settings/recaptcha-settings", {
                key: "reCaptcha",
                reCaptcha: "clear-reCaptcha-settings",
            })
                .then((response) => {
                    state.reCaptchaVersion = "";
                    state.siteKey = "";
                    state.secretKey = "";
                    (state.formContainingReCaptcha = {
                        login_form: "no",
                        signup_form: "no",
                    }),
                        notify({
                            message: response.data.message,
                            type: "success",
                            position: "bottom-right",
                        });
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        };

        onMounted(() => {
            setTitle("ReCaptcha Settings");
            var v2Script = document.createElement("script");
            v2Script.src = `https://www.google.com/recaptcha/api.js`;
            document.head.appendChild(v2Script);

            fetchSettings();
        });

        return {
            ...toRefs(state),
            saveSettings,
            clearSettings,
            loadRecaptchaResponse,
            translate,
            load,
            disabled,
            setTitle,
        };
    },
};
</script>
<style>
.fs_reCaptcha_settings_body {
    padding: 30px;
}
</style>
