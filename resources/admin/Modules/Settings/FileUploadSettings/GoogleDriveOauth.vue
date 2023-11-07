<template>
    <div class="common-layout">
        <el-container>
            <el-main>
                <el-row :gutter="12" justify="center">
                    <el-col :span="12">
                        <el-card shadow="always" grid-content>
                            <div v-loading="loading || verifying" class="text-center">
                                <h2>Google Drive</h2>
                                <p>Verifying your API connection. Please wait</p>
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
                    this.$fluent.handleError(errors)
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
                        this.$notify.error('Something went wrong. Please try again.');
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
                    this.$notify.success(response.message);
                    this.$router.push({name: 'upload_integration'});
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.verifying = false;
                });
        },

        setupX() {
            const {get, post, translate, handleError, setTitle, appVars} =
                useFluentHelper();

            const {notify} = useNotify();
            const router = useRouter();
            const route = useRoute();

            const state = reactive({
                code: false,
                settings: false,
                loading: false,
                redirect_uri: appVars.rest.url + '/public/google_auth',
            });

            watch(route, (route) => {
                    state.code = route.query.code;
                },
                {immediate: true}
            )

            const requestToken = () => {

                const body = new URLSearchParams({
                    'client_id': state.settings.client_id,
                    'client_secret': state.settings.client_secret,
                    'grant_type': 'authorization_code',
                    'code': state.code,
                    'access_type': 'offline',
                    'redirect_uri': state.redirect_uri,
                });


                const requestOptions = {
                    method: 'POST',
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: body,
                    redirect: 'follow'
                };

                fetch("https://accounts.google.com/o/oauth2/token", requestOptions)
                    .then(response => response.json())
                    .then(result => {
                            state.settings.access_token = result.access_token
                            state.settings.refresh_token = result.refresh_token
                            saveSettings()
                        }
                    )
                    .catch(error => console.log('error', error));

            }

            const fetchSettings = async () => {
                state.loading = true;
                await get('settings/upload_integration', {
                    integration_key: 'google_drive_settings'
                })
                    .then(response => {
                        state.settings = response.settings;
                    })
                    .catch((errors) => {
                        handleError(errors)
                    })
                    .always(() => {
                        state.loading = false;
                    });
            };

            const saveSettings = () => {
                state.saving = true;
                post('settings/upload_integration', {
                    integration_key: 'google_drive_settings',
                    settings: state.settings
                })
                    .then(response => {
                        router.push({name: 'upload_integration', query: {integration_key: 'google_drive_settings'}});
                    })
                    .catch((errors) => {
                        handleError(errors);
                    })
                    .always(() => {
                        state.saving = false;
                    });
            };

            onMounted(() => {
                fetchSettings();
            });
            return {
                ...toRefs(state),
                fetchSettings,
                requestToken,
                saveSettings
            }
        } // end setup
    },
    mounted() {
        this.fetchSettings();
        this.code = this.$route.query.code;
    }
}
</script>

<style scoped>

</style>
