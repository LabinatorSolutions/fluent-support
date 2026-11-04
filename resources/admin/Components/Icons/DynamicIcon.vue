<template>
    <component :is="icon" v-if="icon" v-bind="labelAttrs"/>
    <img v-else-if="fallback" :src="fallback" :alt="label"/>
</template>

<script>
import { markRaw } from 'vue';

// Inline SVG icons drawn with currentColor, so they follow the text color in light and dark mode.
// Eager so icons render with the component instead of popping in.
const icons = import.meta.glob(['./*.vue', '!./DynamicIcon.vue'], { eager: true, import: 'default' });

export default {
    name: 'DynamicIcon',
    props: {
        name: {
            type: String,
            required: true
        },
        // Image URL for names without a bundled icon, e.g. add-on settings menu icons.
        fallback: {
            type: String,
            default: ''
        },
        // Accessible name for icons that carry meaning; without it the icon is decorative (aria-hidden).
        label: {
            type: String,
            default: ''
        }
    },
    computed: {
        icon() {
            const icon = icons[`./${this.name}.vue`];
            return icon ? markRaw(icon) : null;
        },
        labelAttrs() {
            return this.label ? { role: 'img', 'aria-label': this.label, 'aria-hidden': 'false' } : {};
        }
    }
};
</script>
