<template>
    <div class="fs_mailboxes">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("Business Inboxes") }}</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button @click="showNewBusinessModal()" type="primary">
                        {{ translate("Add New Business Inbox") }}
                    </el-button>
                </div>
            </div>
            <div v-if="!fetching" class="">
                <el-row :gutter="30">
                    <el-col
                        v-for="box in mailboxes"
                        :key="box.id"
                        :sm="12"
                        :md="6"
                        :xl="6"
                    >
                        <div class="fs_mail_box">
                            <div class="fs_mail_title">
                                <el-icon class="default_box_icon" v-if="'yes' === box.is_default"><FolderChecked /></el-icon>
                                <h3>{{ box.name }}</h3>
                                <el-dropdown
                                    @command="handleBoxCommand"
                                    class="fs_mail_action"
                                    trigger="click"
                                >
                                    <span class="el-dropdown-link">
                                        <el-icon>
                                            <ArrowDown />
                                        </el-icon>
                                    </span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-if="'yes' !== box.is_default"
                                                :command="{
                                                    type: 'set_default',
                                                    box_id: box.id,
                                                }"
                                                icon="FolderChecked"
                                            >{{ translate("Set as Default") }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="box.tickets_count > 0"
                                                :command="{
                                                    type: 'move',
                                                    box_id: box.id,
                                                }"
                                                icon="EditPen"
                                                >{{ translate("Move Tickets") }}
                                            </el-dropdown-item>

                                            <el-dropdown-item
                                                :command="{
                                                    type: 'delete',
                                                    box_id: box.id,
                                                }"
                                                icon="Delete"
                                                >{{ translate("Delete") }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                            <div class="fs_mail_body">
                                <p>{{ box.email }}</p>
                                <p>
                                    {{ translate("Type") }}: {{ box.box_type }}
                                </p>
                                <p>
                                    {{ translate("Tickets Counts") }}:
                                    {{ box.tickets_count }}
                                </p>
                                <router-link
                                    class="el-button el-button--success el-button--small"
                                    :to="{
                                        name: 'box_settings',
                                        params: { box_id: box.id },
                                    }"
                                >
                                    {{ translate("View Settings") }}
                                </router-link>
                            </div>
                        </div>
                    </el-col>
                </el-row>
            </div>
            <div
                style="padding: 20px; background: white"
                class="fs_box_body"
                v-else
            >
                <el-skeleton class="fs_box_wrapper" :rows="5" animated />
            </div>
        </div>

        <el-dialog
            :title="translate('Add a New Business Inbox')"
            v-model="create_modal"
            width="60%"
            class="fs_dialog"
        >
            <el-form
                v-if="can_create_mailbox"
                :data="new_business"
                label-position="top"
            >
                <el-form-item :label="translate('Inbox Name')">
                    <el-input
                        type="text"
                        v-model="new_business.name"
                    ></el-input>
                </el-form-item>
                <el-form-item :label="translate('Support Inbox Email')">
                    <el-input
                        type="email"
                        v-model="new_business.email"
                    ></el-input>
                    <p>{{ translate("email_can_be_send") }}</p>
                </el-form-item>
                <el-form-item :label="translate('Support Channel')">
                    <el-radio-group v-model="new_business.box_type">
                        <el-radio label="web">{{
                            translate("Web Based")
                        }}</el-radio>
                        <el-radio
                            :disabled="!appVars.has_email_parser"
                            label="email"
                        >
                            {{ translate("email_based_heading") }}
                        </el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <div class="fs_narrow_promo" style="background: white" v-else>
                <h3>{{ translate("shared_inbox_message") }}</h3>
                <p>{{ translate("pro_promo") }}</p>
                  <a
                    target="_blank"
                    rel="noopener"
                    href="https://fluentsupport.com"
                    class="el-button el-button--success"
                >
                    {{ translate("Upgrade To Pro") }}
                </a>
            </div>

            <template #footer>
                <span v-if="can_create_mailbox" class="dialog-footer">
                    <el-button @click="create_modal = false">{{
                        translate("Cancel")
                    }}</el-button>
                    <el-button type="primary" @click="createMailBox()">{{
                        translate("Add Business Inbox")
                    }}</el-button>
                </span>
            </template>
        </el-dialog>

        <el-dialog
            :title="translate('Are You Sure? You can not undo this action.')"
            v-model="deleting_box.show_modal"
            width="60%"
            class="fs_dialog"
        >
            <el-form :data="deleting_box" label-position="top">
                <el-form-item :label="translate('Fallback Business')">
                    <el-select
                        v-model="deleting_box.fallback_box"
                        :placeholder="
                            translate('Select related Product/Service')
                        "
                    >
                        <el-option
                            :disabled="mailbox.id == deleting_box.box_id"
                            v-for="mailbox in mailboxes"
                            :key="mailbox.id"
                            :value="mailbox.id"
                            :label="mailbox.name"
                        ></el-option>
                    </el-select>
                    <p>{{ translate("select_fallback_business") }}</p>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="deleting_box.show_modal = false">{{
                        translate("Cancel")
                    }}</el-button>
                    <el-button
                        v-loading="deleteing"
                        :disabled="deleteing"
                        type="danger"
                        @click="deleteMailBox()"
                        >{{
                            translate("Confirm Delete This Business")
                        }}</el-button
                    >
                </span>
            </template>
        </el-dialog>

        <template v-if="!!change_box">
            <move-ticket
                :mailbox_id="change_box"
                :mailboxes="mailboxes"
                @update_mailbox="fetch()"
                @reset_me="reset_me"
            ></move-ticket>
        </template>
    </div>
