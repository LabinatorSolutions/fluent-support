<template>
    <el-popover
        placement="bottom"
        :width="600"
        trigger="manual"
        :visible="visible"
    >
        <template #reference>
            <el-button @click="initModal()" size="small" type="default">{{translate('Templates')}}</el-button>
        </template>
        <div class="fs_template_inserter">
            <div class="fs_row">
                <div class="fs_half">
                    <el-select @change="searchTemplates()" clearable v-model="selected_product" :placeholder="translate('All')">
                        <el-option v-for="product in products" :label="product.title" :value="product.id" :key="product.id"></el-option>
                    </el-select>
                </div>
                <div class="fs_half">
                    <el-input @keyup.enter="searchTemplates()" :placeholder="translate('Search Replies')" v-model="search" class="input-with-select">
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
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";
import {onMounted, reactive, toRefs} from "vue";
export default {
    name: 'TemplateInserter',
    components: {
        Pagination
    },
    setup(props, context) {
        const {
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
        } = useFluentHelper();
        const emit = context.emit;
        const state = reactive({
            selected_product: '',
            search: '',
            products: appVars.support_products,
            replies: [],
            visible: false,
            loading: false,
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            }
        });

        const searchTemplates = async () => {
            state.loading = true;
            try {
                const response = await get('saved-replies', {
                    page: state.pagination.current_page,
                    product_id: state.selected_product,
                    search: state.search,
                    per_page: state.pagination.per_page
                });
                window.fst_last_replies = response.replies;
                window.fst_last_replies.product_id = state.selected_product;
                window.fst_last_replies.search = state.search;
                state.replies = response.replies.data;
                state.pagination.total = response.replies.total;
            } catch (errors) {
                handleError(errors);
            } finally {
                state.loading = false;
            }
        };

        const initModal = () => {
            if(!state.visible) {
                state.visible = true;
                if(!window.fst_last_replies) {
                    searchTemplates();
                }
            } else {
                state.visible = false;
            }
        };

        const insertReply = (reply) => {
            emit('insert', reply.content);
            state.visible = false;
        };

        const getExcerpt = (content, limit = 200) => {
            if (!content) {
                return '';
            }
            return content.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "").substring(0, limit) + '...';
        };

        onMounted(() => {
            if(window.fst_last_replies) {
                state.selected_product = window.fst_last_replies.product_id;
                state.search = window.fst_last_replies.search;
                state.replies = window.fst_last_replies.data;
                state.pagination.total = window.fst_last_replies.total;
                state.pagination.current_page = window.fst_last_replies.current_page;
            }
        });

        return {
            ...toRefs(state),
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
            searchTemplates,
            initModal,
            insertReply,
            getExcerpt,
        }
    }
}
</script>
