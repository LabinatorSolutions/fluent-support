<template>
    <div class="common-layout">
        <el-container>
            <el-main>
                <el-row :gutter="12" justify="center">
                    <el-col :span="12">
                        <el-card shadow="always" grid-content>
                            <div v-loading="loading || verifying" class="text-center">
                                <h2>{{ $t('Google Drive') }}</h2>
                                <p>{{ $t('Verifying your API connection. Please wait') }}</p>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-main>
        </el-container>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'VerifyGoogleDrive',
    data() {
        return {
            settings: null,
            code: null,
            loading: false,
            verifying: false
        }
    },
    methods: {
        fetchSettings() {
            this.loading = true;
            this.$get('settings/upload_integration', {
                integration_key: 'google_drive'
            })
                .then(response => {
                    this.settings = response.settings;
                    this.requestToken();
                })
                .catch((errors) => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.loading = false;
                });
        },
        requestToken() {
            this.verifying = true;
            const body = new URLSearchParams({
                'client_id': this.settings.client_id,
                'client_secret': this.settings.client_secret,
                'grant_type': 'authorization_code',
                'code': this.code,
                'access_type': 'offline',
                'redirect_uri': this.settings.redirect_uri,
            });

            const requestOptions = {
                method: 'POST',
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: body,
                redirect: 'follow'
            };

            fetch('https://accounts.google.com/o/oauth2/token', requestOptions)
                .then(response => response.json())
                .then(result => {
                    if (result.access_token) {
                        this.settings.access_token = result.access_token;
                        this.settings.refresh_token = result.refresh_token;
                        this.settings.expires_in = result.expires_in;
                        this.settings.is_oauth_flow = true;
                        this.saveSettings();
                    } else {
                        this.$notify({
                            type: 'error',
                            message: 'Something went wrong. Please try again.',
                            position: 'bottom-right'
                        });
                        this.$router.push({name: 'upload_integration'});
                    }
                })
                .catch(errors => {
                    this.$handleError(errors);
                    this.verifying = false;
                });
        },

        saveSettings() {
            this.verifying = true;
            this.$post('settings/upload_integration', {
                integration_key: 'google_drive',
                settings: this.settings
            })
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.$router.push({name: 'upload_integration'});
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.verifying = false;
                });
        },
    },
    mounted() {
        this.fetchSettings();
        this.code = this.$route.query.code;
    }
}
</script>