</template>

<script type="text/babel">
import MoveTicket from "./MoveTicket";
import { computed, onMounted, reactive, toRefs } from "vue";
import { useRouter } from "vue-router";
import {useConfirm, useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "ChooseMailBox",
    components: {
        MoveTicket,
    },
    setup() {
        const router = useRouter();

        const { get, translate, handleError, setTitle, post, put, del, has_pro } =
            useFluentHelper();
        const { notify } = useNotify();
        const { confirm } = useConfirm();
        const state = reactive({
            fetching: true,
            mailboxes: [],
            create_modal: false,
            new_business: {
                box_type: "web",
                name: "",
                email: "",
                mapped_email: "",
                customer_portal_page_id: "",
            },
            creating: false,
            deleting_box: {
                show_modal: false,
                box_id: "",
                fallback_box: "",
            },
            deleteing: false,
            change_box: "",
            set_default: "",
        });

        const can_create_mailbox = computed(() => {
            if (has_pro) {
                return true;
            }

            if (state.mailboxes.length > 1) {
                return false;
            }

            return true;
        });

        const fetch = async () => {
            state.fetching = true;
            get("mailboxes")
                .then((response) => {
                    state.mailboxes = response.mailboxes;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };

        const update_mailbox = (mailboxes) => {
            state.change_box = "";
            state.mailboxes = mailboxes;
        };
        const showNewBusinessModal = () => {
            state.new_business = {
                box_type: "web",
                name: "",
                email: "",
                mapped_email: "",
                customer_portal_page_id: "",
            };
            state.create_modal = true;
        };
        const createMailBox = () => {
            state.creating = true;
            post("mailboxes", {
                business: state.new_business,
            })
                .then((response) => {
                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });

                    if (response.mailbox.box_type == "email") {
                        router.push({
                            name: "email_piping",
                            params: { box_id: response.mailbox.id },
                        });
                    } else {
                        router.push({
                            name: "box_settings",
                            params: { box_id: response.mailbox.id },
                        });
                    }
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.creating = false;
                });
        };
        const handleBoxCommand = (command) => {
            if (command.type == "delete") {
                state.deleting_box.box_id = command.box_id;
                state.deleting_box.show_modal = true;
            } else if (command.type == "move") {
                state.change_box = command.box_id;
            }else if(command.type == "set_default"){
                state.set_default = command.box_id;
                setDefaultBox();
            }
        };
        const deleteMailBox = () => {
            if (
                !state.deleting_box.fallback_box ||
                !state.deleting_box.box_id
            ) {
                alert(translate("Please provide fallback box"));
                return false;
            }

            state.deleteing = true;
            del(`mailboxes/${state.deleting_box.box_id}`, {
                fallback_id: state.deleting_box.fallback_box,
            })
                .then((response) => {
                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                    fetch();
                    state.deleting_box = {
                        show_modal: false,
                        box_id: "",
                        fallback_box: "",
                    };
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.deleteing = false;
                });
        };
        const reset_me = () => {
            state.change_box = "";
        };

        const setDefaultBox = () => {
            confirm({
                message: translate('default_box_set_warning'),
                title: 'Warning',
                options: {
                    confirmButtonText: translate('Set as Default'),
                    cancelButtonText: translate('Cancel'),
                    type: 'warning'
                }
            }).then(() => {
                put(`mailboxes/${state.set_default}/set_default`)
                    .then((response) => {
                        notify({
                            type: "success",
                            message: response.message,
                            position: "bottom-right",
                        });
                        fetch();
                        state.set_default = "";
                    })
                    .catch((errors) => {
                        handleError(errors);
                    })
                    .always(() => {
                        state.deleteing = false;
                    });
            }).catch(() => {
                // do nothing
            });
        }

        onMounted(() => {
            fetch();
            setTitle("Business Inboxes");
        });

        return {
            ...toRefs(state),
            fetch,
            update_mailbox,
            showNewBusinessModal,
            createMailBox,
            handleBoxCommand,
            deleteMailBox,
            reset_me,
            can_create_mailbox,
            get,
            translate,
            handleError,
            setTitle,
            post,
            del,
        };
    },
};
</script>

<style lang="scss">
.fs_mail_box {
    border: 1px solid #e3e8ed;
    border-radius: 5px;
    margin-top: 20px;
    text-align: center;

    .fs_mail_title {
        border-bottom: 1px solid #e3e8ed;
        background: white;
        padding: 10px 15px;
        text-align: left;
        overflow: hidden;

        h3 {
            float: left;
        }

        .fs_mail_action {
            float: right;
            line-height: 27px;
        }
        .default_box_icon{
            position: relative;
            float: left;
            padding-top: 14px;
            font-size: larger;
            margin-right: 5px;
        }
    }

    .fs_mail_body {
        padding: 15px;
        background: #f7fafc;
    }

    p,
    h3 {
        margin: 5px;
    }

    a {
        text-decoration: none;
    }
}
</style>
