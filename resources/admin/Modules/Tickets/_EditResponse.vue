<template>
    <div class="fs_edit_response">
        <wp-editor v-if="editor_ready"  v-model="response.content" />
        <hr />
        <el-button size="large" type="success" @click="editResponse()">{{$t('Update Response')}}</el-button>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import {reactive, toRefs} from "vue";
import {useConfirm, useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'EditResponse',
    props: ['response'],
    components: {
        WpEditor
    },
    setup(props, context){
        const emit = context.emit;
        const { notify } = useNotify();
        const { confirm } = useConfirm();
        const {
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
        } = useFluentHelper();
        const state = reactive({
            saving: false,
            filteredAgents: appVars.support_agents,
            popup: false,
            editor_ready: true
        });

        const editResponse = () => {
            state.saving = true;
            put(`tickets/${props.response.ticket_id}/responses/${props.response.id}`, {
                content: props.response.content
            })
                .then(response => {
                    notify.success({
                        message: response.message,
                        position: 'bottom-right',
                        type: 'success'
                    });
                    emit('updated', response.response);
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        }

        return {
            ...toRefs(state),
            notify,
            confirm,
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
            emit,
            editResponse
        }
    }
}
</script>
