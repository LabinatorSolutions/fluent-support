<template>
    <!-- First load: placeholders shaped like the title, reply bar, thread and sidebar widgets. -->
    <div class="fs_ticket_body fs_ticket_view_skeleton" aria-busy="true">
        <el-skeleton animated :loading="true">
            <template #template>
                <div class="fs_title_container">
                    <el-skeleton-item variant="text" style="width: 55%; height: 22px;" />
                    <div class="fs_skeleton_row">
                        <el-skeleton-item variant="text" style="width: 90px;" />
                        <el-skeleton-item variant="text" style="width: 120px;" />
                        <el-skeleton-item variant="button" style="width: 64px; height: 22px;" />
                    </div>
                </div>
                <div class="fs_ticket_header">
                    <el-skeleton-item variant="button" style="width: 100%; height: 36px;" />
                </div>
                <div class="fs_threads_container">
                    <div v-for="n in 3" :key="n" class="fs_conversation_message">
                        <div class="fs_message_container">
                            <div class="fs_message_avatar">
                                <el-skeleton-item variant="circle" style="width: 32px; height: 32px;" />
                            </div>
                            <div class="fs_message_content">
                                <el-skeleton-item variant="text" style="width: 160px;" />
                                <el-skeleton-item variant="text" style="width: 80px; height: 10px;" />
                                <el-skeleton-item variant="text" :style="{ width: [92, 85, 78][n - 1] + '%', marginTop: '16px' }" />
                                <el-skeleton-item variant="text" :style="{ width: [80, 60, 88][n - 1] + '%' }" />
                                <el-skeleton-item v-if="n !== 2" variant="text" style="width: 45%;" />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </el-skeleton>
    </div>
    <div class="fs_ticket_sidebar fs_ticket_view_skeleton" aria-busy="true">
        <!-- Customer card -->
        <el-skeleton class="fs_sidebar_skeleton" animated :loading="true">
            <template #template>
                <el-skeleton-item variant="text" style="width: 50%;" />
                <div class="fs_skeleton_row" style="margin-top: 16px;">
                    <el-skeleton-item variant="circle" style="width: 40px; height: 40px; flex-shrink: 0;" />
                    <div class="fs_skeleton_lines">
                        <el-skeleton-item variant="text" style="width: 65%;" />
                        <el-skeleton-item variant="text" style="width: 85%;" />
                    </div>
                </div>
            </template>
        </el-skeleton>
        <!-- Additional details: label + select pairs -->
        <el-skeleton class="fs_sidebar_skeleton" animated :loading="true">
            <template #template>
                <el-skeleton-item variant="text" style="width: 45%;" />
                <div v-for="n in 2" :key="n" class="fs_skeleton_lines" style="margin-top: 16px;">
                    <el-skeleton-item variant="text" style="width: 35%;" />
                    <el-skeleton-item variant="button" style="width: 100%; height: 28px;" />
                </div>
            </template>
        </el-skeleton>
        <!-- Previous conversations: list rows -->
        <el-skeleton class="fs_sidebar_skeleton" animated :loading="true">
            <template #template>
                <el-skeleton-item variant="text" style="width: 55%;" />
                <div v-for="n in 3" :key="n" class="fs_skeleton_lines" style="margin-top: 14px;">
                    <el-skeleton-item variant="text" :style="{ width: [80, 65, 72][n - 1] + '%' }" />
                    <el-skeleton-item variant="text" style="width: 40%; height: 10px;" />
                </div>
            </template>
        </el-skeleton>
    </div>
</template>

<script type="text/babel">
// The ticket screen's first-load placeholders, shaped like the title, thread and sidebar.
export default {
    name: 'TicketSkeleton'
};
</script>
