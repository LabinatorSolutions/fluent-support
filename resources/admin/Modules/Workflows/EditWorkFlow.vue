<template>
    <div class="fs_all_workflows">
        <div v-if="!loading" class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <el-breadcrumb separator="/">
                        <el-breadcrumb-item :to="{ name: 'workflows' }">Workflows</el-breadcrumb-item>
                        <el-breadcrumb-item>Edit</el-breadcrumb-item>
                        <template v-if="workflow">
                            <el-breadcrumb-item>{{ workflow.title }} ({{ workflow.status }})</el-breadcrumb-item>
                        </template>
                    </el-breadcrumb>
                </div>
                <div v-if="workflow" class="fs_box_actions fs_ticket_orders">
                    <span style="margin-right: 10px; " class="fcon_status">Status: {{ workflow.status }}
                        <el-switch @change="updateWorkFlow()" active-value="published" inactive-value="draft"
                                   v-model="workflow.status"/>
                    </span>
                    <el-button :disabled="saving" v-loading="saving" size="small" type="primary"
                               @click="updateWorkFlow()">Update Workflow
                    </el-button>
                </div>
            </div>

            <div v-if="workflow" v-loading="saving" class="fs_workflow_edit_wrap">
                <div style="padding: 20px 0px;">
                    <el-input type="text" placeholder="Workflow Title" v-model="workflow.title"/>
                </div>
                <div v-if="workflow.trigger_type == 'automatic'" class="fs_box fs_triggers_wrap">
                    <div class="fs_box_header" style="padding: 10px 15px;font-size: 16px;">
                        <div class="fs_box_head">
                            <h3>Set Your Trigger & Conditions</h3>
                        </div>
                    </div>
                    <div class="fs_box_body fs_padded_20">
                        <el-form label-position="top" :data="workflow">
                            <trigger-mappers :workflow_conditions="workflow_conditions" :workflow="workflow" :trigger_fields="trigger_fields" />
                        </el-form>
                    </div>
                </div>
                <div v-else class="fs_box fs_triggers_wrap">
                    <div class="fs_box_header" style="padding: 10px 15px;font-size: 16px;">
                        <div class="fs_box_head">
                            <h3>Manual Trigger</h3>
                        </div>
                    </div>
                    <div class="fs_box_body fs_padded_20">
                        This is a <b>manual workflow</b>. You can set your actions and run the workflow actions from
                        ticket page.
                    </div>
                </div>

                <div style="margin-top: 20px" class="fs_box fs_actions_wrap">
                    <div class="fs_box_header" style="padding: 10px 15px;font-size: 16px;">
                        <div class="fs_box_head">
                            <h3>Workflow Actions (Tasks)</h3>
                        </div>
                    </div>
                    <div class="fs_box_body fs_padded_20">

                        <action-mappers @update="updateWorkFlow()" :actions="actions"
                                        :all_actions="action_fields"></action-mappers>

                    </div>
                </div>


            </div>
            <div v-else class="fs_workflow_edit_wrap">
                <div style="background: white">
                    <el-skeleton :rows="3" animated/>
                    <el-skeleton :rows="4" animated/>
                </div>
            </div>

        </div>
    </div>
</template>

<script type="text/babel">
import ActionMappers from "./_ActionMappers";
import TriggerMappers from "./_TiggerMappers";

export default {
    name: 'EditWorkFlow',
    components: {
        ActionMappers,
        TriggerMappers
    },
    props: ['workflow_id'],
    data() {
        return {
            workflow: false,
            workflow_conditions: [],
            actions: [],
            action_fields: {},
            trigger_fields: {},
            loading: false,
            saving: false
        }
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('workflows/' + this.workflow_id, {
                with: ['action_fields', 'trigger_fields']
            })
                .then((response) => {
                    if(response.workflow.trigger_type == 'automatic') {
                        this.workflow_conditions = response.workflow.settings.conditions;
                    }
                    this.workflow = response.workflow;
                    this.actions = response.actions;
                    this.trigger_fields = response.trigger_fields;
                    this.action_fields = response.action_fields;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        updateWorkFlow() {
            this.saving = true;
            const workFlow = JSON.parse(JSON.stringify(this.workflow));
            if(workFlow.trigger_type == 'automatic') {
                workFlow.settings.conditions = this.workflow_conditions;
            }
            this.$post('workflows/' + this.workflow_id, {
                actions: this.actions,
                workflow: workFlow
            })
                .then((response) => {
                    this.$notify.success(response.message);
                    if(response.workflow.trigger_type == 'automatic') {
                        this.workflow_conditions = response.workflow.settings.conditions;
                    }
                    this.workflow = response.workflow;
                    this.actions = response.actions;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
    },
    mounted() {
        if (this.has_pro) {
            this.fetch();
        }
    }
}
</script>
