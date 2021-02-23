<template>
    <div class="fc_options_selector" :class="(field.creatable) ? 'fc_option_creatable' : ''">
        <el-select v-loading="!element_ready" v-model="model" :multiple="field.is_multiple"
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

<script type="text/babel">
export default {
    name: 'OptionSelector',
    props: ['value', 'field'],
    data() {
        return {
            options: {},
            model: this.value,
            element_ready: false,
            new_item: '',
            creating: false,
            create_pop_status: false
        }
    },
    watch: {
        model(value) {
            this.$emit('input', value);
        }
    },
    methods: {
        getOptions() {
            this.app_ready = false;
            const query = {
                fields: 'tags,lists,editable_statuses,' + this.field.option_key
            };
            this.$get('reports/options', query).then(response => {
                window.fc_options_cache = response.options;
                this.options = response.options;
                this.element_ready = true;
            })
                .catch((error) => {
                    this.handleError(error);
                })
                .finally(() => {

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
                .fail((errros) => {
                    this.handleError(errros);
                })
                .finally(() => {
                    this.creating = false;
                });
        }
    },
    mounted() {
        if (window.fc_options_cache && window.fc_options_cache[this.field.option_key]) {
            this.options = window.fc_options_cache;
            if (this.field.is_multiple && typeof this.value !== 'object') {
                this.$set(this, 'model', []);
            }
            this.element_ready = true;
        } else {
            this.getOptions();
        }
    }
}
</script>

<style lang="scss">
.fc_option_creatable {
    display: block;
    width: 100%;

    .el-select {
        width: 80%;
        float: left;
    }

    .fc_with_select {
        float: left;
    }
}
</style>
