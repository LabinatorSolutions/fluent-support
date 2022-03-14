<template>
    <div class="fs_saved_replies">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('Saved Replies')}}</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button :disabled="!has_pro" @click="createModal()" type="primary" icon="Plus" size="medium">
                        {{$t('Create New')}}
                    </el-button>
                </div>
                <div class="fs_box_actions fs_ticket_orders" style="margin-right: 0.7em;">
                    <el-input @keyup.enter="fetch" clearable @clear="fetch" :disabled="!has_pro" size="small"
                              :placeholder="$t('Search Replies')" v-model="search">
                        <template #append>
                            <el-button @click="fetch" :disabled="!has_pro" icon="Search"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>
            <template v-if="has_pro">
                <div v-if="!loading" class="fs_box_body">
                    <el-table stripe :data="replies">
                        <el-table-column width="80" prop="id" :label="$t('ID')"></el-table-column>
                        <el-table-column prop="title" :label="$t('Title')"></el-table-column>
                        <el-table-column width="180" :label="$t('Product')">
                            <template #default="scope">
                                {{scope.row.product?.title}}
                            </template>
                        </el-table-column>
                        <el-table-column width="180" :label="$t('Created By')">
                            <template #default="scope">
                                {{scope.row.person?.full_name}}
                            </template>
                        </el-table-column>
                        <el-table-column width="180" :label="$t('Created On')">
                            <template #default="scope">
                                {{$timeDiff(scope.row.created_at)}}
                            </template>
                        </el-table-column>
                        <el-table-column width="120" :label="$t('Action')">
                            <template #default="scope">
                                <el-button @click="editModal(scope.row)" size="medium"
                                           icon="EditPen" type="text" ></el-button>
                              <el-popconfirm
                                  :confirm-button-text="$t('Yes, Delete this')"
                                  :cancel-button-text="$t('No')"
                                  icon="InfoFilled"
                                  icon-color="red"
                                  :title="$t('replies_delete_warning')"
                                  @confirm="deleteReply(scope.row)"
                              >
                                <template #reference>
                                  <el-button v-loading="loading" style="margin-left: 10px; color: red;" type="text"
                                             size="medium"
                                             icon="Delete"></el-button>
                                </template>
                              </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="fframe_pagination_wrapper">
                        <pagination @fetch="fetch()" :pagination="pagination"/>
                    </div>
                </div>
                <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                    <el-skeleton :rows="5" animated/>
                </div>
            </template>
            <div class="fs_narrow_promo" style="background: white;" v-else>
                <h3>{{$t('create_reply_template')}}</h3>
                <p>{{$t('pro_promo')}}</p>
                <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{$t('Upgrade To Pro')}}</a>
            </div>
        </div>

        <Teleport to="body">
            <modal :show="showModal" @close="showModal = false" :title="(editing_reply && editing_reply.id) ? $t('Edit Reply') : $t('Create New Reply')">
                <template #body>
                    <el-form label-position="top" :data="editing_reply">
                        <el-form-item :label="$t('Title')">
                            <el-input type="text" :placeholder="$t('Reply Title')" v-model="editing_reply.title"/>
                        </el-form-item>
                        <el-form-item :label="$t('Content')">
                            <div class="fc_template_box">
                                <el-dropdown type="primary" trigger="click">
                                    <el-button size="small" type="primary" style="margin-right: .3em;">
                                        {{$t('Shortcodes')}} <el-icon style="vertical-align: middle;"><ArrowDown /></el-icon>
                                    </el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item v-for="(value ,key) in shortcodes" :value="key" @click="insertShortcode">
                                                {{value}}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                            <wp-editor v-model="editing_reply.content"/>
                        </el-form-item>
                        <el-form-item :label="$t('Preferred Product')">
                            <el-select :placeholder="$t('Select Product')" v-model="editing_reply.product_id" clearable size="small">
                                <el-option v-for="product in products" :key="product.id" :label="product.title" :value="product.id"></el-option>
                            </el-select>
                        </el-form-item>
                    </el-form>
                </template>
                <template #footer>
                    <el-button v-loading="saving" :disabled="saving" type="success" @click="createOrUpdate()">{{$t('Save')}}</el-button>
                </template>
            </modal>
        </Teleport>

    </div>
</template>

<script title="text/babel">
import Pagination from '../../Pieces/Pagination'
import WpEditor from '../../Pieces/_wp_editor';
import Modal from "../../Pieces/Modal";

export default {
    name: 'SavedReplies',
    components: {
        WpEditor,
        Pagination,
        Modal,
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
            products: this.appVars.support_products,
            search: '',
            shortcodes: {
                '{{customer.first_name}}' : 'Customer First Name',
                '{{customer.last_name}}' : 'Customer Last Name',
                '{{customer.full_name}}' : 'Customer Full Name',
                '{{customer.email}}' : 'Customer Email',
                '{{customer.title}}' : 'Customer Title',
                '{{customer.status}}' : 'Customer Status',
                '{{agent.first_name}}' : 'Agent First Name',
                '{{agent.last_name}}' : 'Agent Last Name',
                '{{agent.full_name}}' : 'Agent Full Name',
                '{{agent.email}}' : 'Agent Email',
                '{{agent.title}}' : 'Agent Title'
            },
            showModal: false
        }
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('saved-replies', {
                page: this.pagination.current_page,
                per_page: this.pagination.per_page,
                search: this.search
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
                    this.fetch();
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
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
            this.showModal = true;
        },
        createModal() {
            this.editing_reply = {
                title: '',
                content: '',
                product_id: ''
            }
            this.showModal = true;
        },
        deleteReply(reply) {
            this.$del(`saved-replies/${reply.id}`)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });

                    this.fetch();
                });
        },
        insertShortcode(content) {
            let tinyInstance = tinyMCE.editors[wpActiveEditor];
            this.editing_reply.content = tinyInstance.setContent(this.editing_reply.content + content.target._value);
        }
    },
    mounted() {
        if(this.has_pro) {
            this.fetch();
        }
        this.$setTitle('Saved Replies');

    }

}
</script>
