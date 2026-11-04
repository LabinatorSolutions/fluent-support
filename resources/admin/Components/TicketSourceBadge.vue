<template>
    <span v-if="source" v-tip="tooltip" class="fs_source_badge" tabindex="0" :aria-label="tooltip">
        <DynamicIcon v-if="iconName" :name="iconName"/>
        <img v-else-if="imageUrl" :src="imageUrl" alt=""/>
        <span v-else class="fs_source_badge_text">{{ label }}</span>
    </span>
</template>

<script>
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

// Sources with an image in assets/images but no bundled currentColor icon.
const IMAGE_SOURCES = ['telegram', 'whatsapp'];

// Where a ticket or reply came from, as an icon (or its name when there is no icon) with the name in a tooltip.
export default {
    name: 'TicketSourceBadge',
    components: { DynamicIcon },
    props: {
        source: {
            type: String,
            default: ''
        }
    },
    computed: {
        label() {
            return this.$sourceLabel(this.source);
        },
        tooltip() {
            // translators: %s is where a message came from, e.g. Email, Web or Telegram
            return this.$t('Via %s', this.label);
        },
        iconName() {
            return this.$sourceIconName(this.source);
        },
        imageUrl() {
            return IMAGE_SOURCES.includes(this.source) ? this.$getSourceIcon(this.source) : '';
        }
    }
};
</script>
