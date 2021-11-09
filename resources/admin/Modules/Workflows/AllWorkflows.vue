<template>
    <div v-loading="loading" class="fs_all_workflows">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('All Workflows') }}</h3>
                </div>
                <div v-if="has_pro" class="fs_box_actions fs_ticket_orders">
                    <el-button type="primary" @click="showAddModal = true" size="small" icon="el-icon-plus">{{
                            $t('Add New WorkFlow')
                        }}
                    </el-button>
                </div>
            </div>
            <div class="fs_box_body fs_padded_20">
                <el-table :data="workflows" border stripe>
                    <el-table-column prop="id" label="ID" width="90"/>
                    <el-table-column label="Title">
                        <template #default="scope">
                            <router-link :to="{ name: 'edit-workflow', params: { workflow_id: scope.row.id } }">
                                {{ scope.row.title }}
                            </router-link>
                            <span v-if="scope.row.trigger_human_name"
                                  class="fs_trigger_sub">{{ scope.row.trigger_human_name }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" label="Status" width="120"/>
                    <el-table-column prop="trigger_type" label="Trigger Type" width="120"/>
                    <el-table-column label="Actions" width="120">
                        <template #default="scope">
                            <router-link :to="{ name: 'edit-workflow', params: { workflow_id: scope.row.id } }">
                                <i class="el-icon-edit"></i>
                            </router-link>
                            <el-popconfirm
                                confirm-button-text="Yes, Delete this"
                                cancel-button-text="No"
                                icon="el-icon-info"
                                icon-color="red"
                                title="Are you sure to delete this? All associate data will be deleted"
                                @confirm="deleteWorkflow(scope.row.id)"
                            >
                                <template #reference>
                                    <el-button v-loading="deleting" style="margin-left: 10px; color: red;" type="text"
                                               size="mini"
                                               icon="el-icon-delete"></el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="fetch()" :pagination="pagination"/>
                </div>

                <div v-if="loading">
                    <el-skeleton :rows="3" animated/>
                </div>
            </div>
        </div>
        <el-dialog
            v-model="showAddModal"
            title="Add a New Workflow"
            width="60%"
        >
            <el-form :data="new_workflow" label-position="top">
                <el-form-item label="Your Workflow Name">
                    <el-input type="text" placeholder="Workflow Name" v-model="new_workflow.title"/>
                </el-form-item>
                <el-form-item label="Workflow Type">
                    <el-radio-group v-model="new_workflow.trigger_type">
                        <el-radio value="manual" label="manual">Manual</el-radio>
                        <el-radio value="automatic" label="automatic">Automatic</el-radio>
                    </el-radio-group>
                </el-form-item>
                <div class="fs_workflow_type_info">
                    <div v-if="new_workflow.trigger_type == 'manual'">
                        <div class="fs_workflow_info_header">
                            A <b>Manual workflow</b> doesn't do anything until you tell it to. When you apply a manual
                            workflow from a ticket, Fluent Support performs all the actions.
                        </div>
                        <div class="fs_workflow_info_body">
                            <b>Example</b><br/>
                            When a customer's ticket/response in with a specific question, you execute this workflow to
                            send a reply, add a tag and assign it to someone on your team.
                        </div>
                    </div>
                    <div v-else-if="new_workflow.trigger_type == 'automatic'">
                        <div class="fs_workflow_info_header">
                            <b>Automatic workflows</b> are always on, running on selected ticket events. Based on what's
                            happening to your tickets and conversations, It will run the defined actions automatically.
                        </div>
                        <div class="fs_workflow_info_body">
                            <b>Example</b><br/>
                            When the subject line contains "Bug Report", you want Fluent Support to automatically add a
                            tag, send an email to the customer and assign a support agent.
                        </div>
                    </div>
                </div>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button v-loading="creating" :disabled="creating || !new_workflow.title" type="primary"
                               @click="createWorkflow()">Continue</el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";

export default {
    name: 'AllWorkflows',
    components: {
        Pagination
    },
    data() {
        return {
            workflows: [],
            loading: false,
            search: '',
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            },
            showAddModal: false,
            new_workflow: {
                title: '',
                trigger_type: 'manual'
            },
            creating: false,
            deleting: false
        }
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('workflows', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search
            })
                .then((response) => {
                    this.workflows = response.workflows.data;
                    this.pagination.total = response.workflows.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        createWorkflow() {
            if (!this.new_workflow.title) {
                this.$notify.error('Workflow title is required');
                return false;
            }
            this.creating = true;
            this.$post('workflows', this.new_workflow)
                .then(response => {
                    this.$notify.success(response.message);
                    this.$router.push({name: 'edit-workflow', params: {workflow_id: response.workflow.id}})
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        },
        deleteWorkflow(workflowId) {
            this.deleting = true;
            this.$del('workflows/' + workflowId)
                .then(response => {
                    this.$notify.success(response.message);
                    this.fetch();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.deleting = false;
                });
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetch();
        }

        this.$setTitle('Workflows');
    }
}
</script>
