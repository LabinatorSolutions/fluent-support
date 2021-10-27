<template>
    <el-popover
        placement="left"
        :width="700"
        trigger="manual"
        v-model:visible="visible"
    >
        <template #reference>
            <el-button @click="initModal()" size="mini" type="default">{{$t('Templates')}}</el-button>
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
                            <el-button @click="searchTemplates()" icon="el-icon-search"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>

            <div class="doc_blocks">
                <ul>
                    <li @click="insertReply(reply)" v-for="reply in replies" :key="reply.id">{{reply.title}} - {{ reply.product?.title }}</li>
                </ul>
            </div>
        </div>
    </el-popover>
</template>

<script type="text/babel">
export default {
    name: 'TemplateInserter',
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
                per_page: this.pagination.per_page,
                product_id: this.product_id,
                search: this.search
            })
                .then(response => {
                    window.fst_last_replies = response.replies.data;
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
        }
    },
    mounted() {
        if(window.fst_last_replies) {
            this.replies = window.fst_last_replies;
        }
    }
}
</script>
