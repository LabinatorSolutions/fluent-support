<template>
    <div class="fs_all_workflows">
        <div class="fs_box_wrapper" v-if="has_pro">
            <div class="fs_component_header">
                <div class="fs_component_head">
                    <h3 class="fs_page_title">{{ $t("All Workflows") }}</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button
                        type="primary"
                        :disabled="!has_pro"
                        class="fs_filled_btn"
                        @click="showAddModal = true"
                        icon="Plus"
                        >{{ $t("Create New") }}
                    </el-button>
                </div>
            </div>

            <div class="fs_table_container">
                <div class="fs_table_header">
                    <div class="fs_bulk_actions">
                        <div class="fs-controls-bulk-actions">
                            <BulkAction
                                :selected-items="selectedWorkflows"
                                :actions="bulkActions"
                                v-model:selected-action="selectedBulkAction"
                                @apply-action="handleBulkAction"
                            />
                        </div>
                    </div>
                    <div class="fs_search_container">
                        <el-input
                            v-model="search"
                            :placeholder="$t('Enter workflow title to search')"
                            @keyup.enter="handleSearch"
                            @blur="handleSearch"
                            @clear="handleSearch"
                            clearable
                            class="fs_text_input fs_table_search_input"
                        >
                            <template #prefix>
                                <el-icon><Search /></el-icon>
                            </template>
                        </el-input>
                    </div>
                </div>
                <!-- Skeleton on the first load only; later fetches keep the rows under an overlay. -->
                <TableSkeleton v-if="first_loading" :rows="5" :columns="['50px', '90px', 3, '160px', '120px', '160px', '140px', '150px']" />
                <div class="fs_table_wrapper" v-else v-loading="loading">
                    <el-table
                        :data="workflows"
                        row-class-name="fs_table_row"
                        header-row-class-name="fs_table_header_row"
                        cell-class-name="fs_table_cell"
                        header-cell-class-name="fs_table_header_cell"
                        table-layout="fixed"
                        @selection-change="handleSelectionChange"
                        ref="workflowTable"
                    >
                        <el-table-column
                            type="selection"
                            fixed="left"
                            width="50"
                            align="center"
                        ></el-table-column>
                        <el-table-column
                            prop="id"
                            fixed="left"
                            :label="$t('ID')"
                            width="90"
                            sortable
                        >
                            <template #header>
                                <span>{{ $t('ID') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column width="446" :label="$t('Title')">
                            <template #header>
                                {{ $t('Title') }}
                            </template>
                            <template #default="scope">
                                <router-link
                                    class="fs_workflow_title_link"
                                    :to="{
                                        name: 'edit-workflow',
                                        params: { workflow_id: scope.row.id },
                                    }"
                                >
                                    {{ scope.row.title }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="trigger"
                            :label="$t('Trigger')"
                            width="160"
                            sortable
                        >
                            <template #default="scope">
                                {{ scope.row.trigger_human_name || 'N/A' }}
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="status"
                            :label="$t('Status')"
                            width="120"
                            sortable
                        >
                            <template #header>
                                <span>{{ $t('Status') }}</span>
                            </template>
                            <template #default="scope">
                                {{ statusLabel(scope.row.status) }}
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="trigger_type"
                            :label="$t('Trigger Type')"
                            width="160"
                            sortable
                        >
                            <template #header>
                                <span>{{ $t('Trigger Type') }}</span>
                            </template>
                            <template #default="scope">
                                <span>{{ triggerTypeLabel(scope.row.trigger_type) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="last_ran_at"
                            :label="$t('Last Run')"
                            width="140"
                        >
                            <template #default="scope">
                                <span v-if="hasRun(scope.row)" :title="scope.row.last_ran_at">{{ $timeDiff(scope.row.last_ran_at) }}</span>
                                <span v-else class="fs_text_muted">{{ $t('Never') }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column
                            align="center"
                            width="150"
                            :label="$t('Action')">
                            <template #default="scope">
                                <div class="fs-table-action-cell">
                                    <div class="fs_action_button_wrapper">
                                        <router-link
                                            :to="{
                                                name: 'edit-workflow',
                                                params: { workflow_id: scope.row.id },
                                            }"
                                        >
                                            <el-icon> <EditPen /> </el-icon>
                                        </router-link>
                                    </div>

                                    <TableMoreActions
                                        :scope="scope"
                                        :fetching="loading"
                                        :actions="['delete', 'duplicate']"
                                        @delete="deleteWorkflow(scope.row.id)"
                                        @duplicate="duplicateWorkflow(scope.row.id)"
                                        :delete-warning="$t('Are you sure to delete this? All associate data will be deleted')"
                                    />
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="fs_pagination_wrapper" v-if="workflows.length">
                        <div class="fs_pagination_left">
                            <p>Page {{ pagination.current_page }} of {{ Math.ceil(pagination.total / pagination.per_page) }}</p>
                            <pagination
                                @fetch="fetch()"
                                :pagination="pagination"
                                layout="sizes"
                            />
                        </div>
                        <div class="fs_pagination_right">
                            <pagination
                                @fetch="fetch()"
                                :pagination="pagination"
                                :background="true"
                                layout="prev, pager, next"
                            />
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <ProShowcase
            v-else
            :feature="$t('Workflows')"
            :heading="$t('Automate repetitive ticket tasks')"
            :description="$t('workflows_promo_description')"
            :specifics="promoSpecifics"
            utm-content="feature_lock_workflow_automation"
            docs-url="https://docs.fluentsupport.com/workflows-automations"
        >
            <template #preview>
                <div class="fs_pro_preview">
                    <div class="fs_pro_preview_card fs_pro_preview_fields">
                        <div class="fs_pro_preview_title">
                            <span class="fs_pro_preview_checkbox"></span>
                            {{ $t('Workflow') }}
                        </div>
                        <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                            <span class="fs_pro_preview_row_label">{{ $t('When') }}</span>
                            <span class="fs_pro_preview_row_value">{{ $t('New ticket') }}</span>
                        </div>
                        <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                            <span class="fs_pro_preview_row_label">{{ $t('If') }}</span>
                            <span class="fs_pro_preview_chip fs_pro_preview_chip_purple fs_pro_preview_chip_plain">{{ $t('Priority is Critical') }}</span>
                        </div>
                        <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                            <span class="fs_pro_preview_row_label">{{ $t('Then') }}</span>
                            <span class="fs_pro_preview_badge fs_pro_preview_badge_success">{{ $t('Assign to Tier 3') }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </ProShowcase>
        <el-dialog
            v-model="showAddModal"
            :title="$t('Add a New Workflow')"
            width="60%"
            class="fs_dialog fs_dialog_md"
        >
            <el-form :data="new_workflow" label-position="top">
                <el-form-item :label="$t('Your Workflow Name')" class="fs_form_item">
                    <el-input
                        class="fs_text_input fs_text_input_40"
                        type="text"
                        :placeholder="$t('Workflow Name')"
                        v-model="new_workflow.title"
                    />
                </el-form-item>
                <el-form-item :label="$t('Workflow Type')" class="fs_form_item">
                    <el-radio-group v-model="new_workflow.trigger_type">
                        <el-radio value="manual">{{ $t("Manual") }}</el-radio>
                        <el-radio value="automatic">{{ $t("Automatic") }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <div class="fs_workflow_type_info">
                    <div v-if="new_workflow.trigger_type == 'manual'">
                        <div class="fs_workflow_info_header">
                            <span class="fs_workflow_info_header_text">
                                {{ $t('Manual_Workflow_Help') }}
                            </span>
                        </div>
                        <div class="fs_workflow_info_body">
                            <div class="fs_workflow_info_header_text">
                                <span class="fs_workflow_info_header_example_title">{{ $t("Example") }}</span>
                                <span class="fs_workflow_info_header_example_text">
                                    {{ $t('Manual_Workflow_Example') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="new_workflow.trigger_type == 'automatic'">
                        <div class="fs_workflow_info_header">
                            <span class="fs_workflow_info_header_text">
                                {{ $t('Automatic_Workflow_Help') }}
                            </span>

                        </div>
                        <div class="fs_workflow_info_body">
                            <div class="fs_workflow_info_header_text">
                                <span class="fs_workflow_info_header_example_title">{{ $t("Example") }}</span>
                            </div>
                            <span class="fs_workflow_info_header_example_text">
                                {{ $t('Automatic_Workflow_Example') }}
                            </span>
                        </div>
                    </div>
                </div>
            </el-form>
            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button
                        class="fs_outline_btn"
                        @click="() => showAddModal = false">
                        {{ $t("Cancel") }}
                    </el-button>
                    <el-button
                        v-loading="creating"
                        :disabled="creating || !new_workflow.title"
                        type="primary"
                        class="fs_filled_btn"
                        @click="createWorkflow()"
                        >{{ $t("Create Workflow") }}</el-button
                    >
                </span>
            </template>
        </el-dialog>

        <el-dialog
            v-model="showBulkDeleteDialog"
            :title="$t('Confirm Delete')"
            width="400"
            class="fs_dialog"
            :append-to-body="true"
        >
            <div class="fs_dialog_warning_content">
                <i class="el-icon el-message-box__status el-message-box-icon--warning">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                        <path
                            fill="currentColor"
                            d="M512 64a448 448 0 1 1 0 896 448 448 0 0 1 0-896m0 192a58.432 58.432 0 0 0-58.24 63.744l23.36 256.384a35.072 35.072 0 0 0 69.76 0l23.296-256.384A58.432 58.432 0 0 0 512 256m0 512a51.2 51.2 0 1 0 0-102.4 51.2 51.2 0 0 0 0 102.4"
                        />
                    </svg>
                </i>
                <p>{{ bulkDeleteWarning }}</p>
            </div>
            <template #footer>
                <div class="fs_popconfirm_actions">
                    <el-button
                        class="fs_outline_btn"
                        size="small"
                        @click="cancelBulkDelete"
                    >
                        {{ $t('Cancel') }}
                    </el-button>
                    <el-button
                        class="fs_filled_btn"
                        size="small"
                        :loading="deleting"
                        @click="confirmBulkDelete"
                    >
                        {{ $t('Delete') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import TableMoreActions from "@/admin/Components/TableMoreActions";
import BulkAction from "@/admin/Components/BulkAction";
import ProShowcase from "@/admin/Components/ProShowcase.vue";
import TableSkeleton from "@/admin/Components/TableSkeleton.vue";

export default {
    name: "AllWorkflows",
    components: {
        Pagination,
        TableMoreActions,
        BulkAction,
        ProShowcase,
        TableSkeleton,
    },
    data() {
        return {
            workflows: [],
            loading: false,
            first_loading: true,
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
            duplicating: false,
            workflowId: "",
            workflowName: "",
            selectedWorkflows: [],
            selectedBulkAction: "",
            showBulkDeleteDialog: false,
            bulkActionItems: [],
        };
    },
    computed: {
        promoSpecifics() {
            return [
                this.$t('Trigger workflows for new tickets, replies, assignments or closed tickets'),
                this.$t('Set conditions based on priority, product, tags, or inbox'),
                this.$t('Automatically assign, tag, reply to or close tickets')
            ];
        },
        bulkActions() {
            return [
                { value: 'delete', label: 'Delete' }
            ];
        },
        bulkDeleteWarning() {
            const count = this.bulkActionItems ? this.bulkActionItems.length : 0;
            if (!count) {
                return this.$t('No workflows selected for deletion.');
            }
            // translators: %s is the number of workflows selected for deletion
            return this.$_n(
                'Are you sure you want to delete %s workflow? All associated data will be deleted.',
                'Are you sure you want to delete %s workflows? All associated data will be deleted.',
                count
            );
        },
    },
    mounted() {
        if (this.has_pro) {
            this.fetch();
        }
        this.$setTitle(this.$t("Workflows"));
    },
    methods: {
        // Older rows hold MySQL's zero date instead of NULL.
        hasRun(workflow) {
            return !!workflow.last_ran_at && !String(workflow.last_ran_at).startsWith('0000');
        },
        statusLabel(status) {
            const labels = {
                published: this.$t('Published'),
                draft: this.$t('Draft')
            };
            return labels[status] || '';
        },
        triggerTypeLabel(triggerType) {
            const labels = {
                manual: this.$t('Manual'),
                automatic: this.$t('Automatic')
            };
            return labels[triggerType] || '';
        },
        async fetch() {
            this.loading = true;
            await this.$get("workflows", {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search,
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
                    this.first_loading = false;
                });
        },

        async createWorkflow() {
            if (!this.new_workflow.title) {
                this.$notify({
                    type: "error",
                    message: this.$t("Workflow title is required"),
                    position: "bottom-right",
                });
                return false;
            }
            this.creating = true;
            await this.$post("workflows", this.new_workflow)
                .then((response) => {
                    this.$notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                    this.$router.push({
                        name: "edit-workflow",
                        params: { workflow_id: response.workflow.id },
                    });
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.creating = false;
                });
        },

        async duplicateWorkflow(workflowId) {
            this.duplicating = true;
            await this.$post("workflows/duplicate/" + workflowId)
                .then((response) => {
                    this.$notify({
                        type: "success",
                        message: this.$t('Workflow duplicated successfully'),
                        position: "bottom-right",
                    });
                    this.fetch();
                    this.duplicating = false;
                    this.renewOptions("workflows/options");
                })
                .catch((errors) => {
                    this.duplicating = false;
                    this.$handleError(errors);
                })
                .always(() => {
                    this.deleting = false;
                });
        },

        async deleteWorkflow(workflowId) {
            this.deleting = true;
            await this.$del("workflows/" + workflowId)
                .then((response) => {
                    this.$notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                    this.fetch();
                    this.renewOptions("workflows/options");
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.deleting = false;
                });
        },

        handleSearch() {
            this.pagination.current_page = 1;
            this.fetch();
        },

        handleSelectionChange(selection) {
            this.selectedWorkflows = selection;
        },

        handleBulkAction({ action, items }) {
            if (action === 'delete') {
                if (!items || !items.length) {
                    return;
                }
                this.bulkActionItems = items;
                this.showBulkDeleteDialog = true;
            }
        },

        async confirmBulkDelete() {
            if (!this.bulkActionItems || !this.bulkActionItems.length) {
                this.showBulkDeleteDialog = false;
                return;
            }
            this.deleting = true;
            try {
                const workflowIds = this.bulkActionItems.map(item => item.id);
                for (const id of workflowIds) {
                    await this.$del("workflows/" + id);
                }
                this.$notify({
                    type: "success",
                    message: this.$t("Selected workflows deleted successfully"),
                    position: "bottom-right",
                });
                this.selectedWorkflows = [];
                this.selectedBulkAction = "";
                this.showBulkDeleteDialog = false;
                this.bulkActionItems = [];
                await this.fetch();
                this.renewOptions("workflows/options");
            } catch (error) {
                this.$handleError(error);
            } finally {
                this.deleting = false;
            }
        },

        cancelBulkDelete() {
            this.showBulkDeleteDialog = false;
            this.bulkActionItems = [];
        },

        handlePageChange(page) {
            this.pagination.current_page = page;
            this.fetch();
        },

        handlePerPageChange() {
            this.pagination.current_page = 1;
            this.fetch();
        },
    },
};
</script>
