<template>
    <div class="fs_form_skeleton" aria-busy="true">
        <el-skeleton animated :loading="true">
            <template #template>
                <div v-for="field in fields" :key="field" class="fs_form_skeleton_field">
                    <el-skeleton-item variant="text" class="fs_form_skeleton_label" />
                    <el-skeleton-item variant="rect" class="fs_form_skeleton_input" />
                </div>
                <el-skeleton-item v-if="button" variant="button" class="fs_form_skeleton_button" />
            </template>
        </el-skeleton>
    </div>
</template>

<script>
// Placeholder for a settings form (label + input pairs) while its values load.
export default {
    name: 'FormSkeleton',
    props: {
        fields: { type: Number, default: 4 },
        button: { type: Boolean, default: true }
    }
};
</script>
