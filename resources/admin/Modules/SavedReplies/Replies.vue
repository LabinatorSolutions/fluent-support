<template>
    <div class="fs_saved_replies">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Saved Replies</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button @click="createModal()" type="primary" icon="el-icon-plus" size="small">
                        Create New
                    </el-button>
                </div>
            </div>
            <div class="fs_box_body">
                <el-table stripe :data="replies">
                    <el-table-column width="80" prop="id" label="ID"></el-table-column>
                    <el-table-column prop="title" label="Title"></el-table-column>
                    <el-table-column width="180" label="Product">
                        <template #default="scope">
                            {{scope.row.product?.title}}
                        </template>
                    </el-table-column>
                    <el-table-column width="180" label="Created By">
                        <template #default="scope">
                            {{scope.row.person?.full_name}}
                        </template>
                    </el-table-column>
                    <el-table-column width="180" label="Created At">
                        <template #default="scope">
                            {{$timeDiff(scope.row.created_at)}}
                        </template>
                    </el-table-column>
                    <el-table-column width="120" label="Action">
                        <template #default="scope">
                            <el-button @click="editModal(scope.row)" size="mini" type="success"
                                       icon="el-icon-edit"></el-button>
                            <el-button @click="deleteReply(scope.row)" size="mini" type="danger" icon="el-icon-delete"></el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="fetch()" :pagination="pagination"/>
                </div>
            </div>
        </div>

        <el-dialog
            :append-to-body="true"
            :title="(editing_reply && editing_reply.id) ? 'Edit Reply' : 'Create new Reply'"
            v-model="edit_modal"
            v-if="edit_modal"
            width="60%">

            <el-form label-position="top" :data="editing_reply">
                <el-form-item label="Title">
                    <el-input type="text" placeholder="Reply Title" v-model="editing_reply.title"/>
                </el-form-item>
                <el-form-item label="Content">
                    <wp-editor v-model="editing_reply.content" />
                </el-form-item>
                <el-form-item label="Prefered Product">
                    <el-select placeholder="Select Product" v-model="editing_reply.product_id" clearable size="small">
                        <el-option v-for="product in products" :key="product.id" :label="product.title" :value="product.id"></el-option>
                    </el-select>
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="primary" @click="createOrUpdate()">Update</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script title="text/babel">
import Pagination from '../../Pieces/Pagination'
import WpEditor from '../../Pieces/_wp_editor';

export default {
    name: 'SavedReplies',
    components: {
        WpEditor,
        Pagination
    },
    data() {
        return {
            replies: [],
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            },
            loading: false,
            saving: false,
            editing_reply: false,
            edit_modal: false,
            products: this.appVars.support_products,
        }
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('saved-replies', {
                page: this.pagination.current_page,
                per_page: this.pagination.per_page
            })
                .then(response => {
                    this.replies = response.replies.data;
                    this.pagination.total = response.replies.total;
                })
                .catch((errors) => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.loading = false;
                });
        },
        createOrUpdate() {
            this.saving = true;
            let method = this.$post;
            let route = 'saved-replies';
            if (this.editing_reply.id) {
                method = this.$put;
                route = `saved-replies/${this.editing_reply.id}`;
            }

            method(route, {
                ...this.editing_reply
            })
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.fetch();
                    this.edit_modal = false;
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
            this.edit_modal = true;
        },
        createModal() {
            this.editing_reply = {
                title: '',
                content: '',
                product_id: ''
            }
            this.edit_modal = true;
        },
        deleteReply(reply) {
            const r = confirm("Are you sure, You want to delete this?");

            if (!r) {
                return ;
            }

            this.$del(`saved-replies/${reply.id}`)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });

                    this.fetch();
                });
        }
    },
    mounted() {
        this.fetch();
    }
}
</script>
