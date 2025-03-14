<template>
    <div class="fs_pagination_container">
        <!-- Left section with total and page size -->
        <div class="fs_pagination_left">
            <span class="fs_page_text">Page {{ pagination.current_page }} of {{ Math.ceil(pagination.total / pagination.per_page) }}</span>
            <el-select
                v-model="pagination.per_page"
                class="fs_per_page_select"
                @change="changeSize"
            >
                <el-option
                    v-for="size in page_sizes"
                    :key="size"
                    :label="`${size} / page`"
                    :value="size"
                />
            </el-select>
        </div>

        <!-- Right section with pagination -->
        <el-pagination
            :background="false"
            layout="prev, pager, next"
            @current-change="changePage"
            @size-change="changeSize"
            :hide-on-single-page="false"
            :current-page.sync="pagination.current_page"
            :page-size="pagination.per_page"
            :total="pagination.total"
        />
    </div>
</template>

<script>
export default {
    name: 'Pagination',
    props: {
        pagination: {
            required: true,
            type: Object
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

<style>
.fs_pagination_container {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;


    .fs_pagination_left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .fs_page_text {
        color: #666;
        font-size: 14px;
    }

    .fs_per_page_select {
        width: 120px;

        .el-select__wrapper {
            border-radius: 8px;
        }
    }

    /* Select styling */

    .el-select .el-input__wrapper {
        background-color: white;
        box-shadow: 0 0 0 1px #dcdfe6;
        border-radius: 6px;
    }

    .el-select .el-input__wrapper:hover {
        box-shadow: 0 0 0 1px #c0c4cc;
    }

    .el-select-dropdown__item {
        padding: 8px 16px;
        font-size: 14px;
    }

    /* Pagination styling */

    .el-pagination {
        --el-pagination-bg-color: transparent;
        --el-pagination-hover-color: #f3f4f6;
        --el-pagination-button-color: #666;
        --el-pagination-button-disabled-color: #ccc;
    }

    .el-pagination .el-pager li {
        background: transparent;
        color: #666;
        min-width: 32px;
        height: 32px;
        line-height: 32px;
        border-radius: 8px;
        margin: 0 4px;
        font-weight: normal;
        border: 1px solid rgba(225, 228, 234, 1);
    }

    .el-pagination .el-pager li.is-active {
        background: rgba(245, 247, 250, 1);
        color: #333;
        font-weight: 600;
    }

    .el-pagination .btn-prev,
    .el-pagination .btn-next {
        background: transparent;
        color: #666;
        border: none;
        margin: 0 4px;
        border-radius: 6px;
    }

    .el-pagination .btn-prev:disabled,
    .el-pagination .btn-next:disabled {
        color: #ccc;
    }

    .el-pagination .btn-prev:hover:not(:disabled),
    .el-pagination .btn-next:hover:not(:disabled),
    .el-pagination .el-pager li:hover:not(.is-active) {
        background: #f3f4f6;
    }

    /* Dropdown menu styling */

    .el-select-dropdown__item.selected {
        color: #333;
        font-weight: 600;
        background-color: #f3f4f6;
    }

    .el-select-dropdown__item:hover {
        background-color: #f3f4f6;
    }
}
</style>
