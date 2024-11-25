<template>
    <div>
        <el-select clearable filterable :multiple="field.multiple" :placeholder="field.placeholder" v-model="modelValueLocal">
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
    data() {
        return {
            modelValueLocal: this.convertToInt(this.modelValue)
        }
    },
    watch: {
        modelValue: {
            handler(newValue) {
                this.modelValueLocal = this.convertToInt(newValue);
            },
            deep: true
        },
        modelValueLocal: {
            handler(newValue) {
                this.$emit('update:modelValue',newValue);
            },
            deep: true
        }
    },
    methods: {
        convertToInt(value) {
            if (Array.isArray(value)) {
                return value.map(item => parseInt(item) || item);
            }
            return parseInt(value) || value;
        }
    }
}
</script>
