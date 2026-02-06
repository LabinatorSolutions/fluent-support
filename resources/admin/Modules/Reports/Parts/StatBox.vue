<template>
    <div class="fs_stat_box">
        <div class="fs_stat_icon" :style="{ background: iconBg }">
            <IconPack 
                :icon-key="icon" 
                :width="20" 
                :height="20"
                :fill="iconColor"
            />
        </div>
        <div class="fs_stat_content">
            <p class="fs_stat_label">{{ label }}</p>
            <span class="fs_stat_value">{{ value }}</span>
        </div>
    </div>
</template>

<script type="text/babel">
import IconPack from "@/admin/Components/IconPack.vue";

export default {
    name: "StatBox",
    components: {
        IconPack
    },
    props: {
        label: {
            type: String,
            required: true
        },
        value: {
            type: [String, Number],
            default: 0
        },
        icon: {
            type: String,
            required: true
        },
        iconBg: {
            type: String,
            default: '#f2f5f8'
        },
        iconColor: {
            type: String,
            default: '#525866'
        }
    }
};
</script>

