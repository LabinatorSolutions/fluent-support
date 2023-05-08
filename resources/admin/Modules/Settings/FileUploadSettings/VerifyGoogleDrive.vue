<template>
    <div class="common-layout">
        <el-container>
            <el-main>
                <el-row :gutter="12" justify="center">
                    <el-col :span="12">
                        <el-card shadow="always" grid-content>
                            <div class="text-center">
                                <h2>Google Drive</h2>
                                <p>Connect your Google Drive account to upload files to your Google Drive.</p>
                                <el-button type="primary" @click="requestToken">Connect</el-button>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-main>
        </el-container>
    </div>
</template>

<script>
import {watch, reactive, onMounted, toRefs, computed} from "vue";
import { useRouter, useRoute } from 'vue-router';
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";


export default {
    name: "VerifyGoogleDrive",
    setup(){
        const { get, post, translate, handleError, setTitle, appVars } =
            useFluentHelper();

        const { notify } = useNotify();
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
                    "Content-Type" : "application/x-www-form-urlencoded"
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
}
</script>

<style scoped>

</style>
