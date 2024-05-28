<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>Integration Logs</h3>
            </div>
        </div>
        <div v-if="!loading" class="fs_box_body">
            <div class="fs_integration_cards">
                <div v-for="(item, index) in connections" class="fs_integration_card fs_integration_log">
                    <div class="fs_integration_card_left">
                        <div class="fs-integrations-logo">
                            <img :src="item.logo"/>
                        </div>
                        <div class="fs_integration_card_title">
                            <div class="fs_integration_title_inside">
                                <h3>{{ item.title }}</h3>
                                <span v-if="item.is_integrated" class="fs_integration_status">{{$t('Connected')}}</span>
                            </div>
                            <p v-html="item.description"></p>
                        </div>
                    </div>
                    <div class="fs_integration_card_right">
                        <!-- <el-link  href="https://element-plus.org" target="_blank">default</el-link> -->
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
            connections: {},
            fetching: false,
            settings_key: "ticket_form_settings",
        });

        const fetchSettings = () => {
            state.fetching = true;
            get("settings/integration-logs")
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
