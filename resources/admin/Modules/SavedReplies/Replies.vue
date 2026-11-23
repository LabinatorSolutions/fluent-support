<template>
    <div class="fs_saved_replies">
        <div class="fs_box_wrapper">
            <template v-if="has_pro">
                <div class="fs_component_header">
                    <div class="fs_component_head">
                        <h3 class="fs_page_title">{{ $t("Saved Replies") }}</h3>
                    </div>
                    <div class="fs_box_actions">
                        <el-button
                            :disabled="!has_pro"
                            class="fs_filled_btn"
                            @click="createModal()"
                            icon="Plus"
                        >
                            {{ $t("Create New") }}
                        </el-button>
                    </div>
                </div>
                <div class="fs_table_container">
                    <div class="fs_table_header">
                        <div class="fs_box_actions">
                            <div class="fs_ticket_orders fs_table_search_field">
                                <el-input
                                    @keyup.enter="searchReplies"
                                    clearable
                                    @clear="searchReplies"
                                    :disabled="!has_pro"
                                    :placeholder="$t('Search...')"
                                    v-model="search"
                                    class="fs_text_input fs_table_search_input"
                                >
                                    <template #prefix>
                                        <el-icon class="el-input__icon"><search /></el-icon>
                                    </template>
                                </el-input>
                            </div>
                        </div>
                    </div>
                    <TableSkeleton v-if="first_loading" :rows="5" :columns="[50, 200, 200, 180, 180, 150]" />
                    <div v-else class="fs_table_wrapper" v-loading="loading">
                        <el-table
                            :data="replies"
                            row-class-name="fs_table_row"
                            header-row-class-name="fs_table_header_row"
                            cell-class-name="fs_table_cell"
                            header-cell-class-name="fs_table_header_cell"
                            table-layout="fixed"
                        >
                            <el-table-column
                                width="50"
                                prop="id"
                                :label="$t('ID')"
                            ></el-table-column>
                            <el-table-column
                                prop="title"
                                width="200"
                                :label="$t('Title')"
                            ></el-table-column>
                            <el-table-column
                                width="200"
                                :label="$t('Product')">
                                <template #default="scope">
                                    {{ scope.row.product?.title || '--' }}
                                </template>
                            </el-table-column>
                            <el-table-column
                                width="180"
                                :label="$t('Created By')">
                                <template #default="scope">
                                    {{ scope.row.person?.full_name }}
                                </template>
                            </el-table-column>
                            <el-table-column
                                width="180"
                                :label="$t('Created On')">
                                <template #default="scope">
                                    {{ $timeDiff(scope.row.created_at) }}
                                </template>
                            </el-table-column>
                            <el-table-column
                                align="center"
                                width="150"
                                :label="$t('Action')">
                                <template #default="scope">
                                    <div class="fs-table-action-cell">
                                        <div class="fs_action_button_wrapper">
                                            <el-button
                                                class="fs_action_button"
                                                @click="editModal(scope.row)"
                                                text
                                                icon="EditPen">
                                            </el-button>
                                        </div>

                                        <TableMoreActions
                                            :scope="scope"
                                            :fetching="loading"
                                            @delete="deleteReply(scope.row)"
                                            :delete-warning="$t('replies_delete_warning')"
                                        />
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                    <div class="fs_pagination_wrapper" v-if="replies.length">
                        <span class="fs_pagination_left">
                            <p>Page {{ pagination.current_page }} of {{ Math.ceil(pagination.total / pagination.per_page) }}</p>
                            <pagination
                                @fetch="fetch()"
                                :pagination="pagination"
                                layout="sizes"
                            />
                        </span>
                        <span class="fs_pagination_right">
                            <pagination
                                @fetch="fetch()"
                                :pagination="pagination"
                                :background="true"
                                layout="prev, pager, next"
                            />
                        </span>

                    </div>
                </div>
            </template>
            <ProShowcase
                v-else
                :feature="$t('Saved Replies')"
                :heading="$t('Reply faster with saved responses')"
                :description="$t('saved_replies_promo_description')"
                :specifics="promoSpecifics"
                utm-content="feature_lock_saved_reply_templates"
                docs-url="https://docs.fluentsupport.com/templated-saved-replies"
            >
                <template #preview>
                    <div class="fs_pro_preview">
                        <div class="fs_pro_preview_card fs_pro_preview_fields">
                            <div class="fs_pro_preview_title">
                                <span class="fs_pro_preview_checkbox"></span>
                                {{ $t('Saved reply') }}
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                                <span class="fs_pro_preview_row_label">{{ $t('Reply Title') }}</span>
                                <span class="fs_pro_preview_row_value">{{ $t('Order status') }}</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                                <span class="fs_pro_preview_row_label">{{ $t('Product') }}</span>
                                <span class="fs_pro_preview_chip fs_pro_preview_chip_purple fs_pro_preview_chip_plain">{{ $t('Fluent Forms Pro') }}</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                                <span class="fs_pro_preview_row_label">{{ $t('Insert into') }}</span>
                                <!-- translators: %s: ticket number -->
                                <span class="fs_pro_preview_badge fs_pro_preview_badge_info">{{ $t('Ticket #%s', 1729) }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </ProShowcase>
        </div>

        <el-dialog
            :append-to-body="true"
            :title="
                editing_reply && editing_reply.id
                    ? $t('Edit Reply')
                    : $t('Create New Reply')
            "
            v-model="showModal"
            v-if="editing_reply"
            width="60%"
            class="fs_dialog fs_reply_modal fs_dialog_md"
            @closed="resetEditingReply"
        >
            <el-form label-position="top" :data="editing_reply">
                <el-form-item :label="$t('Title')" class="fs_form_item">
                    <el-input
                        class="fs_text_input fs_text_input_40"
                        type="text"
                        :placeholder="$t('Reply Title')"
                        v-model="editing_reply.title"
                    />
                </el-form-item>
                <el-form-item :label="$t('Content')" class="fs_form_item">
                    <wp-editor :key="editing_reply.id" v-model="editing_reply.content" :show-shortcodes="true" :editor_shortcodes="shortcodes"/>
                </el-form-item>
                <el-form-item :label="$t('Preferred Product')" class="fs_form_item">
                    <el-select
                        :placeholder="$t('Select Product')"
                        class="fs_select_field"
                        v-model="editing_reply.product_id"
                        clearable
                    >
                        <el-option
                            v-for="product in products"
                            :key="product.id"
                            :label="product.title"
                            :value="product.id"
                        ></el-option>
                    </el-select>
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button
                        class="fs_outline_btn"
                        @click="() => showModal = false">
                        {{ $t("Cancel") }}
                    </el-button>

                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        @click="createOrUpdate()"
                        class="fs_filled_btn"
                        >
                        {{
                            (editing_reply && editing_reply.id)
                                ? $t("Update Reply")
                                : $t("Save Reply") }}
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import WpEditor from "../../Pieces/_wp_editor";
import TableMoreActions from "@/admin/Components/TableMoreActions.vue";
import ProShowcase from "@/admin/Components/ProShowcase.vue";
import TableSkeleton from "@/admin/Components/TableSkeleton.vue";

export default {
    name: "SavedReplies",
    components: {
        WpEditor,
        Pagination,
        TableMoreActions,
        ProShowcase,
        TableSkeleton,
    },

    data() {
        return {
            replies: [],
            pagination: {
                current_page: 1,
                per_page: 10,
                total: 0,
            },
            loading: true,
            first_loading: true,
            saving: false,
            editing_reply: false,
            products: this.appVars.support_products,
            search: "",
            shortcodes: {
                "{{customer.first_name}}": "Customer First Name",
                "{{customer.last_name}}": "Customer Last Name",
                "{{customer.full_name}}": "Customer Full Name",
                "{{customer.email}}": "Customer Email",
                "{{customer.title}}": "Customer Title",
                "{{customer.status}}": "Customer Status",
                "{{agent.first_name}}": "Agent First Name",
                "{{agent.last_name}}": "Agent Last Name",
                "{{agent.full_name}}": "Agent Full Name",
                "{{agent.email}}": "Agent Email",
                "{{agent.title}}": "Agent Title",
            },
            showModal: false,
        };
    },

    computed: {
        promoSpecifics() {
            return [
                this.$t('Create templates for frequently asked questions'),
                this.$t('Insert saved replies into tickets in one click'),
                this.$t('Keep responses consistent across your support team')
            ];
        }
    },

    methods: {
        fetch() {
            this.loading = true;
            this.$get("saved-replies", {
                page: this.pagination.current_page,
                per_page: this.pagination.per_page,
                search: this.search,
            })
                .then((response) => {
                    this.replies = response.replies.data;
                    this.pagination.total = response.replies.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                    this.first_loading = false;
                });
        },

        searchReplies() {
            this.pagination.current_page = 1;
            this.fetch();
        },

        createOrUpdate() {
            this.saving = true;
            let method = this.$post;
            let route = "saved-replies";
            if (this.editing_reply.id) {
                method = this.$put;
                route = `saved-replies/${this.editing_reply.id}`;
            }

            const data = {
                ...this.editing_reply,
                product_id: this.editing_reply.product_id || null,
            };

            method(route, data)
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    this.fetch();
                    this.showModal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },

        editModal(reply) {
            this.editing_reply = JSON.parse(JSON.stringify(reply)); 
            const productId = Number(this.editing_reply.product_id);
            if (productId === 0) {
                this.editing_reply.product_id = null;
            }
            this.showModal = true;
        },

        createModal() {
            this.editing_reply = {
                title: "",
                content: "",
                product_id: null,
            };
            this.showModal = true;
        },

        deleteReply(reply) {
            this.$del(`saved-replies/${reply.id}`).then((response) => {
                this.$notify({
                    message: response.message,
                    type: "success",
                    position: "bottom-right",
                });

                this.fetch();
            });
        },

        resetEditingReply() {
            this.editing_reply = false;
        }
    },

    mounted() {
        if (this.has_pro) {
            this.fetch();
        } else {
            this.loading = false;
            this.first_loading = false;
        }
        this.$setTitle("Saved Replies");
    }
};
</script>




