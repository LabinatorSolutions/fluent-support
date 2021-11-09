<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('Products')}}</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button @click="createTicketModal()" type="primary" icon="el-icon-plus" size="small">{{$t('Create New')}}
                    </el-button>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-input @keyup.enter="getProducts" clearable @clear="getProducts" size="mini"
                              :placeholder="$t('Search Products')" v-model="search">
                        <template #append>
                            <el-button @click="getProducts" icon="el-icon-search"></el-button>
                        </template>
                    </el-input>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">
                <el-table stripe :data="products">
                    <el-table-column width="80" prop="id" :label="$t('ID')"></el-table-column>
                    <el-table-column prop="title" :label="$t('Title')"></el-table-column>
                    <el-table-column prop="description" :label="$t('Description')"></el-table-column>
                    <el-table-column width="120" :label="$t('Action')">
                        <template #default="scope">
                            <el-button @click="editProductModal(scope.row)" size="medium" type="text"
                                       icon="el-icon-edit"></el-button>
                            <el-popconfirm
                                confirm-button-text="Yes, Delete this"
                                cancel-button-text="No"
                                icon="el-icon-info"
                                icon-color="red"
                                title="Are you sure to delete this product?"
                                @confirm="deleteProduct(scope.row)"
                            >
                                <template #reference>
                                    <el-button v-loading="fetching" style="margin-left: 10px; color: red;" type="text"
                                               size="medium"
                                               icon="el-icon-delete"></el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="getProducts()" :pagination="pagination"/>
                </div>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                <el-skeleton :rows="5" animated/>
            </div>
        </div>

        <el-dialog
            :append-to-body=true
            :title="(editing_product && editing_product.id) ? $t('Edit Product') : $t('Create New Supported Product')"
            v-model="ticket_modal"
            v-if="editing_product"
            width="60%">

            <el-form label-position="top" :data="editing_product">
                <el-form-item :label="$t('Title')">
                    <el-input type="text" :placeholder="$t('Product Title')" v-model="editing_product.title"/>
                </el-form-item>
                <el-form-item :label="$t('Description')">
                    <el-input type="textarea" :placeholder="$t('Product Description')" v-model="editing_product.description"/>
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="primary" @click="createOrUpdateProduct()">{{$t('Update')}}</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'

export default {
    name: 'SupportProducts',
    components: {
        Pagination
    },
    data() {
        return {
            fetching: false,
            products: [],
            pagination: {
                current_page: 1,
                per_page: 10,
                total: 0
            },
            saving: false,
            ticket_modal: false,
            editing_product: false,
            search: ''
        }
    },
    methods: {
        getProducts() {
            this.fetching = true;
            this.$get('products', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search
            })
                .then(response => {
                    this.products = response.products.data;
                    this.pagination.total = response.products.total;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        createOrUpdateProduct() {
            this.saving = true;
            let method = this.$post;
            let route = 'products';
            if (this.editing_product.id) {
                method = this.$put;
                route = `products/${this.editing_product.id}`;
            }

            method(route, {
                ...this.editing_product
            })
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.getProducts();
                    this.ticket_modal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });

        },
        editProductModal(product) {
            this.editing_product = JSON.parse(JSON.stringify(product));
            this.ticket_modal = true;
        },
        createTicketModal() {
            this.editing_product = {
                title: '',
                description: ''
            }
            this.ticket_modal = true;
        },
        deleteProduct(product) {
            // const r = confirm(this.$t("Are you sure, You want to delete this?"));
            //
            // if (!r) {
            //     return ;
            // }

            this.$del(`products/${product.id}`)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });

                    this.getProducts();
                });
        }
    },
    mounted() {
        this.getProducts();
        this.$setTitle('Products Settings');
    }
}
</script>
