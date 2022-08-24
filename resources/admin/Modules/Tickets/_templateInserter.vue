<template>
    <el-popover
        placement="bottom"
        :width="600"
        trigger="manual"
        :visible="visible"
    >
        <template #reference>
            <el-button @click="initModal()" size="small" type="default">{{$t('Templates')}}</el-button>
        </template>
        <div class="fs_template_inserter">
            <div class="fs_row">
                <div class="fs_half">
                    <el-select @change="searchTemplates()" clearable v-model="selected_product" :placeholder="$t('All')">
                        <el-option v-for="product in products" :label="product.title" :value="product.id" :key="product.id"></el-option>
                    </el-select>
                </div>
                <div class="fs_half">
                    <el-input @keyup.enter="searchTemplates()" :placeholder="$t('Search Replies')" v-model="search" class="input-with-select">
                        <template #append>
                            <el-button @click="searchTemplates()" icon="Search"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>

            <div v-loading="loading" class="doc_blocks fs_saved_blocks">
                <ul class="fs_saved_replies">
                    <li @click="insertReply(reply)" v-for="reply in replies" :key="reply.id">
                        <b>{{reply.title}} - {{ reply.product?.title }}</b>
                        <p>{{getExcerpt(reply.content)}}</p>
                    </li>
                </ul>
                <pagination @fetch="searchTemplates()" :pagination="pagination"/>
            </div>
        </div>
    </el-popover>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'
export default {
    name: 'TemplateInserter',
    components: {
        Pagination
    },
    data() {
        return {
            selected_product: '',
            search: '',
            products: this.appVars.support_products,
            replies: [],
            visible: false,
            loading: false,
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            }
        }
    },
    emits: ['insert'],
    methods: {
        searchTemplates() {
            this.loading = true;
            this.$get('saved-replies', {
                page: this.pagination.current_page,
                product_id: this.selected_product,
                search: this.search,
                per_page: this.pagination.per_page
            })
                .then(response => {
                    window.fst_last_replies = response.replies;
                    window.fst_last_replies.product_id = this.selected_product;
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
        initModal() {
            if(!this.visible) {
                this.visible = true;
                if(!window.fst_last_replies) {
                    this.searchTemplates();
                }
            } else {
                this.visible = false;
            }
        },
        insertReply(reply) {
            this.$emit('insert', reply.content);
            this.visible = false;
        },
        getExcerpt(content, limit = 200) {
            if (!content) {
                return '';
            }
            return content.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "").substring(0, limit) + '...';
        }
    },
    mounted() {
        if(window.fst_last_replies) {
            this.replies = window.fst_last_replies.data;
            this.pagination.total = parseInt(window.fst_last_replies.total);
            this.pagination.current_page = parseInt(window.fst_last_replies.current_page);
            this.this.this.selected_product = parseInt(window.fst_last_replies.product_id);
        }
    }
}
</script>
