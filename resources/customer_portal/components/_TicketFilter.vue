<template>
    <div class="fs_filters_container">
        <div class="fs_right">
            <div class="fs_product">
                <el-select clearable filterable @change="fetchTickets" style="width: 200px" v-model="filters.product_id"
                           :placeholder="$t('All Products')">
                    <el-option v-for="product in appVars.support_products" :key="product.id" :value="product.id"
                               :label="product.title"></el-option>
                </el-select>
            </div>
            <div class="fs_sorting">
                <el-popover trigger="click" placement="bottom-start" width="240" popper-class="fs_popover">
                    <div class="fs_popover_item">
                        <h3 class="fs_popover_title">{{ $t('Sort By') }}</h3>
                        <div class="fs_radio_blocks">
                            <el-radio-group @change="fetchTickets" v-model="sorting.sort_by">
                                <el-radio v-for="column in sortingColumns" :key="column.value" :label="column.value">
                                    {{ column.label }}
                                </el-radio>
                            </el-radio-group>
                        </div>
                        <hr/>
                        <el-radio-group size="small" v-model="sorting.sort_type">
                            <el-radio-button label="asc">{{ $t('Ascending') }}</el-radio-button>
                            <el-radio-button label="desc">{{ $t('Descending') }}</el-radio-button>
                        </el-radio-group>
                        <div class="fs_sorting_button">
                            <el-button @click="fetchTickets()" type="primary">
                                {{ $t('Apply') }}
                            </el-button>
                        </div>
                    </div>
                    <template #reference>
                        <span>
                            <el-tooltip effect="dark" :content="$t('Sort')" placement="top">
                                <el-button icon="Sort"></el-button>
                            </el-tooltip>
                        </span>
                    </template>
                </el-popover>
            </div>
        </div>
        <div class="fs_search_bar">
            <el-input @keyup.enter="fetchTickets" clearable @clear="fetchTickets()" style="width: 200px" size="small"
                      :placeholder="$t('Please input')" v-model="searchQuery">
                <template #append>
                    <el-button @click="fetchTickets" icon="Search"></el-button>
                </template>
            </el-input>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        filters: Object,
        sorting: Object,
        sortingColumns: Array,
        search: String,
    },
    data() {
        return {
            searchQuery: this.search,
            appVars: this.appVars
        }
    },
    computed: {
        searchQuery: {
            get() {
                return this.search;
            },
            set(value) {
                this.$emit('update-search-query', value)
            }
        }
    },
    methods: {
        fetchTickets() {
            this.$emit('fetch-tickets', {
                search: this.searchQuery,
            });
        }
    }
}
</script>
