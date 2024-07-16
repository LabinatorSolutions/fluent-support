<template>
    <el-popover
        placement="bottom"
        :width="600"
        trigger="manual"
        :visible="visible"
    >
        <template #reference>
            <el-button @click="initModal()" icon="MagicStick" size="small" type="default"></el-button>
        </template>
        <div class="fs_template_inserter">
            <div>
                <div>
                    <el-input :placeholder="translate('Ask AI to write anything...')" v-model="prompt" class="input-with-select">
                        <template #append>
                            <el-button @click="generateResponse" icon="Upload"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>

            <div v-loading="loading" class="fs_ai_response_box" v-if="aiResponse && !loading">
                <el-card shadow="never">
                    <pre class="fs_generated_text">{{ aiResponse }}</pre>
                </el-card>
                <div class="fs_ai_response_action_button">
                    <el-button type="primary" @click="insertReply(aiResponse)">Insert</el-button>
                    <el-button type="danger" @click="cancelResponse()">Cancel</el-button>
                </div>

            </div>

            <div class="fs_ai_response_loading" v-if="loading">
                <el-skeleton :rows="4" animated />
            </div>
        </div>
    </el-popover>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";
import {reactive, toRefs} from "vue";
import {useRoute} from "vue-router";
export default {
    name: 'ChatGPTResponseBox',
    components: {
        Pagination
    },
    setup(props, context) {
        const {
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
        } = useFluentHelper();

        const route = useRoute();
        const emit = context.emit;
        const state = reactive({
            selected_product: '',
            prompt: '',
            products: appVars.support_products,
            replies: [],
            aiResponse: '',
            visible: false,
            loading: false,
            ticketID: parseInt(route.params.ticket_id),
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            }
        });

        const initModal = () => {
            state.visible = !state.visible;
        };

        const insertReply = (aiResponse) => {
            emit('insert', aiResponse);
            state.aiResponse = '';
            state.prompt= '';
            state.visible = false;
        };

        const generateResponse = () => {
            state.loading = true;
            post(`chatGPT/${state.ticketID}/generate-response`, {
                content: state.prompt,
                id: state.ticketID,
                type: 'generateResponse'
            })
                .then(response => {
                    state.aiResponse = response;
                    state.loading = false;
                })
                .catch((errors) => {
                    state.loading = false;
                    handleError(errors);
                });
        }

        const cancelResponse = () => {
            state.aiResponse = '';
            state.prompt= '';
        }

        return {
            ...toRefs(state),
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
            initModal,
            insertReply,
            cancelResponse,
            generateResponse,
        }
    }
}
</script>
<style>
</style>

