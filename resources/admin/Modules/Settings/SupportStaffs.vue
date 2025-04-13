<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ translate("Support Staff") }}</h3>
            </div>
            <div class="fs_box_actions">
                <el-button
                    @click="createStaffModal()"
                    type="primary"
                    icon="Plus"
                    size="default"
                >
                    {{ translate("Add New") }}
                </el-button>
                <div class="fs_ticket_orders" style="margin-left: 10px">
                    <el-input
                        @keyup.enter="fetchAgents"
                        clearable
                        @clear="fetchAgents"
                        size="small"
                        :placeholder="translate('Search Agents')"
                        v-model="search"
                    >
                        <template #append>
                            <el-button
                                @click="fetchAgents"
                                icon="Search"
                            ></el-button>
                        </template>
                    </el-input>
                </div>
            </div>
        </div>
        <div v-if="!loading" class="fs_box_body">
            <el-table stripe :data="agents">
                <el-table-column
                    :label="translate('ID')"
                    prop="id"
                    width="90"
                />
                <el-table-column :label="translate('Avatar')" width="150">
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
                                            class="fs-as-avatar-actions"
                                        >
                                            <el-dropdown-item>
                                                <el-upload
                                                    class="fs-avatar-uploader"
                                                    :action="upload_url + 'avatar/' + scope.row.id"
                                                    :on-success="handleAvatarSuccess"
                                                    :on-error="handleAvatarError"
                                                    :headers="requestHeaders"
                                                    :show-file-list="false"
                                                >
                                                    {{ translate("Upload a Custom Picture") }}
                                                </el-upload>
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="scope.row.avatar"
                                            >
                                                <!-- Reset To Default Gravatar -->
                                                <el-popconfirm
                                                    confirm-button-text="Yes"
                                                    cancel-button-text="No"
                                                    title="Reset to gravatar?"
                                                    @confirm="confirmResetProfile(scope.row)"
                                                >
                                                    <template #reference>
                                                        {{ translate("Reset To Default Gravatar") }}
                                                    </template>
                                                </el-popconfirm>
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <img
                                    v-if="scope.row.photo"
                                    :src="scope.row.photo"
                                    class="avatar"
                                />
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="translate('Name')" width="120">
                    <template #default="scope">
                        <a :href="scope.row.user_profile">{{ scope.row.full_name }}</a>
                    </template>
                </el-table-column>
                <el-table-column :label="translate('Title')">
                    <template #default="scope">
                        <span style="font-size: 14px; color: #56c288">{{ scope.row.title }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="translate('Permissions')" width="160">
                    <template #default="scope">
                        <el-tooltip placement="top">
                            <template #content>
                                <p>{{ translate("Assigned Permissions") }}</p>
                                <span
                                    style="display: block"
                                    v-for="permission in scope.row.permissions">
                                    {{ readable(permission) }}
                                </span>
                            </template>
                            <el-button type="default" size="small">
                                {{ scope.row.permissions.length }}
                                {{ translate("Permissions") }}
                            </el-button>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="translate('Replies')"
                    prop="replies_count"
                    width="120"
                />
                <el-table-column
                    :label="translate('Interactions')"
                    prop="interactions_count"
                    width="120"
                />
                <el-table-column :label="translate('Actions')">
                    <template #default="scope">
                        <el-button
                            class="fs_support_action_btn"
                            @click="initEdit(scope.row)"
                            text
                            icon="EditPen"
                        />
                        <el-button
                            class="fs_support_action_btn"
                            @click="deleteAgent(scope.row.id)"
                            text
                            icon="Delete"
                            style="color: red"
                        />
                    </template>
                </el-table-column>
            </el-table>

            <div class="fframe_pagination_wrapper" v-if="agents.length">
                <pagination @fetch="fetchAgents()" :pagination="pagination" />
            </div>
        </div>
        <div style="padding: 20px; background: white" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated />
        </div>

        <el-dialog
            :append-to-body="true"
            :title="
                editing_agent && editing_agent.id
                    ? translate('Edit Staff')
                    : translate('Add New Support Staff')
            "
            v-model="agent_modal"
            v-if="editing_agent"
            width="60%"
            @close="resetAgentModal()"
            class="fs_dialog"
        >
            <el-form label-position="top" :data="editing_agent">
                <el-form-item :label="translate('Email')">
                    <el-input
                        :disabled="editing_agent.id"
                        type="email"
                        :placeholder="translate('Agent Email')"
                        v-model="editing_agent.email"
                    />
                </el-form-item>
                <el-form-item :label="translate('First Name')">
                    <el-input
                        type="text"
                        :placeholder="translate('First Name')"
                        v-model="editing_agent.first_name"
                    />
                </el-form-item>
                <el-form-item :label="translate('Last Name')">
                    <el-input
                        type="text"
                        :placeholder="translate('Last Name')"
                        v-model="editing_agent.last_name"
                    />
                </el-form-item>
                <el-form-item :label="translate('Title')">
                    <el-input
                        type="text"
                        :placeholder="translate('agent_title')"
                        v-model="editing_agent.title"
                    />
                </el-form-item>

                <div
                    class="telegram_integration"
                    v-if="integrationSettings.includes('telegram_settings')"
                >
                    <h3>{{ translate("Telegram Integration") }}</h3>
                    <el-form-item :label="translate('Telegram Chat ID')">
                        <el-input
                            text
                            :placeholder="translate('Telegram Chat ID')"
                            v-model="editing_agent.telegram_chat_id"
                        />
                    </el-form-item>
                </div>

                <div
                    class="slack_integration"
                    v-if="integrationSettings.includes('slack_settings')"
                >
                    <h3>{{ translate("Slack Integration") }}</h3>
                    <el-form-item :label="translate('Slack User ID')">
                        <el-input
                            text
                            :placeholder="translate('Slack User ID')"
                            v-model="editing_agent.slack_user_id"
                        />
                    </el-form-item>
                </div>

                <div
                    class="slack_integration"
                    v-if="integrationSettings.includes('twilio_settings')"
                >
                    <h3>{{ translate("Twilio Integration") }}</h3>
                    <el-form-item :label="translate('WhatsApp Phone Number')">
                        <el-input
                            text
                            :placeholder="translate('Ex: +123456789')"
                            v-model="editing_agent.whatsapp_number"
                        />
                    </el-form-item>
                </div>

                <el-form-item :label="translate('Permissions')">
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

                                            1. Manage Own Tickets,
                                            <br />
                                            2. Manage Unassigned Tickets,
                                            <br />
                                            3. Manage Other Tickets ,
                                            <br />
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
            <div class="fs_restriction_section">
                <h4 class="fs_restriction_label">{{translate('Restrictions')}}</h4>
                <div class="fs_restriction_content">
                    <el-form :model="editing_agent" label-position="top" ref="form">
                        <el-form-item>
                            <el-checkbox v-model="editing_agent.restrictions.businessBoxRestrictions" @change="manageBusinessBoxRestrictions(restrictBusinessBox)">{{translate('Business Inbox Access Restriction')}}</el-checkbox>
                        </el-form-item>
                        <el-form-item v-if="editing_agent.restrictions.businessBoxRestrictions"  >
                            <el-select class="fs_select_restricted_business_boxes"  v-model="editing_agent.restrictions.restrictedBusinessBoxes" placeholder="Select" clearable multiple>
                                <el-option v-for="box in businessBoxes" :key="box.id"  :label="box.name" :value="box.id"></el-option>
                            </el-select>
                        </el-form-item>
                    </el-form>
                </div>
            </div>

            <template #footer>
                <span class="dialog-footer">
                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        type="success"
                        @click="createOrUpdateAgent()"
                    >
                        <span v-if="editing_agent.id">{{ translate("Update") }}</span>
                        <span v-else>{{ translate("Create") }}</span>
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import {  ElMessageBox } from 'element-plus'
import {computed, onMounted, reactive, toRefs, watch} from "vue";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "support-staffs",
    components: {
        Pagination,
    },
    setup() {
        const {
            get,
            post,
            put,
            del,
            handleError,
            setTitle,
            translate,
            appVars,
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
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
            upload_url: appVars.rest.url + "/agents/",
            requestHeaders: {
                "X-WP-Nonce": appVars.rest.nonce,
            },
            show_icon: false,
            restrictBusinessBox: false,
            selectedBusinessBoxes: [],
            businessBoxes: [],
        });

        const manageBusinessBoxRestrictions = (restrictBusinessBox) => {
            if (!restrictBusinessBox) {
                state.editing_agent.restrictions.restrictedBusinessBoxes = [];
            }
        };

        const fetchAgents = async () => {
            state.loading = true;
            await get("agents", {
                per_page: state.pagination.per_page,
                page: state.pagination.current_page,
                search: state.search,
            })
                .then((response) => {
                    state.agents = response.agents.data;
                    state.pagination.total = response.agents.total;
                    state.permissions = response.permissions;
                    state.businessBoxes = response.businessBoxes;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const fetchSettings = async () => {
            await get("settings/integration-settings")
                .then((response) => {
                    state.integrationSettings = response;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {});
        };

        const createStaffModal = () => {
            state.editing_agent = {
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
            state.agent_modal = true;
        };

        const initEdit = (agent) => {
            state.editing_agent = agent;
            state.agent_modal = true;
        };

        const createOrUpdateAgent = () => {
            state.saving = true;
            let method = post;
            let route = "agents";
            if (state.editing_agent.id) {
                method = put;
                route = `agents/${state.editing_agent.id}`;
            }

            method(route, {
                ...state.editing_agent,
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    fetchAgents();
                    state.editing_agent = false;
                    state.agent_modal = false;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        const readable = (name) => {
            return name.replace("fst_", "").replaceAll("_", " ");
        };

        const deleteAgent = (agentId) => {
            ElMessageBox.prompt(
                translate("Please Provide Fallback Agent ID"),
                translate("Fallback Agent ID"),
                {
                    confirmButtonText: translate("Confirm Delete"),
                    cancelButtonText: translate("Cancel"),
                    inputErrorMessage: translate("Invalid ID"),
                }
            ) .then(({ value }) => {
                    confirmDeleteAgent(agentId, value);
                })
                .catch(() => {});
        };

        const confirmDeleteAgent = async (agentId, fallbackId) => {
            await del(`agents/${agentId}`, {
                fallback_agent_id: fallbackId,
            })
                .then((response) => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    fetchAgents();
                })
                .catch((errors) => {
                    handleError(errors);
                });
        };

        const handleAvatarSuccess = (res, file) => {
            let id = res.agent.id;
            let index = state.agents.findIndex((row) => row.id === id);

            state.agents[index].photo = res.image;

            notify({
                type: "success",
                message: res.message,
                position: "bottom-right",
            });
        };

        const handleAvatarError = (err, _) => {
            let errorMessage = JSON.parse(err.message);

            notify({
                type: "error",
                message: errorMessage.message,
                position: "bottom-right",
            });
        };

        const showIcon = (id) => {
            document.querySelector(
                "i.fs_agent_avatar_upload-" + id
            ).style.display = "inline-flex";
        };

        const hideIcon = (id) => {
            document.querySelector(
                "i.fs_agent_avatar_upload-" + id
            ).style.display = "none";
        };

        const confirmResetProfile = async (row) => {
            state.loading = !state.loading;
            await post("agents/reset_avatar/" + row.id)
                .then((response) => {
                    notify({
                        type: 'success',
                        message: response.message,
                        position: "bottom-right",
                    });

                    fetchAgents();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = !state.loading;
                });
        };

        onMounted(() => {
            fetchAgents();
            fetchSettings();
            setTitle("Support Staff");
        });

        const defaultProps = {
            children: 'children',
            label: 'label',
        };

        const treeData = computed(() => {
            return [{
                    id: 0,
                    label: 'All Permissions',
                    children: [
                        ...getFormattedPermissionData()
                    ],
                }];
        });

        const getFormattedPermissionData = () => {
            return state.permissions.map((permission, key) => {
                let childPermissions = permission.permissions;
                return {
                    id: key,
                    label: permission.title,
                    children: Object.keys(childPermissions).map((permission_id) => {
                        return {
                            id: permission_id,
                            label: childPermissions[permission_id],
                        }
                    })
                }
            });
        };

        const getAllChildrenIds = (data) => {
            let ids = [];
            if (!data.children || data.children.length === 0) return ids;
            
            for (const child of data.children) {
                ids.push(child.id, ...getAllChildrenIds(child));
            }
            return ids;
        };

        const handleCheckChange = (data, isChecked) => {
            const { permissions } = state.editing_agent;
            const allIds = [data.id, ...getAllChildrenIds(data)];

            state.editing_agent.permissions = isChecked
                ? [...new Set([...permissions, ...allIds])] // Add IDs
                : permissions.filter(id => !allIds.includes(id)); // Remove IDs
        };

        const resetAgentModal = () => {
            state.editing_agent = false;
            state.agent_modal = false;
        };

        return {
            ...toRefs(state),
            translate,
            fetchAgents,
            fetchSettings,
            createStaffModal,
            initEdit,
            createOrUpdateAgent,
            handleAvatarSuccess,
            handleAvatarError,
            readable,
            deleteAgent,
            confirmDeleteAgent,
            showIcon,
            hideIcon,
            confirmResetProfile,
            defaultProps,
            treeData,
            handleCheckChange,
            resetAgentModal,
            manageBusinessBoxRestrictions
        };
    },
};
</script>

<style lang="scss">
table.el-table__header,
table.el-table__body {
    width: 100% !important;
}

.fs_as_profile_picture {
    position: absolute;
    top: 0.3em;

    .fs_agent_avatar {
        img {
            border: none;
            width: 4.3em;
            height: 4.3em;
            border-radius: 50%;
            box-shadow: 0 4px 6px rgb(147 161 175 / 50%);
        }

        .fs_agent_avatar_upload {
            display: none;
            left: 20px;
            top: 23px;
            cursor: pointer;
            z-index: 10000;
            font-size: 22px;
            position: absolute;
            background-color: #f5f7fa;
            border-radius: 32%;
            padding: 3px;
        }
    }

    .fs-avatar-uploader {
        .el-upload-dragger > i {
            display: none;
            font-size: 2.9em;
            color: #fafafa;
            position: absolute;
            top: 0.8em;
            left: 0.8em;
        }

        img {
            border: none;
            width: 7.3em;
            height: 7.3em;
            border-radius: 50%;
            box-shadow: 0 4px 6px rgb(147 161 175 / 50%);
        }

        .avatar-uploader .el-upload {
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
    }
}

.fs-avatar-uploader .el-upload-dragger {
    background-color: unset;
    border: unset;
    border-radius: unset;
    width: unset;
    height: unset;
    text-align: unset;
}

.el-table__row {
    height: 5em;
}

.fs-as-avatar-actions {
    display: flex;
    flex-direction: column;
}
.fs_support_action_btn{
    padding: 0px;
}
</style>
