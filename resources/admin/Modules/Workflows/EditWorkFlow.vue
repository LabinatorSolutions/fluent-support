<template>
    <div class="fs_all_workflows">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <el-breadcrumb separator="/">
                        <el-breadcrumb-item :to="{ name: 'workflows' }">{{$t('Workflows')}}</el-breadcrumb-item>
                        <el-breadcrumb-item>{{$t('Edit')}}</el-breadcrumb-item>
                        <template v-if="workflow">
                            <el-breadcrumb-item>{{ workflow.title }} ({{ workflow.status }})</el-breadcrumb-item>
                        </template>
                    </el-breadcrumb>
                </div>
                <div v-if="workflow" class="fs_box_actions fs_ticket_orders">
                    <span style="margin-right: 10px; " class="fcon_status">{{$t('Status')}}: {{ workflow.status }}
                        <el-switch @change="updateWorkFlow()" active-value="published" inactive-value="draft"
                                   v-model="workflow.status"/>
                    </span>
                    <el-button :disabled="saving" v-loading="saving" type="success"
                               @click="updateWorkFlow()">{{$t('Update Workflow')}}
                    </el-button>
                </div>
            </div>

            <div v-if="workflow" v-loading="saving" class="fs_workflow_edit_wrap">
                <div style="padding: 20px 0px;">
                    <el-input type="text" :placeholder="$t('Workflow Title')" v-model="workflow.title"/>
                </div>
                <div v-if="workflow.trigger_type == 'automatic'" class="fs_box fs_triggers_wrap">
                    <div class="fs_box_header" style="padding: 10px 15px;font-size: 16px;">
                        <div class="fs_box_head">
                            <h3>{{$t('Set Your Trigger & Conditions')}}</h3>
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
                            <h3>{{$t('Manual Trigger')}}</h3>
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
                            <h3>{{$t('Workflow Actions (Tasks)')}}</h3>
                        </div>
                    </div>
                    <div class="fs_box_body fs_padded_20">

                        <action-mappers @update="updateWorkFlow()" :actions="actions"
                                        :all_actions="filtred_action_fields"></action-mappers>

                    </div>
                </div>


            </div>
            <div style="background: white; padding: 20px; margin-top: 20px;" v-else class="fs_workflow_edit_wrap">
                <div>
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
            saving: false,
            filtred_action_fields: {}
        }
    },
    watch: {
        'workflow.trigger_key'() {
            this.filtred_action_fields = {};

            if (this.workflow.trigger_key == 'fluent_support/ticket_closed') {
                const exclude = ['fs_action_create_response', 'fs_action_close_ticket', 'fs_action_assign_agent', 'fs_block_customer', 'fs_delete_ticket'];

                for(let field in this.action_fields){
                    if (!exclude.includes(field)) {
                        this.filtred_action_fields[field] = this.action_fields[field];
                    }
                }
            }

            else if( this.workflow.trigger_key == 'fluent_support/ticket_created' ) {
                const exclude = ['fs_action_remove_bookmarks', 'fs_action_remove_tags'];

                for(let field in this.action_fields){
                    if (!exclude.includes(field)) {
                        this.filtred_action_fields[field] = this.action_fields[field];
                    }
                }
            }

            else if( this.workflow.trigger_key == 'fluent_support/response_added_by_customer' ) {
                this.filtred_action_fields = this.action_fields;
            } else {
                this.filtred_action_fields = this.action_fields;
            }

            return this.filtred_action_fields;
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
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
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

        this.$setTitle('Edit Workflow');
    }
}
</script>

<style scoped>
    .fs_box_wrapper .fs_box_header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-direction: row;
    }
</style>
