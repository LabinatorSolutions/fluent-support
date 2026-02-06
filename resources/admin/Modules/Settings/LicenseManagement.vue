<template>
    <div class="fs_license_manager">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t("License Management") }}</h3>
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
                    v-loading="verifying"
                    :class="'fs_license_' + licenseData.status"
                >
                    <div v-if="licenseData.status == 'expired'">
                        <h3>
                            {{ $t("Looks like your license has been expired") }}
                            {{ licenseData.expires | nsHumanDiffTime }}
                        </h3>
                        <a
                            :href="licenseData.renew_url"
                            target="_blank"
                            class="el-button el-button--danger el-button--small"
                            >{{ $t("Click Here to Renew your License") }}</a
                        >

                        <hr style="margin: 20px 0px" />
                        <p v-if="!showNewLicenseInput">
                            {{ $t("Have a new license Key?") }}
                            <a
                                @click.prevent="
                                    showNewLicenseInput = !showNewLicenseInput
                                "
                                href="#"
                                >{{ $t("Click here") }}</a
                            >
                        </p>
                        <div v-else>
                            <h3>{{ $t("Your License Key") }}</h3>
                            <el-input
                                v-model="licenseKey"
                                :placeholder="$t('License Key')"
                            >
                                <template #append>
                                    <el-button
                                        @click="verifyLicense()"
                                        icon="Lock"
                                        >{{ $t("Verify License") }}</el-button
                                    >
                                </template>
                            </el-input>
                        </div>
                    </div>
                    <div v-else-if="licenseData.status == 'valid'">
                        <el-icon
                            class="fs_text-center fs_activated_icon"
                            ><circle-check
                        /></el-icon>
                        <h2 class="fs_license_form_title">
                            {{ $t("Your license key is valid and activated") }}
                        </h2>

                        <p class="fs_deactivate_license_text">
                            {{ $t("Want to deactivate this license?") }}
                            <a @click.prevent="deactivateLicense()" href="#" class="fs_license_purchase_link">{{
                                $t("Click here")
                            }}</a>
                        </p>
                    </div>
                    <div v-else class="fs_license_form_wrapper">
                        <div class="fs_license_form_content">
                            <div class="fs_license_form_header">
                                <h3 class="fs_license_form_title">
                                    {{ $t("Customer Support Plugin for WordPress") }}
                                </h3>
                                <p class="fs_license_form_description">
                                    {{
                                        $t(
                                            "Please Provide a license key of FluentSupport"
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="fs_license_input_wrapper">
                                <el-input
                                    v-model="licenseKey"
                                    :placeholder="$t('License key')"
                                    :class="{ 'fs_license_input_error': showLicenseKeyError }"
                                    class="fs_license_input"
                                    @input="showLicenseKeyError = false"
                                >
                                    <template #append>
                                        <el-button
                                            @click="verifyLicense()"
                                            class="fs_license_verify_btn"
                                            >
                                            <svg width="17" height="9" viewBox="0 0 17 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M8.93775 5.25411C8.7467 6.36417 8.14642 7.36226 7.25544 8.05136C6.36445 8.74046 5.24749 9.07049 4.12507 8.9763C3.00264 8.88211 1.9563 8.37055 1.19262 7.5426C0.428933 6.71465 0.00339216 5.63048 1.87515e-06 4.50411C-0.00102824 3.37507 0.422395 2.28689 1.18626 1.45548C1.95013 0.62407 2.99862 0.11018 4.12371 0.0157691C5.2488 -0.0786414 6.36827 0.253328 7.26001 0.945811C8.15176 1.63829 8.75061 2.64069 8.93775 3.75411H16.5V5.25411H15V8.25411H13.5V5.25411H12V8.25411H10.5V5.25411H8.93775ZM4.5 7.50411C5.29565 7.50411 6.05871 7.18804 6.62132 6.62544C7.18393 6.06283 7.5 5.29976 7.5 4.50411C7.5 3.70847 7.18393 2.9454 6.62132 2.38279C6.05871 1.82019 5.29565 1.50411 4.5 1.50411C3.70435 1.50411 2.94129 1.82019 2.37868 2.38279C1.81607 2.9454 1.5 3.70847 1.5 4.50411C1.5 5.29976 1.81607 6.06283 2.37868 6.62544C2.94129 7.18804 3.70435 7.50411 4.5 7.50411V7.50411Z" fill="#525866"/>
                                            </svg>

                                            <span>{{ $t("Verify License") }}</span>
                                        </el-button>
                                    </template>
                                </el-input>
                                <p
                                    v-if="showLicenseKeyError"
                                    class="fs_license_error_message"
                                >
                                    {{ $t("Please enter your license key.") }}
                                </p>
                                <p
                                    v-else-if="!showLicenseKeyError"
                                    class="fs_license_hint_text"
                                >
                                    {{ $t("Please enter your license key.") }}
                                </p>
                            </div>
                        </div>
                        <p class="fs_license_purchase_text">
                            {{ $t("Don't have a license key?") }}
                            <a
                                class="fs_doc_link"
                                target="_blank"
                                :href="licenseData.purchase_url"
                                >{{ $t("Purchase one here") }}</a
                            >
                        </p>
                    </div>
                </div>
            </div>
            <div
                class="fs_box_body"
                v-else
            >
                <el-skeleton :rows="4" animated />
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: "LicenseManagement",
    data() {
        return {
            fetching: false,
            licenseData: {},
            verifying: false,
            licenseKey: "",
            showNewLicenseInput: false,
            errorMessage: "",
            showLicenseKeyError: false,
        };
    },
    mounted() {
        this.fetchLicense();
        this.$setTitle("License Management");
    },
    methods: {
        async fetchLicense() {
            this.errorMessage = "";
            this.showLicenseKeyError = false;
            this.fetching = true;
            await this.$get("pro/license", { verify: true })
                .then((response) => {
                    this.licenseData = response;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        async verifyLicense() {
            if (!this.licenseKey) {
                this.showLicenseKeyError = true;
                this.$notify({
                    type: "error",
                    message: "Please provide a license key",
                    position: "bottom-right",
                });
                this.errorMessage = "Please provide a license key";
                return;
            }

            this.showLicenseKeyError = false;
            this.verifying = true;

            this.errorMessage = "";
            await this.$post("pro/license", {
                license_key: this.licenseKey,
            })
                .then((response) => {
                    this.licenseData = response.license_data;
                    this.$notify({
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
                        errorMessage = this.convertToText(errorResponse);
                    }
                    if (!errorMessage) {
                        errorMessage = "Something is wrong!";
                    }

                    this.errorMessage = errorMessage;

                    this.$handleError(errorResponse);
                })
                .always(() => {
                    this.verifying = false;
                });
        },
        async deactivateLicense() {
            this.verifying = true;
            await this.$post("pro/remove-license")
                .then((response) => {
                    this.licenseData = response.license_data;
                    this.$notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.verifying = false;
                });
        },
    },
};
</script>
