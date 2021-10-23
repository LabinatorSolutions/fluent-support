<template>
    <div class="fs_html_viewer" :class="field.wrapper_class">
        <div v-html="field.html"></div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'HtmlViewer',
    props: ['field'],
}
</script>
