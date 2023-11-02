<template>
    <div class="fs-incoming-webhooks">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("Incoming Webhook") }}</h3>
                </div>
            </div>
            <div
                style="padding: 20px; background: white"
                class="fs_box_body fs_narrow_promo"
                v-if="has_pro && webhook === ''"
            >
                <el-skeleton :rows="5" animated />
            </div>
            <div class="fs_box_body" v-if="has_pro && webhook">
                <el-row>
                    <el-col :span="24">
                        <label class="select-box-label">{{ translate("Select Business Box") }}</label>
                    </el-col>
                    <el-col :span="24" class="select-box">
                        <el-select v-model="mailbox" class="m-2 el-col-23" placeholder="Select"
                                   @change="changeBusinessBox">
                            <el-option
                                v-for="box in mailboxes"
                                :key="box.id"
                                :label="box.name+'( '+box.box_type+' )'"
                                :value="box.id"
                            />
                        </el-select>
                    </el-col>
                </el-row>
                <el-form>
                    <el-form-item size="small">
                        <el-input
                            style="width: 90%; margin-right: 0.2em"
                            :label="translate('Incoming Webhook URL')"
                            v-model="webhook"
                            :readonly="true"
                        />
                        <el-popconfirm
                            :title="
                                translate(
                                    'Are you sure to regenerate the webhook url? If you regenerate the url you have to change all your used webhook'
                                )
                            "
                            @confirm="updateWebhook"
                        >
                            <template #reference>
                                <el-button
                                    size="default"
                                    icon="Refresh"
                                ></el-button>
                            </template>
                        </el-popconfirm>
                    </el-form-item>
                </el-form>
                <span style="color: red">{{
                    translate("to_create_tickets_using_webhooks_notice")
                }}</span>
                <h3>{{ translate(" Fillable Fields") }}</h3>
                <el-table :data="fields">
                    <el-table-column prop="field" label="Field" />
                    <el-table-column prop="field_key" label="Field Key" />
                    <el-table-column prop="type" label="Type" />
                </el-table>
            </div>
        </div>
        <div class="fs_narrow_promo" v-if="!has_pro">
            <h3>
                {{
                    translate("use_webhook_to_create_ticket_from_external_site")
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
</template>

<script type="text/babel">
import { onMounted, reactive, toRefs } from "vue";
import {
    useConfirm,
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "IncomingWebhook",

    setup() {
        const { get, put, translate, handleError, setTitle, has_pro } =
            useFluentHelper();
        const { confirm } = useConfirm();
        const { notify } = useNotify();

        const state = reactive({
            loading: false,
            webhook: "",
            mailbox: "",
            mailboxes: [],
            fields: [
                {
                    field: "Ticket Title(Required)",
                    field_key: "title",
                    type: "Text",
                },
                {
                    field: "Ticket Content(Required)",
                    field_key: "content",
                    type: "Text",
                },
                {
                    field: "Ticket Priority",
                    field_key: "priority",
                    type: "Text",
                },
                {
                    field: "Customer First Name",
                    field_key: "sender[first_name]",
                    type: "Text",
                },
                {
                    field: "Customer Last Name",
                    field_key: "sender[last_name]",
                    type: "Text",
                },
                {
                    field: "Customer Email(Required)",
                    field_key: "sender[email]",
                    type: "Email",
                },
                {
                    field: "Customer Title",
                    field_key: "sender[title]",
                    type: "Text",
                },
                {
                    field: "Customer Address Line 1",
                    field_key: "sender[address_line_1]",
                    type: "Text",
                },
                {
                    field: "Customer Address Line 2",
                    field_key: "sender[address_line_2]",
                    type: "Text",
                },
                {
                    field: "Customer City",
                    field_key: "sender[city]",
                    type: "Text",
                },
                {
                    field: "Customer State",
                    field_key: "sender[state]",
                    type: "Text",
                },
                {
                    field: "Customer Zip",
                    field_key: "sender[zip]",
                    type: "Text",
                },
                {
                    field: "Customer Country",
                    field_key: "sender[country]",
                    type: "Text",
                },
                {
                    field: "Ticket Custom Field",
                    field_key: "custom_fields[custom_field_slug]",
                    type: "Text",
                },
                {
                    field: "Mailbox ID",
                    field_key: "mailbox_id",
                    type: "Number",
                },
            ],
        });

        const fetch = async () => {
            state.loading = true;
            await get("settings/incoming-webhook")
                .then((response) => {
                    state.webhook = response.webhook;
                    state.mailboxes = response.mailboxes;
                    state.mailbox = response.mailbox;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading != state.loading;
                });
        };

        const updateWebhook = async () => {
            state.loading = true;
            await put("settings/incoming-webhook")
                .then((response) => {
                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                    fetch();
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading != state.loading;
                });
        };

        const changeBusinessBox = (value) => {
            confirm({
                message: translate('set_mailbox_webhook'),
                title: 'Warning',
                options: {
                    confirmButtonText: translate('Set Business Mailbox'),
                    cancelButtonText: translate('Cancel'),
                    type: 'warning'
                }
            }).then(() => {
                state.loading = true;
                 put("settings/incoming-webhook", { mailbox_id: value })
                    .then((response) => {
                        notify({
                            type: "success",
                            message: response.message,
                            position: "bottom-right",
                        });
                        fetch();
                    })
                    .catch((errors) => {
                        handleError(errors);
                    })
                    .always(() => {
                        state.loading != state.loading;
                    });
            }).catch(() => {
                // do nothing
            });
        };

        onMounted(() => {
            if (has_pro) {
                fetch();
            }
            setTitle("Incoming Webhook Settings");
        });

        return {
            ...toRefs(state),
            translate,
            fetch,
            updateWebhook,
            changeBusinessBox,
            has_pro,
        };
    },
};
</script>

<style>
    .fs_box_wrapper .fs_box_body .el-form-item__content {
        display: contents;
    }
    .fs_box_body .select-box{
        margin-bottom: 10px;
        padding-right: 4px;
        margin-top: 3px;
    }

    .fs_box_body .select-box-label{
        font-size: medium;
        font-weight: 400;
        padding: 2px;
    }
</style>
