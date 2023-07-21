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
                                        :class="
                                            'fs_agent_avatar_upload fs_agent_avatar_upload-' +
                                            scope.row.id
                                        "
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
                                                    :action="
                                                        upload_url +
                                                        scope.row.id +
                                                        `/avatar`
                                                    "
                                                    :on-success="
                                                        handleAvatarSuccess
                                                    "
                                                    :on-error="
                                                        handleAvatarError
                                                    "
                                                    :headers="requestHeaders"
                                                    :show-file-list="false"
                                                    drag
                                                >
                                                    {{
                                                        translate(
                                                            "Upload a Custom Picture"
                                                        )
                                                    }}
                                                </el-upload>
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="scope.row.avatar"
                                            >
                                                <!--                                                    Reset To Default Gravatar-->
                                                <el-popconfirm
                                                    confirm-button-text="Yes"
                                                    cancel-button-text="No"
                                                    title="Reset to gravatar?"
                                                    @confirm="
                                                        confirmResetProfile(
                                                            scope.row
                                                        )
                                                    "
                                                >
                                                    <template #reference>
                                                        {{
                                                            translate(
                                                                "Reset To Default Gravatar"
                                                            )
                                                        }}
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
                        <a :href="scope.row.user_profile">{{
                            scope.row.full_name
                        }}</a>
                    </template>
                </el-table-column>
                <el-table-column :label="translate('Title')">
                    <template #default="scope">
                        <span style="font-size: 14px; color: #56c288">{{
                            scope.row.title
                        }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="translate('Permissions')" width="160">
                    <template #default="scope">
                        <el-tooltip placement="top">
                            <template #content>
                                <p>{{ translate("Assigned Permissions") }}</p>
                                <span
                                    style="display: block"
                                    v-for="permission in scope.row.permissions"
                                    >{{ readable(permission) }}</span
                                >
                            </template>
                            <el-button type="default" size="small"
                                >{{ scope.row.permissions.length }}
                                {{ translate("Permissions") }}</el-button
                            >
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

            <div class="fframe_pagination_wrapper">
                <pagination @fetch="fetchAgents()" :pagination="pagination" />
            </div>
        </div>
        <div
            style="padding: 20px; background: white"
            class="fs_box_body"
            v-else
        >
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

                <el-form-item :label="translate('Permissions')">
                    <el-checkbox-group
                        class="fs_permission_groups"
                        v-model="editing_agent.permissions"
                    >
                        <div
                            v-for="permissionSet in permissions"
                            class="fs_each_permission_set"
                        >
                            <h4 style="font-size: 15px">
                                {{ permissionSet.title }}
                            </h4>
                            <el-checkbox
                                v-for="(
                                    permissionLabel, permissionkey
                                ) in permissionSet.permissions"
                                :label="permissionkey"
                                :key="permissionkey"
                                >{{ permissionLabel }}</el-checkbox
                            >
                        </div>
                    </el-checkbox-group>
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
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        type="success"
                        @click="createOrUpdateAgent()"
                    >
                        <span v-if="editing_agent.id">{{
                            translate("Update")
                        }}</span>
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
import { onMounted, reactive, toRefs } from "vue";
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
        });

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

            state.agents[index].photo = URL.createObjectURL(file.raw);

            notify({
                type: "success",
                message: "Profile picture has been updated successfully",
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
            await post("agents/" + row.id + "/reset_avatar")
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
