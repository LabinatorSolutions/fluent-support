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

        <div v-else class="fs_filter_intro">
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
                    <el-button @click="addVisible = !addVisible" size="small" icon="el-icon-plus" style="margin-top: 0.7em;
">And</el-button>
                </template>
            </el-popover>
        </div>

    </div>
</template>


<script>
import RichFilterItem from './_RichFilterItem';
import each from 'lodash/each';

export default {
    name: 'RichFilter',
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

                if (this.new_item[0] === 'customer' && this.new_item[1] !== 'agent') {
                    operator = 'contains';
                }

                let tempItem = this.new_item;
                let exist = false;
                each(this.items, (item) => {
                    if(item.source[0] === tempItem[0] && item.source[1] === tempItem[1]){
                        exist = true;
                    }
                })
                if(!exist){
                    this.items.push({
                        source: [...this.new_item],
                        operator: operator,
                        value: ''
                    });
                    this.addVisible = false;
                    // this.new_item = [];
                }else{
                    this.$notify.error({
                        message: 'Selected item already selected',
                        position: 'bottom-right'
                    })
                }
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
