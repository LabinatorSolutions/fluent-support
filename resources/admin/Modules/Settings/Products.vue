<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate("Products") }}</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button
                        @click="createTicketModal()"
                        type="primary"
                        icon="Plus"
                        size="default"
                        >{{ translate("Create New") }}
                    </el-button>

                    <div class="fs_ticket_orders" style="margin-left: 10px">
                        <el-input
                            @keyup.enter="getProducts"
                            clearable
                            @clear="getProducts"
                            size="small"
                            :placeholder="translate('Search Products')"
                            v-model="search"
                        >
                            <template #append>
                                <el-button
                                    @click="getProducts"
                                    icon="Search"
                                ></el-button>
                            </template>
                        </el-input>
                    </div>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">
                <el-table stripe :data="products">
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
                    <el-table-column width="120" :label="translate('Action')">
                        <template #default="scope">
                            <el-button
                                class="fs_products_action_btn"
                                @click="editProductModal(scope.row)"
                                text
                                icon="EditPen"
                            ></el-button>
                            <el-popconfirm
                                :confirm-button-text="translate('Yes, Delete this')"
                                :cancel-button-text="translate('No')"
                                icon="InfoFilled"
                                icon-color="red"
                                :title="translate('product_delete_warning')"
                                @confirm="deleteProduct(scope.row)"
                            >
                                <template #reference>
                                    <el-button
                                        class="fs_products_action_btn"
                                        v-loading="fetching"
                                        style="margin-left: 10px; color: red"
                                        text
                                        icon="Delete"
                                    ></el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="fframe_pagination_wrapper">
                    <pagination
                        @fetch="getProducts()"
                        :pagination="pagination"
                    />
                </div>
            </div>
            <div
                style="padding: 20px; background: white"
                class="fs_box_body"
                v-else
            >
                <el-skeleton :rows="5" animated />
            </div>
        </div>

        <el-dialog
            :append-to-body="true"
            :title="
                editing_product && editing_product.id
                    ? translate('Edit Product')
                    : translate('Create New Supported Product')
            "
            v-model="ticket_modal"
            v-if="editing_product"
            width="60%"
        >
            <el-form label-position="top" :data="editing_product">
                <el-form-item :label="translate('Title')">
                    <el-input
                        text
                        :placeholder="translate('Product Title')"
                        v-model="editing_product.title"
                    />
                </el-form-item>
                <el-form-item :label="translate('Description')">
                    <el-input
                        type="textarea"
                        :placeholder="translate('Product Description')"
                        v-model="editing_product.description"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button
                        v-loading="saving"
                        :disabled="saving"
                        type="success"
                        @click="createOrUpdateProduct()"
                    >
                        <span v-if="editing_product.id">{{
                            translate("Update")
                        }}</span>
                        <span v-else>{{ translate("Create") }}</span>
                    </el-button>
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
    name: "SupportProducts",
    components: {
        Pagination,
    },
    setup() {
        const {
            get,
            post,
            put,
            del,
            handleError,
            setTitle,
            translate
        } = useFluentHelper();

        const { notify } = useNotify();

        const state = reactive({
            fetching: false,
            products: [],
            pagination: {
                current_page: 1,
                per_page: 10,
                total: 0,
            },
            saving: false,
            ticket_modal: false,
            editing_product: false,
            search: "",
        });

        const getProducts = async() => {
            state.fetching = true;
            await get('products', {
                per_page: state.pagination.per_page,
                page: state.pagination.current_page,
                search: state.search
            })
                .then(response => {
                    state.products = response.products.data;
                    state.pagination.total = response.products.total;
                })
                .catch(errors => {
                    handleError(errors);
                })
                .always(() => {
                    state.fetching = false;
                });
        };
        const createOrUpdateProduct = () => {
            state.saving = true;
            let method = post;
            let route = 'products';
            if (state.editing_product.id) {
                method = put;
                route = `products/${state.editing_product.id}`;
            }

            method(route, {
                ...state.editing_product
            })
                .then(response => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    getProducts();
                    state.ticket_modal = false;
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.saving = false;
                });

        };
        const editProductModal = (product) => {
            state.editing_product = JSON.parse(JSON.stringify(product));
            state.ticket_modal = true;
        };
        const createTicketModal = () => {
            state.editing_product = {
                title: '',
                description: ''
            }
            state.ticket_modal = true;
        };
        const deleteProduct = async(product) => {
            await del(`products/${product.id}`)
                .then(response => {
                    notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });

                    getProducts();
                });
        }

        onMounted(() => {
            getProducts();
            setTitle('Products Settings');
        });

        return {
            ...toRefs(state),
            translate,
            getProducts,
            createOrUpdateProduct,
            editProductModal,
            createTicketModal,
            deleteProduct

        }
    },
};
</script>
<style>
    .fs_products_action_btn{
        padding: 0px;
    }
</style>
