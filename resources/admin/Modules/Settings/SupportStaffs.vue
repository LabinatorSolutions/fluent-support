<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t("Support Staff") }}</h3>
            </div>
        </div>
        <div v-if="!loading" class="fs_table_container">
            <div class="fs_table_header">
                <div class="fs_box_actions">
                    <div class="fs_ticket_orders fs_table_search_field">
                        <el-input
                            @keyup.enter="fetchAgents"
                            clearable
                            @clear="fetchAgents"
                            size="small"
                            :placeholder="$t('Search Agents')"
                            v-model="search"
                            class="fs_text_input fs_table_search_input"
                        >
                            <template #prefix>
                                <el-icon class="el-input__icon"><search /></el-icon>
                            </template>
                        </el-input>
                    </div>
                    <el-button
                        @click="createStaffModal()"
                        class="fs_filled_btn"
                        icon="Plus"
                    >
                        {{ $t("Add New") }}
                    </el-button>
                </div>
            </div>
            <div class="fs_table_wrapper fs_support_staffs_table">
                <el-table
                    :data="agents"
                    row-class-name="fs_table_row"
                    header-row-class-name="fs_table_header_row"
                    cell-class-name="fs_table_cell"
                    header-cell-class-name="fs_table_header_cell"
                >
                    <template #empty>
                        <div class="fs_table_empty_state">
                            <el-empty
                                :description="$t('No Support Staff Found')"
                                :image-size="130"
                            >
                                <template #image>
                                    <TableEmptyStateImage />
                                </template>
                            </el-empty>
                        </div>
                    </template>
                    <el-table-column :label="$t('Staff')" width="220">
                        <template #default="scope">
                            <div
                                class="fs_as_profile_picture"
                                @mouseover="showIcon(scope.row.id)"
                                @mouseout="hideIcon(scope.row.id)"
                            >
                                <div class="fs_agent_avatar">
                                    <el-dropdown
                                        trigger="click"
                                        :hide-on-click="false"
                                        placement="bottom-start"
                                    >
                                        <el-icon
                                            :class=" 'fs_agent_avatar_upload fs_agent_avatar_upload-' + scope.row.id "
                                        >
                                            <Camera />
                                        </el-icon>
                                        <template #dropdown>
                                            <el-dropdown-menu
                                                class="fs_global_dropdown <>fs-as-avatar-actions</>"
                                            >
                                                <el-dropdown-item>
                                                    <el-upload
                                                        class="fs_avatar_uploader"
                                                        :action="upload_url + 'avatar/' + scope.row.id"
                                                        :on-success="handleAvatarSuccess"
                                                        :on-error="handleAvatarError"
                                                        :headers="requestHeaders"
                                                        :show-file-list="false"
                                                    >
                                                        {{ $t("Upload a Custom Picture") }}
                                                    </el-upload>
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="scope.row.avatar"
                                                >
                                                    <!-- Reset To Default Gravatar -->
                                                    <el-popconfirm
                                                        confirm-button-text="Yes"
                                                        cancel-button-text="No"
                                                        :title="$t('Reset to gravatar?')"
                                                        @confirm="confirmResetProfile(scope.row)"
                                                    >
                                                        <template #reference>
                                                            {{ $t("Reset To Default Gravatar") }}
                                                        </template>
                                                    </el-popconfirm>
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                    <div class="fs_agent_avatar_cell">
                                        <img
                                            v-if="scope.row.photo"
                                            :src="scope.row.photo"
                                            class="avatar"
                                        />
                                        <div class="fs_agent_info">
                                            <a
                                                :href="scope.row.user_profile"
                                                :title="scope.row.full_name"
                                                class="fs_agent_name"
                                            >{{ scope.row.full_name }}</a>
                                            <span>#{{ scope.row.id }}</span>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('Title')">
                        <template #default="scope">
                            <span>{{ scope.row.title }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('Permissions')" width="160">
                        <template #default="scope">
                            <el-tooltip placement="top">
                                <template #content>
                                    <p>{{ $t("Assigned Permissions") }}</p>
                                    <span
                                        style="display: block"
                                        v-for="permission in scope.row.permissions">
                                        {{ readable(permission) }}
                                    </span>
                                </template>
                                <el-button class="fs_agent_permissions" type="default" size="small">
                                    {{ scope.row.permissions.length }}
                                    {{ $t("Permissions") }}
                                </el-button>
                            </el-tooltip>
                        </template>
                    </el-table-column>
                    <el-table-column
                        :label="$t('Replies')"
                        prop="replies_count"
                        width="120"
                    />
                    <el-table-column
                        :label="$t('Interactions')"
                        prop="interactions_count"
                        width="120"
                    />
                    <el-table-column width="120" :label="$t('Actions')">
                        <template #default="scope">
                            <div class="fs-table-action-cell">
                                <div class="fs_action_button_wrapper">
                                    <el-button
                                        class="fs_support_action_btn fs_action_button"
                                        @click="initEdit(scope.row)"
                                        text
                                        icon="EditPen"
                                    />
                                </div>
                                <TableMoreActions
                                    :scope="scope.row"
                                    :fetching="loading"
                                    @delete="deleteAgent(scope.row.id)"
                                    :delete-warning="$t('agent_delete_warning')"
                                />
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div class="fs_pagination_wrapper" v-if="agents.length">
                <span class="fs_pagination_left">
                    <p>
                        Page {{ pagination.current_page }} of
                        {{ Math.ceil(pagination.total / pagination.per_page) }}
                    </p>
                    <pagination
                        @fetch="fetchAgents()"
                        :pagination="pagination"
                        layout="sizes"
                    />
                </span>
                <span class="fs_pagination_right">
                    <pagination
                        :hide-on-single-page="true"
                        @fetch="fetchAgents()"
                        :pagination="pagination"
                        :background="true"
                        layout="prev, pager, next"
                    />
                </span>
            </div>
        </div>
        <div style="padding: 20px; background: white" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated />
        </div>

        <el-dialog
            :append-to-body="true"
            :title="
                editing_agent && editing_agent.id
                    ? $t('Edit Staff')
                    : $t('Add New Support Staff')
            "
            v-model="agent_modal"
            v-if="editing_agent"
            width="60%"
            @close="resetAgentModal()"
            class="fs_dialog"
        >
            <el-form label-position="top" :data="editing_agent">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('First Name')" class="fs_form_item">
                            <el-input
                                class="fs_text_input fs_text_input_40"
                                type="text"
                                :placeholder="$t('First Name')"
                                v-model="editing_agent.first_name"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('Last Name')" class="fs_form_item">
                            <el-input
                                class="fs_text_input fs_text_input_40"
                                type="text"
                                :placeholder="$t('Last Name')"
                                v-model="editing_agent.last_name"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('Email')" class="fs_form_item">
                            <el-input
                                class="fs_text_input fs_text_input_40"
                                :disabled="editing_agent.id"
                                type="email"
                                :placeholder="$t('Agent Email')"
                                v-model="editing_agent.email"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('Title')" class="fs_form_item">
                            <el-input
                                class="fs_text_input fs_text_input_40"
                                type="text"
                                :placeholder="$t('agent_title')"
                                v-model="editing_agent.title"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="telegram_integration" v-if="integrationSettings.includes('telegram_settings')">
                    <h3>{{ $t("Telegram Integration") }}</h3>
                    <el-form-item :label="$t('Telegram Chat ID')" class="fs_form_item">
                        <el-input
                            class="fs_text_input fs_text_input_40"
                            text
                            :placeholder="$t('Telegram Chat ID')"
                            v-model="editing_agent.telegram_chat_id"
                        />
                    </el-form-item>
                </div>

                <div class="slack_integration" v-if="integrationSettings.includes('slack_settings')">
                    <h3>{{ $t("Slack Integration") }}</h3>
                    <el-form-item :label="$t('Slack User ID')" class="fs_form_item">
                        <el-input
                            class="fs_text_input fs_text_input_40"
                            text
                            :placeholder="$t('Slack User ID')"
                            v-model="editing_agent.slack_user_id"
                        />
                    </el-form-item>
                </div>

                <div class="twilio_integration" v-if="integrationSettings.includes('twilio_settings')">
                    <h3>{{ $t("Twilio Integration") }}</h3>
                    <el-form-item :label="$t('WhatsApp Phone Number')" class="fs_form_item">
                        <el-input
                            class="fs_text_input fs_text_input_40"
                            text
                            :placeholder="$t('Ex: +123456789')"
                            v-model="editing_agent.whatsapp_number"
                        />
                    </el-form-item>
                </div>

                <hr class="fs_divider" />

                <!-- Permissions Tree -->
                <el-form-item :label="$t('Permissions')" class="fs_form_item">
                    <el-tree
                        :data="treeData"
                        show-checkbox
                        node-key="id"
                        :props="defaultProps"
                        default-expand-all
                        :default-checked-keys="editing_agent.permissions"
                        @check-change="handleCheckChange"
                    >
                        <template #default="{ node, data }">
                        <span class="custom-tree-node">
                            <span v-if="node.label === 'Draft Reply'">
                                <el-tooltip
                                    width="200"
                                    class="box-item"
                                    popper-class="fs_tree_tooltip"
                                    effect="dark"
                                    placement="top-start"
                                >
                                  <template #content>
                                      If any of the following options are enabled:
                                      <br />
                                      1. Manage Own Tickets,<br />
                                      2. Manage Unassigned Tickets,<br />
                                      3. Manage Other Tickets,<br />
                                      In that case, the draft replies permission will be disabled.
                                  </template>
                                  <span>{{ node.label }}</span>
                                </el-tooltip>
                          </span>
                            <span v-else>{{ node.label }}</span>
                        </span>
                        </template>
                    </el-tree>
                </el-form-item>
            </el-form>
            <hr class="fs_divider" />
            <div class="fs_restriction_section">
                <h4 class="fs_restriction_label">{{$t('Restrictions')}}</h4>
                <div class="fs_restriction_content">
                    <el-form :model="editing_agent" label-position="top" ref="form">
                        <el-form-item class="fs_form_item">
                            <el-checkbox v-model="editing_agent.restrictions.businessBoxRestrictions" @change="manageBusinessBoxRestrictions(restrictBusinessBox)">{{$t('Business Inbox Access Restriction')}}</el-checkbox>
                        </el-form-item>
                        <el-form-item class="fs_form_item" v-if="editing_agent.restrictions.businessBoxRestrictions"  >
                            <el-select class="fs_select_field fs_select_restricted_business_boxes"  v-model="editing_agent.restrictions.restrictedBusinessBoxes" :placeholder="$t('Select')" clearable multiple>
                                <el-option v-for="box in businessBoxes" :key="box.id"  :label="box.name" :value="box.id"></el-option>
                            </el-select>
                        </el-form-item>
                    </el-form>
                </div>
            </div>

            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        @click="createOrUpdateAgent()"
                        class="fs_filled_btn"
                    >
                        <span v-if="editing_agent.id">{{ $t("Update") }}</span>
                        <span v-else>{{ $t("Create") }}</span>
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>
<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import TableMoreActions from "@/admin/Components/TableMoreActions.vue";
import TableEmptyStateImage from "@/admin/Components/TableEmptyStateImage.vue";

export default {
    name: "support-staffs",
    components: {
        Pagination,
        TableMoreActions,
        TableEmptyStateImage
    },
    data() {
        return {
            agents: [],
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0,
            },
            permissions: [],
            loading: false,
            editing_agent: false,
            agent_modal: false,
            saving: false,
            search: "",
            integrationSettings: [],
            upload_url: this.appVars.rest.url + "/agents/",
            requestHeaders: {
                "X-WP-Nonce": this.appVars.rest.nonce,
            },
            show_icon: false,
            restrictBusinessBox: false,
            selectedBusinessBoxes: [],
            businessBoxes: [],
        };
    },
    computed: {
        treeData() {
            return [{
                id: 0,
                label: 'All Permissions',
                children: this.getFormattedPermissionData()
            }];
        },
        defaultProps() {
            return {
                children: 'children',
                label: 'label',
            };
        }
    },
    methods: {
        fetchAgents() {
            this.loading = true;
            this.$get("agents", {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search,
            })
                .then((response) => {
                    this.agents = response.agents.data;
                    this.pagination.total = response.agents.total;
                    this.permissions = response.permissions;
                    this.businessBoxes = response.businessBoxes;
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        fetchSettings() {
            this.$get("settings/integration-settings")
                .then((response) => {
                    this.integrationSettings = response;
                })
                .catch((error) => {
                    this.$handleError(error);
                });
        },
        createStaffModal() {
            this.editing_agent = {
                email: "",
                first_name: "",
                last_name: "",
                title: "",
                permissions: [],
                restrictions: {
                    businessBoxRestrictions: false,
                    restrictedBusinessBoxes: []
                },
            };
            this.agent_modal = true;
        },
        initEdit(agent) {
            this.editing_agent = agent;
            this.agent_modal = true;
        },
        createOrUpdateAgent() {
            this.saving = true;
            let method = this.$post;
            let route = "agents";
            if (this.editing_agent.id) {
                method = this.$put;
                route = `agents/${this.editing_agent.id}`;
            }

            method(route, { ...this.editing_agent })
                .then((response) => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.fetchAgents();
                    this.editing_agent = false;
                    this.agent_modal = false;
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        readable(name) {
            return name.replace("fst_", "").replaceAll("_", " ");
        },
        deleteAgent(agentId) {
            ElMessageBox.prompt(
                this.$t("Please Provide Fallback Agent ID"),
                this.$t("Fallback Agent ID"),
                {
                    confirmButtonText: this.$t("Confirm Delete"),
                    cancelButtonText: this.$t("Cancel"),
                    inputErrorMessage: this.$t("Invalid ID"),
                    customClass: 'fs_text_input fs_text_input_40',
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'el-button--danger fs_filled_btn',
                }
            )
                .then(({ value }) => {
                    this.confirmDeleteAgent(agentId, value);
                })
                .catch(() => {});
        },
        confirmDeleteAgent(agentId, fallbackId) {
            this.$del(`agents/${agentId}`, {
                fallback_agent_id: fallbackId,
            })
                .then((response) => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: "bottom-right",
                    });
                    this.fetchAgents();
                })
                .catch((error) => {
                    this.$handleError(error);
                });
        },
        handleAvatarSuccess(res, file) {
            const id = res.agent.id;
            const index = this.agents.findIndex((row) => row.id === id);
            this.agents[index].photo = res.image;
            this.$notify({
                type: 'success',
                message: res.message,
                position: 'bottom-right'
            })
        },
        handleAvatarError(err) {
            const errorMessage = JSON.parse(err.message);
            this.$notify({
                type: 'error',
                message: errorMessage.message,
                position: "bottom-right",
            });
        },
        showIcon(id) {
            document.querySelector("i.fs_agent_avatar_upload-" + id).style.display = "inline-flex";
        },
        hideIcon(id) {
            document.querySelector("i.fs_agent_avatar_upload-" + id).style.display = "none";
        },
        confirmResetProfile(row) {
            this.loading = true;
            this.$post("agents/reset_avatar/" + row.id)
                .then((response) => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: "bottom-right",
                    });
                    this.fetchAgents();
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        getFormattedPermissionData() {
            return this.permissions.map((permission, key) => {
                let childPermissions = permission.permissions;
                return {
                    id: key,
                    label: permission.title,
                    children: Object.keys(childPermissions).map((permission_id) => {
                        return {
                            id: permission_id,
                            label: childPermissions[permission_id],
                        };
                    })
                };
            });
        },
        getAllChildrenIds(data) {
            let ids = [];
            if (!data.children || data.children.length === 0) return ids;

            for (const child of data.children) {
                ids.push(child.id, ...this.getAllChildrenIds(child));
            }
            return ids;
        },
        handleCheckChange(data, isChecked) {
            const { permissions } = this.editing_agent;
            const allIds = [data.id, ...this.getAllChildrenIds(data)];
            this.editing_agent.permissions = isChecked
                ? [...new Set([...permissions, ...allIds])]
                : permissions.filter(id => !allIds.includes(id));
        },
        resetAgentModal() {
            this.editing_agent = false;
            this.agent_modal = false;
        },
        manageBusinessBoxRestrictions(restrictBusinessBox) {
            if (!restrictBusinessBox) {
                this.editing_agent.restrictions.restrictedBusinessBoxes = [];
            }
        }
    },
    mounted() {
        this.fetchAgents();
        this.fetchSettings();
        this.$setTitle("Support Staff");
    }
};
</script>
