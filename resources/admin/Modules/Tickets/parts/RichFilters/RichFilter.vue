<template>
    <div class="fs_rich_filters">
        <table v-if="items" style="width: 100%;" class="fs_table">
            <tbody>
            <rich-filter-item v-for="(item,itemKey) in items" @removeItem="removeItem(itemKey)" :key="itemKey"
                              :filterLabels="filterLabels" :item="item" />
            </tbody>
        </table>

        <div v-if="Object.keys(items).length==0" class="fs_filter_intro fs_padded_20">
            <el-popover
                placement="right"
                width="450"
                v-model="addVisible"
                trigger="click">
                <el-cascader-panel @change="maybeSelected"
                                   style="width: 100%"
                                   :options="filterOptions"
                                   v-model="new_item"/>
                <template #reference>
                    <el-button @click="addVisible = !addVisible" size="small" icon="el-icon-plus">Add</el-button>
                </template>
            </el-popover>
            {{$t('advance_filter_help_text')}}
        </div>

        <div v-else class="fs_filter_intro fs_padded_20">
            <el-popover
                placement="right"
                width="450"
                v-model="addVisible"
                trigger="click">
                <el-cascader-panel @change="maybeSelected"
                                   style="width: 100%"
                                   :options="filterOptions"
                                   v-model="new_item"/>
                <template #reference>
                    <el-button @click="addVisible = !addVisible" size="small" icon="el-icon-plus">And</el-button>
                </template>
            </el-popover>
        </div>

    </div>
</template>


<script>
import RichFilterItem from './_RichFilterItem';
import each from 'lodash/each';

export default {
    name: "RichFilter",
    components: {
        RichFilterItem
    },
    props: ['items'],
    data() {
        return {
            addVisible: false,
            new_item: [],
            filterOptions: this.appVars.advanced_filter_options
        }
    },
    computed: {
        filterLabels() {
            const options = {};
            each(this.filterOptions, (option) => {
                each(option.children, (item) => {
                    options[option.value + '-' + item.value] = {
                        provider: option.value,
                        ...item
                    }
                });
            });
            return options
        }
    },
    methods: {
        maybeSelected() {
            if (this.new_item.length == 2) {
                let operator = '';

                if (this.new_item[0] == 'customer' && this.new_item[1] != 'country') {
                    operator = 'contains';
                }

                this.items.push({
                    source: [...this.new_item],
                    operator: operator,
                    value: ''
                });
                this.addVisible = false;
                this.new_item = [];
            }
        },
        removeItem(index) {
            this.items.splice(index, 1);
            if (!this.items.length) {
                this.$emit('maybeRemove');
            }
        }
    }
}
</script>

<style scoped>

</style>
