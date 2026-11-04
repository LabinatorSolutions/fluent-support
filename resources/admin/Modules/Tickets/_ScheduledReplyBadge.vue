<template>
    <el-tooltip v-if="schedule" effect="dark" :content="tooltip" placement="top" :disabled="!compact">
        <span class="fs_scheduled_reply_badge" :class="{ fs_scheduled_reply_badge_paused: isPaused }">
            <el-icon><VideoPause v-if="isPaused" /><Clock v-else /></el-icon>
            <span>{{ label }}</span>
        </span>
    </el-tooltip>
</template>

<script type="text/babel">
import { formatUtcTime } from '@/common/dateTime';

export default {
    name: 'ScheduledReplyBadge',
    props: {
        schedule: {
            type: Object,
            default: null
        },
        compact: {
            type: Boolean,
            default: false
        }
    },
    computed: {
        isPaused() {
            return this.schedule?.status === 'paused';
        },
        time() {
            return formatUtcTime(this.schedule?.scheduled_at, true);
        },
        label() {
            if (this.compact) {
                return this.isPaused ? this.$t('Paused') : this.time;
            }

            return this.tooltip;
        },
        tooltip() {
            if (this.isPaused) {
                return this.$t('Scheduled reply paused');
            }

            // translators: %s is the date and time the reply will be sent
            return this.$t('Reply scheduled · %s', this.time);
        }
    }
}
</script>
