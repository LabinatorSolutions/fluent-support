<template>
    <div class="fs_all_workflows">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("All Workflows") }}</h3>
                </div>
                <div v-if="has_pro" class="fs_box_actions fs_ticket_orders">
                    <el-button
                        type="primary"
                        @click="showAddModal = true"
                        icon="Plus"
                        >{{ translate("Add New WorkFlow") }}
                    </el-button>
                </div>
            </div>
            <div v-if="has_pro" class="fs_box_body fs_padded_20">
                <template v-if="!loading">
                    <el-table :data="workflows" border stripe>
                        <el-table-column
                            prop="id"
                            :label="translate('ID')"
                            width="90"
                        />
                        <el-table-column :label="translate('Title')">
                            <template #default="scope">
                                <router-link
                                    :to="{
                                        name: 'edit-workflow',
                                        params: { workflow_id: scope.row.id },
                                    }"
                                >
                                    {{ scope.row.title }}
                                </router-link>
                                <span
                                    v-if="scope.row.trigger_human_name"
                                    class="fs_trigger_sub"
                                    >{{ scope.row.trigger_human_name }}</span
                                >
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="status"
                            :label="translate('Status')"
                            width="120"
                        />
                        <el-table-column
                            prop="trigger_type"
                            :label="translate('Trigger Type')"
                            width="120"
                        />
                        <el-table-column
                            :label="translate('Actions')"
                            width="120"
                        >
                            <template #default="scope">
                                <router-link
                                    :to="{
                                        name: 'edit-workflow',
                                        params: { workflow_id: scope.row.id },
                                    }"
                                >
                                    <el-icon> <EditPen /> </el-icon>
                                </router-link>
                                <el-popconfirm
                                    confirm-button-type="danger"
                                    :confirm-button-text="
                                        translate('Yes, Delete this')
                                    "
                                    :cancel-button-text="translate('No')"
                                    icon="InfoFilled"
                                    icon-color="red"
                                    :title="translate('Are you sure to delete this? All associate data will be deleted')"
                                    @confirm="deleteWorkflow(scope.row.id)"
                                >
                                    <template #reference>
                                        <el-button
                                            v-loading="deleting"
                                            style="
                                                margin-left: 10px;
                                                color: red;
                                            "
                                            type="text"
                                            size="small"
                                            icon="Delete"
                                        ></el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="fframe_pagination_wrapper" v-if="workflows.length">
                        <pagination @fetch="fetch()" :pagination="pagination" />
                    </div>
                </template>

                <div v-else>
                    <el-skeleton :rows="3" animated />
                </div>
            </div>
            <div class="fs_narrow_promo" style="background: white" v-else>
                <h3>
                    {{
                        translate(
                            "Automate your tickets by applying manual or automated tasks"
                        )
                    }}
                </h3>
                <p>{{ translate("pro_promo") }}</p>
                <a
                    target="_blank"
                    rel="noopener"
                    href="https://fluentsupport.com"
                    class="el-button el-button--success"
                    >{{ translate("Upgrade To Pro") }}</a
                >
            </div>
        </div>
        <el-dialog
            v-model="showAddModal"
            :title="translate('Add a New Workflow')"
            width="60%"
        >
            <el-form :data="new_workflow" label-position="top">
                <el-form-item :label="translate('Your Workflow Name')">
                    <el-input
                        text
                        :placeholder="translate('Workflow Name')"
                        v-model="new_workflow.title"
                    />
                </el-form-item>
                <el-form-item label="Workflow Type">
                    <el-radio-group v-model="new_workflow.trigger_type">
                        <el-radio label="manual">{{ translate("Manual") }}</el-radio>
                        <el-radio label="automatic">{{ translate("Automatic") }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <div class="fs_workflow_type_info">
                    <div v-if="new_workflow.trigger_type == 'manual'">
                        <div class="fs_workflow_info_header">
                            {{ translate("A") }}
                            <b>{{ translate("Manual workflow") }}</b>
                            {{
                                translate(
                                    "doesn't do anything until you tell it to. When you apply a manual workflow from a ticket, Fluent Support performs all the actions."
                                )
                            }}
                        </div>
                        <div class="fs_workflow_info_body">
                            <b>{{ translate("Example") }}</b
                            ><br />
                            {{
                                translate(
                                    "When a customer's ticket/response in with a specific question, you execute this workflow to send a reply, add a tag and assign it to someone on your team."
                                )
                            }}
                        </div>
                    </div>
                    <div v-else-if="new_workflow.trigger_type == 'automatic'">
                        <div class="fs_workflow_info_header">
                            <b>{{ translate("Automatic workflows") }}</b>
                            {{
                                translate(
                                    " are always on, running on selected ticket events. Based on what's happening to your tickets and conversations, It will run the defined actions automatically."
                                )
                            }}
                        </div>
                        <div class="fs_workflow_info_body">
                            <b>{{ translate("Example") }}</b
                            ><br />
                            {{
                                translate(
                                    'When the subject line contains "Bug Report", you want Fluent Support to automatically add a tag, send an email to the customer and assign a support agent.'
                                )
                            }}
                        </div>
                    </div>
                </div>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button
                        v-loading="creating"
                        :disabled="creating || !new_workflow.title"
                        type="primary"
                        @click="createWorkflow()"
                        >{{ translate("Continue") }}</el-button
                    >
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import { useRouter } from "vue-router";
import { onMounted, reactive, toRefs } from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "AllWorkflows",
    components: {
        Pagination,
    },
    setup() {
        const router = useRouter();
        const { get, post, translate, handleError, renewOptions, del, setTitle, has_pro } =
            useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            workflows: [],
            loading: false,
            search: "",
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0,
            },
            showAddModal: false,
            new_workflow: {
                title: "",
                trigger_type: "manual",
            },
            creating: false,
            deleting: false,
        });

        const fetch = async () => {
            state.loading = true;
            await get("workflows", {
                per_page: state.pagination.per_page,
                page: state.pagination.current_page,
                search: state.search,
            })
                .then((response) => {
                    state.workflows = response.workflows.data;
                    state.pagination.total = response.workflows.total;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const createWorkflow = async () => {
            if (!state.new_workflow.title) {
                notify({
                    type: "error",
                    message: "Workflow title is required",
                    position: "bottom-right",
                });
                return false;
            }
            state.creating = true;
            await post("workflows", state.new_workflow)
                .then((response) => {

                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                    router.push({
                        name: "edit-workflow",
                        params: { workflow_id: response.workflow.id },
                    });

                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.creating = false;
                });
        };

        const deleteWorkflow = async (workflowId) => {
            state.deleting = true;
            await del("workflows/" + workflowId)
                .then((response) => {
                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                    fetch();
                    renewOptions("workflows/options");
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.deleting = false;
                });
        };

        onMounted(() => {
            if (has_pro) {
                fetch();
            }

            setTitle("Workflows");
        });

        return {
            ...toRefs(state),
            fetch,
            createWorkflow,
            deleteWorkflow,
            translate,
        };
    },
};
</script>
