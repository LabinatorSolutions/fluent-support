<template>
    <div v-if="workflows && workflows.length" class="fs_workflow_pop">
        <el-popover
            placement="bottom"
            :width="400"
            v-model:visible="visible"
            trigger="click"
        >
            <template #reference>
                <i class="el-icon-lightning"/>
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
            :title="$t('Review your Workflow')"
            width="60%"
        >
            <div v-if="modalVisible" v-loading="fetchingActions" class="fs_workflow_desc">
                <h3>{{$t('Running the')}} <b>{{ selected_workflow.title }}</b> {{$t('workflow will perform the following actions:')}}</h3>
                <ul class="fs_workflow_items">
                    <li v-for="(task,taskIndex) in workflow_actions" :key="task.id">
                        <span v-if="taskIndex != 0">{{$t('and')}}</span> {{ task.title || task.action_name }}
                    </li>
                </ul>
            </div>

            <template #footer>
                <span class="dialog-footer">
                    <el-button v-loading="applying" :disabled="applying || fetchingActions" type="primary"
                               @click="runWorkFow()">{{$t('Run Workflow')}}</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
export default {
    name: 'WorkflowRunner',
    props: ['ticket_ids'],
    data() {
        return {
            workflows: this.appVars.manual_workflows,
            selected_workflow: false,
            visible: false,
            modalVisible: false,
            workflow_actions: [],
            fetchingActions: false,
            applying: false
        }
    },
    methods: {
        showWorkflowModal(workflow) {
            this.selected_workflow = workflow;
            this.fetchActions();
            this.visible = false;
            this.modalVisible = true;
        },
        fetchActions() {
            this.workflow_actions = [];
            this.fetchingActions = true;
            this.$get('workflows/' + this.selected_workflow.id + '/actions')
                .then((response) => {
                    this.workflow_actions = response.actions;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetchingActions = false;
                });
        },
        runWorkFow() {
            this.applying = true;
            this.$post('workflows/' + this.selected_workflow.id + '/run', {
                ticket_ids: this.ticket_ids
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.modalVisible = false;
                    this.$emit('reloadTickets');
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.applying = false;
                });
        }
    }
}
</script>
