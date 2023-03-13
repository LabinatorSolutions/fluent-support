<template>
    <wp-base-editor :showShortcodes="field.show_shortcodes" :showSavedReplies="field.show_saved_replies" v-model="modelValue" />
</template>

<script type="text/babel">

import WpBaseEditor from '../_wp_editor';

export default {
    name: 'WPEditorField',
    components: {
        WpBaseEditor
    },
    props: ['field', 'modelValue'],
    emits: ['update:modelValue'],
    watch: {
        modelValue(value) {
            this.$emit('update:modelValue', value);
        }
    }
}
</script>
