<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('Ticket Tags') }}</h3>
                </div>
                <div v-if="has_pro" class="fs_box_actions">
                    <el-button @click="createTagModal()" type="primary" icon="Plus" size="small">
                        {{ $t('Add New') }}
                    </el-button>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-input @keyup.enter="fetchTags" clearable @clear="fetchTags" :disabled="!has_pro" size="small"
                              :placeholder="$t('Search Tags')" v-model="search">
                        <template #append>
                            <el-button @click="fetchTags" :disabled="!has_pro" icon="Search"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>
            <template v-if="has_pro">
                <div v-if="!fetching" class="fs_box_body">
                    <el-table stripe :data="tags">
                        <el-table-column width="80" prop="id" :label="$t('ID')"></el-table-column>
                        <el-table-column prop="title" :label="$t('Title')"></el-table-column>
                        <el-table-column prop="description" :label="$t('Description')"></el-table-column>
                        <el-table-column :label="$t('Tickets')">
                            <template #default="scope">
                                <router-link :to="{name: 'tickets', query: {tags: [scope.row.id]}}">
                                    {{ scope.row.count }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column width="120" :label="$t('Action')">
                            <template #default="scope">
                                <el-button @click="editTagModal(scope.row)" type="text"
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
                                        <el-button v-loading="fetching" style="margin-left: 10px; color: red;"
                                                   type="text"
                                                   icon="Delete"></el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="fframe_pagination_wrapper">
                        <pagination @fetch="fetchTags()" :pagination="pagination"/>
                    </div>
                </div>
                <div style="background: white;" class="fs_box_body" v-else>
                    <el-skeleton class="fs_box_wrapper" :rows="5" animated/>
                </div>
            </template>
            <div class="fs_narrow_promo" v-else>
                <h3>{{ $t('segment_ticket_by_tags') }}</h3>
                <p>{{ $t('pro_promo') }}</p>
                <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{ $t('Upgrade To Pro') }}</a>
            </div>
        </div>

        <el-dialog
            :append-to-body=true
            :title="(editing_tag && editing_tag.id) ? $t('Edit Tag') : $t('Create New Ticket Tag')"
            v-model="tag_modal"
            v-if="editing_tag"
            width="60%">

            <el-form label-position="top" :data="editing_tag">
                <el-form-item :label="$t('Title')">
                    <el-input type="text" :placeholder="$t('Tag Title')" v-model="editing_tag.title"/>
                </el-form-item>
                <el-form-item :label="$t('Description')">
                    <el-input type="textarea" :placeholder="$t('Tag Description')" v-model="editing_tag.description"/>
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="success"
                             @click="createOrUpdateTag()">{{ $t('Save') }}</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";

export default {
    name: 'TicketTags',
    components: {Pagination},
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
            editing_tag: false,
            search: ''
        }
    },
    methods: {
        fetchTags() {
            this.fetching = true;
            this.$get('ticket-tags', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search
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
                    this.fetchTags();
                    this.tag_modal = false;
                    this.renewOptions('ticket-tags/options');
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
            this.$del(`ticket-tags/${tag.id}`)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.fetchTags();
                    this.renewOptions('ticket-tags/options');
                });
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetchTags();
        }
    }
}
</script>
