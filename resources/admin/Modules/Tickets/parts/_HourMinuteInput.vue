<template>
    <el-row class="fs_minute_input" :gutter="20" :span="2">
        <el-col :span="12">
            <label>{{ $t('Hours') }}</label>
            <el-input type="number" v-model="form.hour" :min="0" :step="1"></el-input>
        </el-col>
        <el-col :span="12">
            <label>{{ $t('Minutes') }}</label>
            <el-input type="number" v-model="form.minute" :min="0" :max="59" :step="1"></el-input>
        </el-col>
    </el-row>
</template>

<script type="text/babel">
export default {
    name: 'HourMinuteInput',
    props: ['modelValue'],
    emits: ['update:modelValue'],
    data() {
        return {
            form: {
                hour: Math.floor(this.modelValue / 60),
                minute: Math.floor(this.modelValue % 60)
            }
        }
    },
    watch: {
        form: {
            handler() {
                this.$emit('update:modelValue', parseInt(this.form.hour) * 60 + parseInt(this.form.minute));
            },
            deep: true
        }
    }
}
</script>
