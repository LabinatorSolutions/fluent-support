<template>
    <div>
        <el-select
            clearable
            filterable
            :multiple="field.multiple"
            :placeholder="field.placeholder"
            :model-value="modelValueLocal"
            @update:model-value="updateValue"
        >
            <el-option
                v-for="item in field.options"
                :key="item.id"
                :label="item.title"
                :value="item.id">
                <span style="float: left">{{ item.title }}</span>
                <span v-if="field.show_id" style="float: right; color: #8492a6; font-size: 13px">{{ item.id }}</span>
            </el-option>
        </el-select>
    </div>
</template>

<script>
export default {
    name: 'InputOptions',
    props: ['field', 'modelValue'],
    emits: ['update:modelValue'],
    computed: {
        modelValueLocal() {
            return this.convertToInt(this.modelValue);
        }
    },
    methods: {
        convertToInt(value) {
            if (Array.isArray(value)) {
                return value.map(item => parseInt(item) || item);
            }
            return parseInt(value) || value;
        },
        updateValue(newValue) {
            if (JSON.stringify(newValue) !== JSON.stringify(this.modelValue)) {
                this.$emit('update:modelValue', newValue);
            }
        }
    }
}
</script>
