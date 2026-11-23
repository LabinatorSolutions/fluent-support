<template>
    <div class="fs_snapshot_banner">
        <!-- translators: %1$s is the snapshot date, %2$s the snapshot time -->
        <span>{{ $t('Viewing snapshot from %1$s at %2$s', formattedDate, snapshot.time) }}</span>
        <el-button class="fs_outline_btn" @click="$emit('back')">{{ $t('Back to live') }}</el-button>
    </div>
</template>

<script>
import dayjs from "dayjs";

export default {
    name: "SnapshotBanner",
    props: {
        snapshot: {
            type: Object,
            required: true
        }
    },
    emits: ["back"],
    computed: {
        formattedDate() {
            return dayjs(this.snapshot.date).format("MMM D, YYYY");
        }
    }
};
</script>
