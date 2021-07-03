<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Ticket Tags</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button @click="createModal()" type="primary" icon="el-icon-plus" size="small">
                        Add New
                    </el-button>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">
                <el-table stripe :data="tags">
                    <el-table-column width="80" prop="id" label="ID"></el-table-column>
                    <el-table-column prop="title" label="Title"></el-table-column>
                    <el-table-column prop="description" label="Description"></el-table-column>
                    <el-table-column width="120" label="Action">
                        <template #default="scope">
                            <el-button @click="editTagModal(scope.row)" size="mini" type="success"
                                       icon="el-icon-edit"></el-button>
                            <el-button @click="deleteTag(scope.row)" size="mini" type="danger" icon="el-icon-delete"></el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="fetchTags()" :pagination="pagination"/>
                </div>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                <el-skeleton :rows="5" animated/>
            </div>
        </div>

        <el-dialog
            :append-to-body="true"
            :title="(editing_tag && editing_tag.id) ? 'Edit Tag' : 'Create New Ticket Tag'"
            v-model="tag_modal"
            v-if="editing_tag"
            width="60%">

            <el-form label-position="top" :data="editing_tag">
                <el-form-item label="Title">
                    <el-input type="text" placeholder="Product Title" v-model="editing_tag.title"/>
                </el-form-item>
                <el-form-item label="Description">
                    <el-input type="textarea" placeholder="Product Description" v-model="editing_tag.description"/>
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="primary" @click="createOrUpdateTag()">Save</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
export default {
    name: 'TicketTags',
    data() {
        return {
            tags: [],
            pagination: {
                current_page: 1,
                per_page: 10,
                total: 0
            },
            fetching: true,
            saving: false,
            tag_modal: false,
            editing_tag: false
        }
    },
    methods: {
        fetchTags() {
            this.fetching = true;
            this.$get('ticket-tags', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page
            })
                .then(response => {
                    this.tags = response.tags.data;
                    this.pagination.total = response.tags.total;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        createOrUpdateTag() {
            this.saving = true;
            let method = this.$post;
            let route = 'ticket-tags';
            if (this.editing_tag.id) {
                method = this.$put;
                route = `ticket-tags/${this.editing_tag.id}`;
            }

            method(route, {
                ...this.editing_tag
            })
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.getTags();
                    this.tag_modal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });

        },
        editTagModal(tag) {
            this.editing_tag = JSON.parse(JSON.stringify(tag));
            this.tag_modal = true;
        },
        createTagModal() {
            this.editing_tag = {
                title: '',
                description: ''
            }
            this.tag_modal = true;
        },
        deleteTag(tag) {
            const r = confirm("Are you sure, You want to delete this?");

            if (!r) {
                return ;
            }

            this.$del(`ticket-tags/${tag.id}`)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });

                    this.getTags();
                });
        }
    },
    mounted() {
        this.fetchTags();
    }
}
</script>
