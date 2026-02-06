<template>

        <el-checkbox :true-value="field.true_label" :false-value="field.false_label" v-model="model">
            {{ field.checkbox_label }}
        </el-checkbox>
</template>

<script type="text/babel">
export default {
    name: 'InlineCheckbox',
    props: ['field', 'value'],
    data() {
        return {
            model: this.value
        }
    },
    watch: {
        model(value) {
            this.$emit('input', value);
        }
    }
}
</script>
