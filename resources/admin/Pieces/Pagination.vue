<template>
    <el-pagination
                   :background="background"
                   :layout="layout"
                   @current-change="changePage"
                   @size-change="changeSize"
                   :hide-on-single-page="false"
                   :current-page.sync="pagination.current_page"
                   :page-sizes="page_sizes"
                   :page-size="pagination.per_page"
                   :total="pagination.total"
    />
</template>

<script>
    export default {
        name: 'Pagination',
        props: {
            pagination: {
                required: true,
                type: Object
            },
            layout: {
                type: String,
                default: 'total, sizes, prev, pager, next'
            },
            background: {
                type: [Boolean, String],
                default: false
            }
        },
        emits: ['fetch'],
        computed: {
            page_sizes() {
                return [
                    10,
                    20,
                    50,
                    80,
                    100,
                    120,
                    150
                ];
            }
        },
        methods: {
            changePage(page) {
                this.pagination.current_page = page;
                this.$emit('fetch');
            },
            changeSize(size) {
                this.pagination.per_page = size;
                this.$emit('fetch');
            }
        }
    };
</script>
