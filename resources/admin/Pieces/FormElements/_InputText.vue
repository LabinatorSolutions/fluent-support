<template>
    <el-input :disabled="field.disabled" :type="field.data_type" :placeholder="field.placeholder" v-model="modelValue"></el-input>
</template>

<script type="text/babel">
export default {
    name: 'InputText',
    props: ['field', 'modelValue'],
    emits: ['update:modelValue'],
    data() {
        return {
            modelValue: this.modelValue
        }
    },
    watch: {
        modelValue(value) {
            this.$emit('update:modelValue', value);
        }
    }
}
</script>
