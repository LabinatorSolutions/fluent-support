<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>Products</h3>
                </div>
                <div class="fs_box_actions">
                    <el-button @click="createTicketModal()" type="primary" icon="el-icon-plus" size="small">Create New
                    </el-button>
                </div>
            </div>
            <div class="fs_box_body">
                <el-table stripe :data="products">
                    <el-table-column width="80" prop="id" label="ID"></el-table-column>
                    <el-table-column prop="title" label="Title"></el-table-column>
                    <el-table-column prop="description" label="Description"></el-table-column>
                    <el-table-column width="120" label="Action">
                        <template #default="scope">
                            <el-button @click="editProductModal(scope.row)" size="mini" type="success"
                                       icon="el-icon-edit"></el-button>
                            <el-button @click="deleteProduct(scope.row)" size="mini" type="danger" icon="el-icon-delete"></el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="getProducts()" :pagination="pagination"/>
                </div>
            </div>
        </div>

        <el-dialog
            :append-to-body="true"
            :title="(editing_product && editing_product.id) ? 'Edit Product' : 'Create New Supported Product'"
            v-model="ticket_modal"
            v-if="editing_product"
            width="60%">

            <el-form label-position="top" :data="editing_product">
                <el-form-item label="Title">
                    <el-input type="text" placeholder="Product Title" v-model="editing_product.title"/>
                </el-form-item>
                <el-form-item label="Description">
                    <el-input type="textarea" placeholder="Product Description" v-model="editing_product.description"/>
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="primary" @click="createOrUpdateProduct()">Update</el-button>
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
            editing_product: false
        }
    },
    methods: {
        getProducts() {
            this.fetching = true;
            this.$get('products', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page
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
            const r = confirm("Are you sure, You want to delete this?");

            if (!r) {
                return ;
            }

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
