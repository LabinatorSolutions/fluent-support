<template>
    <div v-if="workflows && workflows.length" class="fs_workflow_pop">
        <el-popover
            placement="bottom"
            :width="400"
            trigger="click"
        >
            <template #reference>
                <el-icon style="vertical-align: middle;">
                    <Lightning/>
                </el-icon>
            </template>

            <ul class="fs_workflows_list">
                <li v-for="workflow in workflows" @click="showWorkflowModal(workflow)" :key="workflow.id">
                    {{ workflow.title }}
                </li>
            </ul>
        </el-popover>

        <el-dialog
            v-model="modalVisible"
            :append-to-body="true"
            :title="translate('Review your Workflow')"
            width="60%"
            class="fs_dialog"
        >
            <div v-if="modalVisible" v-loading="fetchingActions" class="fs_workflow_desc">
                <h3>{{ translate('Running the') }} <b>{{ selected_workflow.title }}</b>
                    {{ translate('workflow will perform the following actions:') }}</h3>
                <ul class="fs_workflow_items">
                    <li v-for="(task,taskIndex) in workflow_actions" :key="task.id">
                        <span v-if="taskIndex != 0">{{ translate('and') }}</span> {{ task.title || task.action_name }}
                    </li>
                </ul>
            </div>

            <template #footer>
                <span class="dialog-footer">
                    <el-button v-loading="applying" :disabled="applying || fetchingActions" type="primary"
                               @click="runWorkFow()">{{ translate('Run Workflow') }}</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
import {reactive, toRefs} from "vue";
import {useRouter} from 'vue-router'
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'WorkflowRunner',
    props: ['ticket_ids'],

    setup(props, {emit}) {
        const {
            translate,
            handleError,
            appVars,
            post,
            get
        } = useFluentHelper();
        const {notify} = useNotify();
        const router = useRouter()

        const state = reactive({
            workflows: appVars.manual_workflows,
            selected_workflow: false,
            visible: false,
            modalVisible: false,
            workflow_actions: [],
            fetchingActions: false,
            applying: false
        });

        const showWorkflowModal = (workflow) => {
            state.selected_workflow = workflow;
            fetchActions();
            state.visible = false;
            state.modalVisible = true;
        };

        const fetchActions = () => {
            state.workflow_actions = [];
            state.fetchingActions = true;
            get('workflows/' + state.selected_workflow.id + '/actions')
                .then((response) => {
                    state.workflow_actions = response.actions;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetchingActions = false;
                });
        };

        const runWorkFow = () => {
            state.applying = true;
            post('workflows/' + state.selected_workflow.id + '/run', {
                ticket_ids: props.ticket_ids
            })
                .then(response => {
                    notify({
                        message: response.message,
                        position: 'bottom-right',
                        type: 'success'
                    });
                    state.modalVisible = false;
                    // if `workflow_action` contains `fs_delete_ticket` on `action_name` then redirect to tickets list
                    if (state.workflow_actions.some(action => action.action_name === 'fs_delete_ticket')) {
                        router.push({name: 'tickets'});
                    } else {
                        emit('reloadTickets');
                    }
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.applying = false;
                });
        }

        return {
            ...toRefs(state),
            showWorkflowModal,
            fetchActions,
            runWorkFow,
            translate
        }
    }
}
</script>
