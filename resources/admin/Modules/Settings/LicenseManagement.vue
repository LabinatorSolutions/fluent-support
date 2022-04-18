<template>
    <div class="fs_license_manager">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('License Management') }}</h3>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-button
                        type="text"
                        icon="Refresh"
                        @click="fetchLicense()">
                    </el-button>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">

                <div style="background: white;" v-loading="verifying" class="fs_narrow_promo text-align-center" :class="'fc_license_'+licenseData.status">
                    <div v-if="licenseData.status == 'expired'">
                        <h3>{{$t('Looks like your license has been expired')}} {{licenseData.expires | nsHumanDiffTime}}</h3>
                        <a :href="licenseData.renew_url" target="_blank" class="el-button el-button--danger el-button--small">{{$t('Click Here to Renew your License')}}</a>

                        <hr style="margin: 20px 0px;" />
                        <p v-if="!showNewLicenseInput">{{$t('Have a new license Key?')}} <a @click.prevent="showNewLicenseInput = !showNewLicenseInput" href="#">Click here</a></p>
                        <div v-else>
                            <h3>{{$t('Your License Key')}}</h3>
                            <el-input v-model="licenseKey" placeholder="License Key">
                                <template #append>
                                    <el-button @click="verifyLicense()" icon="Lock">{{$t('Verify License')}}</el-button>
                                </template>
                            </el-input>
                        </div>
                    </div>
                    <div v-else-if="licenseData.status == 'valid'">
                        <el-icon style="font-size: 50px; text-align: center;"><circle-check /></el-icon>
                        <h2>{{$t('Your license key is valid and activated')}}</h2>
                        <hr style="margin: 20px 0px;" />
                        <p>{{$t('Want to deactivate this license?')}} <a @click.prevent="deactivateLicense()" href="#">{{$t('Click here')}}</a></p>
                    </div>
                    <div v-else>
                        <h3>{{$t('Please Provide a license key of FluentSupport - Customer Support Plugin for WordPress')}}</h3>
                        <el-input v-model="licenseKey" placeholder="License Key">
                            <template #append>
                                <el-button @click="verifyLicense()" icon="Lock">{{$t('Verify License')}}</el-button>
                            </template>
                        </el-input>
                        <hr style="margin: 20px 0 30px;" />
                        <p v-if="!showNewLicenseInput">{{$t('dont_have_a_license_key')}} <a target="_blank" :href="licenseData.purchase_url">{{$t('Purchase one here')}}</a></p>
                    </div>
                </div>

                <p class="text-align-center" style="color: red;" v-html="errorMessage"></p>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body fs_narrow_promo" v-else>
                <el-skeleton :rows="5" animated/>
                <h3>{{$t('Fetching License Information Please wait')}}</h3>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'LicenseManagement',
    data() {
        return {
            fetching: false,
            licenseData: {},
            verifying: false,
            licenseKey: '',
            showNewLicenseInput: false,
            errorMessage: ''
        }
    },
    methods: {
        fetchLicense() {
            this.errorMessage = '';
            this.fetching = true;
            this.$get('pro/license', { verify: true })
                .then((response) => {
                    this.licenseData = response;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        verifyLicense() {
            if (!this.licenseKey) {
                this.$notify.error({
                    message: 'Please provide a license key',
                    position: 'bottom-right'
                });
                this.errorMessage = 'Please provide a license key';
                return;
            }

            this.verifying = true;

            this.errorMessage = '';
            this.$post('pro/license', {
                license_key: this.licenseKey
            })
                .then(response => {
                    this.licenseData = response.license_data;
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                })
                .catch((errorResponse) => {
                    let errorMessage = '';
                    if (typeof errorResponse === 'string') {
                        errorMessage = errorResponse;
                    } else if (errorResponse && errorResponse.message) {
                        errorMessage = errorResponse.message;
                    } else {
                        errorMessage = window.FLUENTCRM.convertToText(errorResponse);
                    }
                    if (!errorMessage) {
                        errorMessage = 'Something is wrong!';
                    }

                    this.errorMessage = errorMessage;

                    this.handleError(errorResponse);
                })
                .always(() => {
                    this.verifying = false;
                });
        },
        deactivateLicense() {
            this.verifying = true;
            this.$post('pro/remove-license')
                .then(response => {
                    this.licenseData = response.license_data;
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                })
                .catch((errors) => {
                    this.handleError(errors)
                })
                .always(() => {
                    this.verifying = false;
                });
        }
    },
    mounted() {
        this.fetchLicense();
    }
}
</script>
