<template>
    <div class="fs_table_skeleton" aria-busy="true">
        <el-skeleton animated :loading="true">
            <template #template>
                <div v-if="header" class="fs_table_skeleton_row fs_table_skeleton_head">
                    <div v-for="(col, index) in cols" :key="'h' + index" class="fs_table_skeleton_cell" :style="cellStyle(col)">
                        <el-skeleton-item variant="text" style="width: 60%;" />
                    </div>
                </div>
                <div v-for="row in rows" :key="row" class="fs_table_skeleton_row">
                    <div v-for="(col, index) in cols" :key="index" class="fs_table_skeleton_cell" :style="cellStyle(col)">
                        <template v-if="index === 0 && avatar">
                            <el-skeleton-item variant="circle" class="fs_table_skeleton_avatar" />
                            <div class="fs_table_skeleton_lines">
                                <el-skeleton-item variant="text" style="width: 70%;" />
                                <el-skeleton-item variant="text" style="width: 45%;" />
                            </div>
                        </template>
                        <el-skeleton-item v-else variant="text" :style="{ width: textWidth(row, index) }" />
                    </div>
                </div>
            </template>
        </el-skeleton>
    </div>
</template>

<script>
// Placeholder for an fs_table while its first page loads. Pass `columns` as a
// count or as an array of flex weights / fixed widths ('120px') so the
// placeholder lines up with the real columns.
export default {
    name: 'TableSkeleton',
    props: {
        rows: { type: Number, default: 5 },
        columns: { type: [Number, Array], default: 4 },
        avatar: { type: Boolean, default: false },
        header: { type: Boolean, default: true }
    },
    computed: {
        cols() {
            return Array.isArray(this.columns) ? this.columns : Array(this.columns).fill(1);
        }
    },
    methods: {
        cellStyle(col) {
            if (typeof col === 'string') {
                return { flex: '0 0 ' + col };
            }
            return { flex: col + ' 1 0' };
        },
        textWidth(row, index) {
            // Vary the line lengths so rows don't look like a barcode.
            return [80, 60, 70, 50, 65][(row + index) % 5] + '%';
        }
    }
};
</script>
