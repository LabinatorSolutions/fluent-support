<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{$t('Integration Statuses')}}</h3>
            </div>
        </div>
        <div v-if="!loading" class="fs_box_body">
            <div class="fs_integration_logs_cards">
                <div v-for="(item, index) in connections" class="fs_integration_logs_card fs_integration_logs_log">
                    <div class="fs_integration_logs_card_left">
                        <div class="fs_integrations_logs_logo">
                            <img :src="item.logo"/>
                        </div>
                        <div class="fs_integration_logs_card_title">
                            <div class="fs_integration_logs_title_inside">
                                <h3>{{ item.title }}</h3>
                                <el-tag v-if="item.is_integrated" class="fs_integration_logs_status" type="success" effect="plain">{{$t('Connected')}}</el-tag>
                            </div>
                            <p v-html="item.description"></p>
                        </div>
                    </div>
                    <div class="fs_integration_logs_card_right">
                        <el-button @click="redirectToDocs(item.doc_url)">
                            Learn More
                        </el-button>
                    </div>
                </div>
            </div>
        </div>
        <el-skeleton v-else :animated="true" :rows="3" />
    </div>
</template>

<script type="text/babel">
import FormBuilder from "../../Pieces/FormElements/_FormBuilder";
import { onMounted, reactive, toRefs } from "vue";
import {
    useFluentHelper,
} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'IntegrationStatuses',
    components: {
        FormBuilder,
    },
    setup() {
        const { get, handleError, translate } =
            useFluentHelper();

        const state = reactive({
            connections: {},
            fetching: false,
            settings_key: "ticket_form_settings",
        });

        const fetchSettings = () => {
            state.fetching = true;
            get("settings/integration-statuses")
                .then((response) => {
                    state.connections = response.connections;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };

        const redirectToDocs = (links) => {
            window.open(links);
        }

        onMounted(() => {
            fetchSettings();
        });

        return {
            ...toRefs(state),
            translate,
            fetchSettings,
            redirectToDocs
        };
    },
};
</script>
