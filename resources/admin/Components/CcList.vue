<template>
    <div v-if="emails.length" class="fs_cc_info">
        <span>
            <strong>Cc: </strong>{{ visibleEmails }}
            <el-popover v-if="emails.length > limit" trigger="click" placement="bottom-start" :width="300" popper-class="fs_cc_popover">
                <template #reference>
                    <button type="button" class="fs_cc_more_btn" :aria-label="$t('Show all Cc')">
                        +{{ emails.length - limit }}
                        <el-icon class="fs_cc_more_icon"><ArrowDown /></el-icon>
                    </button>
                </template>
                <div class="fs_cc_details">
                    <strong>{{ $t('Cc') }}:</strong>
                    <div><div v-for="(email, i) in emails" :key="email">{{ email }}{{ i < emails.length - 1 ? ',' : '' }}</div></div>
                </div>
            </el-popover>
        </span>
    </div>
</template>

<script>
export default {
    name: 'CcList',
    props: {
        emails: {
            type: Array,
            default: () => []
        },
        limit: {
            type: Number,
            default: 2
        }
    },
    computed: {
        visibleEmails() {
            return this.emails.slice(0, this.limit).join(', ');
        }
    }
};
</script>
