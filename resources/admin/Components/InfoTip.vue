<template>
    <el-tooltip :content="content" placement="top" :trigger="['hover', 'focus']">
        <span class="fs_info_tip" tabindex="0" role="button" :aria-label="content" @click.stop>
            <InformationFill />
        </span>
    </el-tooltip>
</template>

<script>
import InformationFill from '@/admin/Components/Icons/InformationFill.vue';

export default {
    name: 'InfoTip',
    components: { InformationFill },
    props: {
        content: { type: String, required: true }
    }
};
</script>
