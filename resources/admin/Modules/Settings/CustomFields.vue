<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("Custom Ticket Fields") }}</h3>
                </div>
                <div v-if="has_pro" class="fs_box_actions">
                    <el-button
                        @click="addFieldVisible = true"
                        type="primary"
                        icon="Plus"
                        size="default"
                    >
                        {{ translate("Add New Field") }}
                    </el-button>
                </div>
            </div>
            <template v-if="has_pro">
                <div v-if="!loading" class="fs_box_body">
                    <el-table
                        :empty-text="translate('No Custom Fields Found')"
                        border
                        stripe
                        :data="fields"
                    >
                        <el-table-column :label="translate('Label')" prop="label">
                            <template #default="scope">
                                {{ scope.row.label || scope.row.admin_label }}
                                <el-icon
                                    :title="'Agent Only Field'"
                                    v-if="scope.row.admin_only == 'yes'"
                                    ><Lock
                                /></el-icon>
                                <el-icon
                                    :title="'Conditional Field'"
                                    v-if="scope.row.has_logics == 'yes'"
                                    ><Connection
                                /></el-icon>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="translate('Slug')"
                            prop="slug"
                        ></el-table-column>
                        <el-table-column
                            :label="translate('Type')"
                            prop="type"
                        ></el-table-column>
                        <el-table-column width="160" :label="translate('Actions')">
                            <template #default="scope">
                                <el-button
                                    class="fs_custom_field_action_btn"
                                    text                 
                                    @click="updateFieldModal(scope.$index)"
                                    icon="EditPen"
                                ></el-button>
                                <el-popconfirm
                                    :title="translate('custom_ticket_field_delete')"
                                    @confirm="deleteField(scope.$index)"
                                >
                                    <template #reference>
                                        <el-button
                                            class="fs_custom_field_action_btn"
                                            text
                                            icon="Delete"
                                            style="
                                                color: red;
                                                margin-right: 0.3em;
                                            "
                                        ></el-button>
                                    </template>
                                </el-popconfirm>

                                <el-button-group>
                                    <el-button
                                        @click="
                                            movePosition(scope.$index, 'up')
                                        "
                                        :disabled="scope.$index == 0"
                                        size="small"
                                        icon="ArrowUp"
                                    ></el-button>
                                    <el-button
                                        @click="
                                            movePosition(scope.$index, 'down')
                                        "
                                        :disabled="
                                            scope.$index == fields.length - 1
                                        "
                                        size="small"
                                        icon="ArrowDown"
                                    ></el-button>
                                </el-button-group>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
                <div
                    style="padding: 20px; background: white"
                    class="fs_box_body"
                    v-else
                >
                    <el-skeleton :rows="5" animated />
                </div>
            </template>
            <div class="fs_narrow_promo" v-else>
                <h3>{{ translate("tk_data_collect_using_customfield") }}</h3>
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
            :title="translate('Add New Custom Field')"
            v-model="addFieldVisible"
            :append-to-body="true"
            width="60%"
        >
            <custom-field-form
                form_type="new"
                :field_types="field_types"
                :item="new_item"
            ></custom-field-form>
            <div slot="footer" class="dialog-footer">
                <el-button @click="createField()" type="success" size="small">{{
                    translate("Add")
                }}</el-button>
            </div>
        </el-dialog>

        <el-dialog
            :title="translate('Update Custom Field')"
            v-model="updateFieldVisible"
            :append-to-body="true"
            width="60%"
        >
            <custom-field-form
                v-if="updateFieldVisible"
                :fields="fields"
                form_type="update"
                :field_types="field_types"
                :item="update_field"
            ></custom-field-form>
            <div slot="footer" class="dialog-footer">
                <el-button @click="updateField()" type="success" size="small">{{
                    translate("Update Field")
                }}</el-button>
            </div>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import CustomFieldForm from "./_CustomFieldForm";
import { onMounted, reactive, toRefs } from "vue";
import { useFluentHelper, useNotify } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "CustomFields",
    components: {
        CustomFieldForm,
    },
    setup() {

        const {
            get,
            post,
            translate,
            handleError,
            setTitle,
            has_pro,
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            fields: [],
            loading: false,
            new_item: {},
            field_types: {},
            addFieldVisible: false,
            updateFieldVisible: false,
            update_field: {},
        });

        const fetch = async () => {
            state.loading = true;
            await get("ticket-custom-fields", {
                with: ["field_types"],
            })
                .then((response) => {
                    state.fields = response.fields;
                    state.field_types = response.field_types;
                })
                .catch((error) => {
                    handleError(error);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const saveFields = async () => {
            state.loading = true;
            await post("ticket-custom-fields", {
                fields: JSON.stringify(state.fields),
            })
                .then((response) => {
                    state.fields = response.fields;
                    notify({
                        type: "success",
                        message: response.message,
                        position: "bottom-right",
                    });
                })
                .catch((error) => {
                    handleError(error);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const createField = () => {
            if (!validateField(state.new_item)) {
                return false;
            }
            state.fields.push(state.new_item);
            state.addFieldVisible = false;
            state.new_item = {};
            saveFields();
        };

        const validateField = (item) => {
            if (!item.label) {
                notify({
                    type: "error",
                    message: "Please Provide label",
                    position: "bottom-right",
                });
                return false;
            }
            if (item.options && !item.options.length) {
                notify({
                    type: "error",
                    message: "Please Field Option Values",
                    position: "bottom-right",
                });
                return false;
            }
            return true;
        };

        const updateFieldModal = (fieldIndex) => {
            state.update_field = state.fields[fieldIndex];
            state.updateFieldVisible = true;
        };

        const updateField = () => {
            if (!validateField(state.update_field)) {
                return false;
            }
            state.updateFieldVisible = false;
            state.update_field = {};
            saveFields();
        };

        const deleteField = (fieldIndex) => {
            state.fields.splice(fieldIndex, 1);
            saveFields();
        };

        const movePosition = (fromIndex, type) => {
            let toIndex = fromIndex - 1;
            if (type === "down") {
                toIndex = fromIndex + 1;
            }
            const fields = state.fields;
            const element = fields[fromIndex];
            fields.splice(fromIndex, 1);
            fields.splice(toIndex, 0, element);
            state.fields = fields;
            saveFields();
        };

        onMounted(() => {
            setTitle("Custom Ticket Fields");
            if (has_pro) {
                fetch();
            }
        });

        return {
            ...toRefs(state),
            translate,
            fetch,
            saveFields,
            createField,
            validateField,
            updateFieldModal,
            updateField,
            deleteField,
            movePosition
        }
    },
};
</script>

<style scoped>
.fs_custom_field_action_btn{
    padding: 0px;
}
</style>
