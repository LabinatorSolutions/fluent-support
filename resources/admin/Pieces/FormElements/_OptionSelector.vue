<template>
    <div class="fs_options_selector" :class="(field.creatable) ? 'fs_option_creatable' : ''">
        <el-select :size="field.size" v-loading="!element_ready" v-model="model" :multiple="field.is_multiple"
                   :placeholder="field.placeholder"
                   clearable
                   filterable>
            <el-option v-if="element_ready"
                       v-for="option in options[field.option_key]"
                       :key="option.id"
                       :value="option.id"
                       :label="option.title"><span v-html="option.title"></span></el-option>
        </el-select>
        <el-popover
            v-if="field.creatable"
            placement="left"
            v-model="create_pop_status"
            width="400"
            trigger="manual">
            <div>
                <el-input placeholder="Provide Name" v-model="new_item">
                    <template slot="append">
                        <el-button @click="createNewItem()" type="success">Add</el-button>
                    </template>
                </el-input>
            </div>
            <el-button @click="create_pop_status = !create_pop_status" slot="reference" class="fc_with_select"
                       type="info">+
            </el-button>
        </el-popover>
    </div>
</template>

<script>
export default {
    name: 'OptionSelector',
    props: ['value', 'field', 'modelValue'],
    data() {
        return {
            options: {},
            model: this.value,
            element_ready: false,
            new_item: [],
            creating: false,
            create_pop_status: false
        }
    },
    watch: {
        model(value) {
            this.$emit('update:modelValue', value);
        }
    },
    methods: {
        getOptions() {
            this.app_ready = false;
            const query = {
                fields: 'tags,statuses,client_priority,priority,' + this.field.option_key
            };
            this.$get('rich-filter/options', query).then(response => {
                this.options = response.options;
                this.element_ready = true;
            })
                .catch((error) => {
                    this.handleError(error);
                })
                .always(() => {

                });
        },
        createNewItem() {
            this.creating = true;
            this.$post(this.field.option_key + '/bulk', {
                items: [
                    {
                        title: this.new_item
                    }
                ]
            })
                .then(response => {
                    this.create_pop_status = false;
                    this.$notify.success(response.message);
                    this.getOptions();
                    this.new_item = '';
                })
                .catch((errros) => {
                    this.handleError(errros);
                })
                .always(() => {
                    this.creating = false;
                });
        }
    },
    mounted() {
        this.getOptions();
    }
}
</script>

<style lang="scss">
.fs_option_creatable {
    display: block;
    width: 100%;

    .el-select {
        width: 80%;
        float: left;
    }

    .fs_with_select {
        float: left;
    }
}
</style>
