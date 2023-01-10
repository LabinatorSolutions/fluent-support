<template>
    <div class="fs_license_manager">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("License Management") }}</h3>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-button
                        text
                        icon="Refresh"
                        @click="fetchLicense()"
                    >
                    </el-button>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">
                <div
                    style="background: white"
                    v-loading="verifying"
                    class="fs_narrow_promo text-align-center"
                    :class="'fc_license_' + licenseData.status"
                >
                    <div v-if="licenseData.status == 'expired'">
                        <h3>
                            {{ translate("Looks like your license has been expired") }}
                            {{ licenseData.expires | nsHumanDiffTime }}
                        </h3>
                        <a
                            :href="licenseData.renew_url"
                            target="_blank"
                            class="el-button el-button--danger el-button--small"
                            >{{ translate("Click Here to Renew your License") }}</a
                        >

                        <hr style="margin: 20px 0px" />
                        <p v-if="!showNewLicenseInput">
                            {{ translate("Have a new license Key?") }}
                            <a
                                @click.prevent="
                                    showNewLicenseInput = !showNewLicenseInput
                                "
                                href="#"
                                >{{ translate("Click here") }}</a
                            >
                        </p>
                        <div v-else>
                            <h3>{{ translate("Your License Key") }}</h3>
                            <el-input
                                v-model="licenseKey"
                                placeholder="License Key"
                            >
                                <template #append>
                                    <el-button
                                        @click="verifyLicense()"
                                        icon="Lock"
                                        >{{ translate("Verify License") }}</el-button
                                    >
                                </template>
                            </el-input>
                        </div>
                    </div>
                    <div v-else-if="licenseData.status == 'valid'">
                        <el-icon style="font-size: 50px; text-align: center"
                            ><circle-check
                        /></el-icon>
                        <h2>
                            {{ translate("Your license key is valid and activated") }}
                        </h2>
                        <hr style="margin: 20px 0px" />
                        <p>
                            {{ translate("Want to deactivate this license?") }}
                            <a @click.prevent="deactivateLicense()" href="#">{{
                                translate("Click here")
                            }}</a>
                        </p>
                    </div>
                    <div v-else>
                        <h3>
                            {{
                                translate(
                                    "Please Provide a license key of FluentSupport - Customer Support Plugin for WordPress"
                                )
                            }}
                        </h3>
                        <el-input
                            v-model="licenseKey"
                            placeholder="License Key"
                        >
                            <template #append>
                                <el-button
                                    @click="verifyLicense()"
                                    icon="Lock"
                                    >{{ translate("Verify License") }}</el-button
                                >
                            </template>
                        </el-input>
                        <hr style="margin: 20px 0 30px" />
                        <p v-if="!showNewLicenseInput">
                            {{ translate("dont_have_a_license_key") }}
                            <a
                                target="_blank"
                                :href="licenseData.purchase_url"
                                >{{ translate("Purchase one here") }}</a
                            >
                        </p>
                    </div>
                </div>

                <p
                    class="text-align-center"
                    style="color: red"
                    v-html="errorMessage"
                ></p>
            </div>
            <div
                style="padding: 20px; background: white"
                class="fs_box_body fs_narrow_promo"
                v-else
            >
                <el-skeleton :rows="5" animated />
                <h3>{{ translate("Fetching License Information Please wait") }}</h3>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import { onMounted, reactive, toRefs } from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "LicenseManagement",
    setup() {
        const { get, post, translate, handleError, setTitle, appVars, convertToText } =
            useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            fetching: false,
            licenseData: {},
            verifying: false,
            licenseKey: "",
            showNewLicenseInput: false,
            errorMessage: "",
        });

        const fetchLicense = async () => {
            state.errorMessage = "";
            state.fetching = true;
            await get("pro/license", { verify: true })
                .then((response) => {
                    state.licenseData = response;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };

        const verifyLicense = async () => {
            if (!state.licenseKey) {
                notify({
                    type: "error",
                    message: "Please provide a license key",
                    position: "bottom-right",
                });
                state.errorMessage = "Please provide a license key";
                return;
            }

            state.verifying = true;

            state.errorMessage = "";
            await post("pro/license", {
                license_key: state.licenseKey,
            })
                .then((response) => {
                    state.licenseData = response.license_data;
                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                })
                .catch((errorResponse) => {
                    let errorMessage = "";
                    if (typeof errorResponse === "string") {
                        errorMessage = errorResponse;
                    } else if (errorResponse && errorResponse.message) {
                        errorMessage = errorResponse.message;
                    } else {
                        errorMessage =
                            convertToText(errorResponse);
                    }
                    if (!errorMessage) {
                        errorMessage = "Something is wrong!";
                    }

                    state.errorMessage = errorMessage;

                    handleError(errorResponse);
                })
                .always(() => {
                    state.verifying = false;
                });
        };

        const deactivateLicense = async () => {
            state.verifying = true;
            await post("pro/remove-license")
                .then((response) => {
                    state.licenseData = response.license_data;
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
                    state.verifying = false;
                });
        };

        onMounted(() => {
            fetchLicense();
            setTitle("License Management");
        });

        return {
            ...toRefs(state),
            translate,
            fetchLicense,
            verifyLicense,
            deactivateLicense

        };
    },
};
</script>
