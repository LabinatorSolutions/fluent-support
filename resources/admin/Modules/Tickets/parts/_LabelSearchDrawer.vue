<template>
    <div class="fs_label_search_drawer">
        <!-- Fixed Header Section -->
        <div class="fs_label_search_drawer_header">
            <div class="head">
                <div class="left">
                    {{ translate("Saved Filtering Options") }}
                </div>
                <div class="right">
                    <div class="icon" @click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M8 8L16 16M16 8L8 16" stroke="#1B2533" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="fs-table-items">
            <div
                class="fs-each-search"
                v-for="(item, index) in labelSearchList"
                :key="index"
                :class="{ selected: selectedItem === index }"
                @click="selectItem(index)"
            >
                <div class="fs-search-name">{{ item.label_search_name }}</div>
                <div class="fs-search-actions">
                    <el-button @click.stop="handleLabelSearchEdit(item)">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.9725 15.6L16.1003 6.47215L14.8277 5.19955L5.6999 14.3274V15.6H6.9725ZM7.7186 17.4H3.8999V13.5813L14.1914 3.28975C14.3602 3.12103 14.5891 3.02625 14.8277 3.02625C15.0664 3.02625 15.2952 3.12103 15.464 3.28975L18.0101 5.83585C18.1788 6.00463 18.2736 6.23351 18.2736 6.47215C18.2736 6.7108 18.1788 6.93968 18.0101 7.10845L7.7186 17.4ZM3.8999 19.2H20.0999V21H3.8999V19.2Z" fill="#525866"/>
                        </svg>
                    </el-button>
                    <el-popconfirm title="Are you sure to delete this?" @confirm="handleLabelSearchDelete(item.id)">
                        <template #reference>
                            <el-button type="danger" @click.stop>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M16.5 6.6H21V8.4H19.2V20.1C19.2 20.3387 19.1052 20.5676 18.9364 20.7364C18.7676 20.9052 18.5387 21 18.3 21H5.7C5.46131 21 5.23239 20.9052 5.0636 20.7364C4.89482 20.5676 4.8 20.3387 4.8 20.1V8.4H3V6.6H7.5V3.9C7.5 3.66131 7.59482 3.43239 7.7636 3.2636C7.93239 3.09482 8.1613 3 8.4 3H15.6C15.8387 3 16.0676 3.09482 16.2364 3.2636C16.4052 3.43239 16.5 3.66131 16.5 3.9V6.6ZM17.4 8.4H6.6V19.2H17.4V8.4ZM9.3 4.8V6.6H14.7V4.8H9.3Z" fill="#525866"/>
                                </svg>
                            </el-button>
                        </template>
                    </el-popconfirm>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, toRefs, watch } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "LabelSearchDrawer",
    props: {
        labelSearchList: {
            type: Array,
            required: true,
            default: () => [],
        },
        filtersValue: {
            type: Array,
            required: true,
            default: () => [],
        }
    },
    setup(props, { emit }) {
        const { translate } = useFluentHelper();
        const state = reactive({
            selectedItem: null,
            userSelected: false, // Flag to track user selection
        });

        const closeModal = () => {
            emit("close", false);
        };

        const selectItem = (index) => {
            state.selectedItem = index;
            state.userSelected = true;
            const selectedItem = props.labelSearchList[index];
            handleSavedSearch(selectedItem);
        };

        const handleSavedSearch = (item) => {
            emit("getSavedSearch", item);
        };

        const handleLabelSearchEdit = (item) => {
            emit("setLabelSearchItem", item);
        };

        const handleLabelSearchDelete = (id) => {
            emit("delete", id);
            state.selectedItem = null;
        };

        watch(
            () => props.filtersValue,
            (newFiltersValue) => {
                if (!state.userSelected) {
                    state.selectedItem = null;
                }
                state.userSelected = false;
            },
            { deep: true }
        );

        return {
            ...toRefs(state),
            closeModal,
            selectItem,
            handleLabelSearchEdit,
            handleLabelSearchDelete,
            handleSavedSearch,
            translate
        };
    },
};
</script>


