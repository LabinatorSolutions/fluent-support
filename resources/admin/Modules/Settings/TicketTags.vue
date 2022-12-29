<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("Ticket Tags") }}</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button
                        v-if="has_pro"
                        @click="createTagModal()"
                        type="primary"
                        icon="Plus"
                    >
                        {{ translate("Add New") }}
                    </el-button>

                    <div class="fs_ticket_orders" style="margin-left: 10px">
                        <el-input
                            @keyup.enter="fetchTags"
                            clearable
                            @clear="fetchTags"
                            :disabled="!has_pro"
                            :placeholder="translate('Search Tags')"
                            v-model="search"
                        >
                            <template #append>
                                <el-button
                                    @click="fetchTags"
                                    :disabled="!has_pro"
                                    icon="Search"
                                ></el-button>
                            </template>
                        </el-input>
                    </div>
                </div>
            </div>
            <template v-if="has_pro">
                <div v-if="!fetching" class="fs_box_body">
                    <el-table stripe :data="tags">
                        <el-table-column
                            width="80"
                            prop="id"
                            :label="translate('ID')"
                        ></el-table-column>
                        <el-table-column
                            prop="title"
                            :label="translate('Title')"
                        ></el-table-column>
                        <el-table-column
                            prop="description"
                            :label="translate('Description')"
                        ></el-table-column>
                        <el-table-column :label="translate('Tickets')">
                            <template #default="scope">
                                <router-link
                                    :to="{
                                        name: 'tickets',
                                        query: { tags: [scope.row.id] },
                                    }"
                                >
                                    {{ scope.row.count }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column width="120" :label="$t('Action')">
                            <template #default="scope">
                                <el-button class="fs_action_button" @click="editTagModal(scope.row)" text
                                           icon="EditPen"></el-button>
                                <el-popconfirm
                                    :confirm-button-text="$t('Yes, Delete this')"
                                    :cancel-button-text="$t('No')"
                                    icon="InfoFilled"
                                    icon-color="red"
                                    :title="$t('tag_delete_warning')"
                                    @confirm="deleteTag(scope.row)"
                                >
                                    <template #reference>
                                        <el-button class="fs_action_button" v-loading="fetching" style="margin-left: 10px; color: red;"
                                                   text
                                                   icon="Delete"></el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="fframe_pagination_wrapper">
                        <pagination
                            @fetch="fetchTags()"
                            :pagination="pagination"
                        />
                    </div>
                </div>
                <div style="background: white" class="fs_box_body" v-else>
                    <el-skeleton class="fs_box_wrapper" :rows="5" animated />
                </div>
            </template>
            <div class="fs_narrow_promo" v-else>
                <h3>{{ translate("segment_ticket_by_tags") }}</h3>
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
            :append-to-body="true"
            :title="
                editing_tag && editing_tag.id
                    ? translate('Edit Tag')
                    : translate('Create New Ticket Tag')
            "
            v-model="tag_modal"
            v-if="editing_tag"
            width="60%"
        >
            <el-form label-position="top" :data="editing_tag">
                <el-form-item :label="translate('Title')">
                    <el-input
                        type="text"
                        :placeholder="translate('Tag Title')"
                        v-model="editing_tag.title"
                    />
                </el-form-item>
                <el-form-item :label="translate('Description')">
                    <el-input
                        type="textarea"
                        :placeholder="translate('Tag Description')"
                        v-model="editing_tag.description"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        type="success"
                        @click="createOrUpdateTag()"
                        >{{ translate("Save") }}</el-button
                    >
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import { onMounted, reactive, toRefs } from "vue";
import { useFluentHelper, useNotify } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "TicketTags",
    components: { Pagination },

    setup() {

        const {
            get,
            post,
            put,
            del,
            handleError,
            renewOptions,
            has_pro,
            setTitle,
            translate
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            tags: [],
            pagination: {
                current_page: 1,
                per_page: 10,
                total: 0,
            },
            fetching: true,
            saving: false,
            tag_modal: false,
            editing_tag: false,
            search: "",
        });

        const fetchTags = async() => {
            state.fetching = true;
            await get("ticket-tags", {
                per_page: state.pagination.per_page,
                page: state.pagination.current_page,
                search: state.search,
            })
                .then((response) => {
                    state.tags = response.tags.data;
                    state.pagination.total = response.tags.total;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };

        const createOrUpdateTag = () => {
            state.saving = true;
            let method = post;
            let route = "ticket-tags";
            if (state.editing_tag.id) {
                method = put;
                route = `ticket-tags/${state.editing_tag.id}`;
            }

            method(route, {
                ...state.editing_tag,
            })
                .then((response) => {
                    
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    fetchTags();
                    state.tag_modal = false;
                    renewOptions("ticket-tags/options");
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });
        };

        const editTagModal = (tag) => {
            state.editing_tag = JSON.parse(JSON.stringify(tag));
            state.tag_modal = true;
        };

        const createTagModal = () => {
            state.editing_tag = {
                title: "",
                description: "",
            };
            state.tag_modal = true;
        };

        const deleteTag = (tag) => {
            del(`ticket-tags/${tag.id}`).then((response) => {
                notify({
                    message: response.message,
                    type: "success",
                    position: "bottom-right",
                });
                fetchTags();
                renewOptions("ticket-tags/options");
            });
        };

        onMounted(() => {
            if (has_pro) {
                fetchTags();
            }
            setTitle("Ticket Tags");
        });

        return {
            ...toRefs(state),
            translate,
            fetchTags,
            createOrUpdateTag,
            editTagModal,
            createTagModal,
            deleteTag
        }
    },
    // data() {
    //     return {
    //         tags: [],
    //         pagination: {
    //             current_page: 1,
    //             per_page: 10,
    //             total: 0
    //         },
    //         fetching: true,
    //         saving: false,
    //         tag_modal: false,
    //         editing_tag: false,
    //         search: ''
    //     }
    // },
    // methods: {
    //     fetchTags() {
    //         this.fetching = true;
    //         this.$get('ticket-tags', {
    //             per_page: this.pagination.per_page,
    //             page: this.pagination.current_page,
    //             search: this.search
    //         })
    //             .then(response => {
    //                 this.tags = response.tags.data;
    //                 this.pagination.total = response.tags.total;
    //             })
    //             .catch(errors => {
    //                 this.$handleError(errors);
    //             })
    //             .always(() => {
    //                 this.fetching = false;
    //             });
    //     },
    //     createOrUpdateTag() {
    //         this.saving = true;
    //         let method = this.$post;
    //         let route = 'ticket-tags';
    //         if (this.editing_tag.id) {
    //             method = this.$put;
    //             route = `ticket-tags/${this.editing_tag.id}`;
    //         }

    //         method(route, {
    //             ...this.editing_tag
    //         })
    //             .then(response => {
    //                 this.$notify({
    //                     message: response.message,
    //                     type: 'success',
    //                     position: 'bottom-right'
    //                 });
    //                 this.fetchTags();
    //                 this.tag_modal = false;
    //                 this.renewOptions('ticket-tags/options');
    //             })
    //             .catch((errors) => {
    //                 this.$handleError(errors);
    //             })
    //             .always(() => {
    //                 this.saving = false;
    //             });

    //     },
    //     editTagModal(tag) {
    //         this.editing_tag = JSON.parse(JSON.stringify(tag));
    //         this.tag_modal = true;
    //     },
    //     createTagModal() {
    //         this.editing_tag = {
    //             title: '',
    //             description: ''
    //         }
    //         this.tag_modal = true;
    //     },
    //     deleteTag(tag) {
    //         this.$del(`ticket-tags/${tag.id}`)
    //             .then(response => {
    //                 this.$notify({
    //                     message: response.message,
    //                     type: 'success',
    //                     position: 'bottom-right'
    //                 });
    //                 this.fetchTags();
    //                 this.renewOptions('ticket-tags/options');
    //             });
    //     }
    // },
    // mounted() {
    //     if (this.has_pro) {
    //         this.fetchTags();
    //     }
    //     this.$setTitle('Ticket Tags');
    // }
};
</script>

<style scoped>
.fs_action_button{
    padding: 0px;
}
</style>

