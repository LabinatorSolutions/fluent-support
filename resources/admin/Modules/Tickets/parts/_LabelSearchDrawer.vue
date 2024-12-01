<template>
    <div class="fs_label_search_drawer">
        <div class="fs_label_search_drawer_header">
            <div class="head">
                <div class="left">
                    {{ $t("Saved Filtering Options") }}
                </div>
                <div class="right">
                    <div
                        class="icon"
                        @click="openLabelSearchDrawer = false"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <path
                                d="M8 8L16 16M16 8L8 16"
                                stroke="#1B2533"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
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
            >
                <div
                    class="fs-search-name"
                    @click="handleAdvanceSearch(item)"
                >
                    {{ item.label_search_name }}
                </div>

                <div class="fs-search-actions">
                    <el-button @click="handleLabelSearchEdit(item)">
                        <svg
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M6.9725 15.6L16.1003 6.47215L14.8277 5.19955L5.6999 14.3274V15.6H6.9725ZM7.7186 17.4H3.8999V13.5813L14.1914 3.28975C14.3602 3.12103 14.5891 3.02625 14.8277 3.02625C15.0664 3.02625 15.2952 3.12103 15.464 3.28975L18.0101 5.83585C18.1788 6.00463 18.2736 6.23351 18.2736 6.47215C18.2736 6.7108 18.1788 6.93968 18.0101 7.10845L7.7186 17.4ZM3.8999 19.2H20.0999V21H3.8999V19.2Z"
                                fill="#525866"
                            />
                        </svg>
                    </el-button>
                    <el-popconfirm
                        title="Are you sure to delete this?"
                        @confirm="handleLabelSearchDelete(item.id)"
                    >
                        <template #reference>
                            <el-button type="danger">
                                <svg
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M16.5 6.6H21V8.4H19.2V20.1C19.2 20.3387 19.1052 20.5676 18.9364 20.7364C18.7676 20.9052 18.5387 21 18.3 21H5.7C5.46131 21 5.23239 20.9052 5.0636 20.7364C4.89482 20.5676 4.8 20.3387 4.8 20.1V8.4H3V6.6H7.5V3.9C7.5 3.66131 7.59482 3.43239 7.7636 3.2636C7.93239 3.09482 8.1613 3 8.4 3H15.6C15.8387 3 16.0676 3.09482 16.2364 3.2636C16.4052 3.43239 16.5 3.66131 16.5 3.9V6.6ZM17.4 8.4H6.6V19.2H17.4V8.4ZM9.3 4.8V6.6H14.7V4.8H9.3Z"
                                        fill="#525866"
                                    />
                                </svg>
                            </el-button>
                        </template>
                    </el-popconfirm>
                </div>
            </div>
        </div>
    </div>
    <template #footer>
        <div style="flex: auto">
            <el-button @click="cancelClick">{{
                translate("Close")
            }}</el-button>
        </div>
    </template>
</template>

<script type="text/babel">
import { useRouter, useRoute } from "vue-router";
import {
    useFluentHelper,
    useNotify,
} from "@/admin/Composable/FluentFrameworkHelper";
import { nextTick, onMounted, reactive, toRefs, watch, onUnmounted } from "vue";
export default {
    name: "LabelSearchDrawer",
    props: {
        labelSearchList: {
            type: Array, 
            required: true, 
            default: Array
        },
        openLabelSearchDrawer: {
            type: Boolean
        }
    },
    setup(props, {emit}) {
        const {
            appVars,
            get,
            post,
            del,
            translate,
            handleError,
            moment,
            setTitle,
            ucFirst,
            humanDiffTime,
            has_pro,
            getData,
            saveData,
        } = useFluentHelper();
        const { notify } = useNotify();
        const router = useRouter();
        const route = useRoute();
        const state = reactive({
            loading: false,
            tickets: [],
            pagination: {
                current_page: 1,
                total: 0,
                per_page: 10,
            },
            filter_type: 'advanced',
            advanced_filters: [],
            label_search_id: '',
            search: "",
            label_search_name: "",
            labelSearchList: [],
            // openLabelSearchDrawer: false,
            isLabelSearchOpen: false,
            openTicketInNewTab:
                appVars.open_ticket_in_new_tab === "yes" ? true : false,
        });

        const cancelClick = () => {
            emit('update:openLabelSearchDrawer', false);
        };

        const countAdvancedFilterData = (filters) => {
            return filters.reduce((total, innerArray) => {
                if (Array.isArray(innerArray)) {
                    const validEntries = innerArray.filter(
                        (item) => item.value && item.value.trim() !== ""
                    );
                    return total + validEntries.length;
                }
                return total;
            }, 0);
        };

        const fetchLabelSearch = () => {
            state.openLabelSearchDrawer = true;
            get("tickets/label-search")
                .then((response) => {
                    state.labelSearchList = response;
                })
                .always(() => {
                    this.loading = false;
                })
                .catch((error) => {
                    handleError(error);
                });
        };

        const handleAdvanceSearch = (item) => {
            state.advanced_filters = JSON.parse(item.advanced_filters)
            state.filter_type = item.filter_type
            state.label_search_name = ''
            state.label_search_id = ''
            fetchTickets()
        } 

        const handleLabelSearchEdit = (item) => {
            state.advanced_filters = JSON.parse(item.advanced_filters)
            state.filter_type = item.filter_type
            state.label_search_name = item.label_search_name
            state.label_search_id = item.id
        }

        const openSaveSearchModal = () => {
            state.isLabelSearchOpen = true
            state.label_search_name = state.label_search_id ? state.label_search_name : ''
        }

        const handleLabelSearchDelete = (id) => {
            del(`tickets/${id}/label-search`)
                .then((response) => {
                    if (response) {
                        state.labelSearchList = state.labelSearchList.filter(item => item.id !== id);
                        notify({
                                message: response.message,
                                type: "success",
                                position: "bottom-right",
                            });
                        }
                })
                .catch((error) => {
                    handleError(error);
                })
                .always(() => {
                    state.doing_bulk = false;
                });
        }

        const handleKeydown = (event) => {
            const { metaKey, altKey, code } = event;

            if (metaKey && altKey && code === "KeyI") {
                return;
            }

            if (metaKey && altKey) {
                event.preventDefault();

                const keyActions = {
                    KeyN: () => {
                        state.add_ticket_modal = true;
                    },
                    KeyQ: () => {
                        fetchTickets();
                    },
                    KeyF: () => {
                        state.filter_type =
                            state.filter_type === "advanced"
                                ? "simple"
                                : "advanced";
                    },
                };

                const action = keyActions[code];
                if (action) {
                    action();
                }
            }
        };

       

        return {
            appVars,
            get,
            del,
            translate,
            handleError,
            moment,
            setTitle,
            ucFirst,
            humanDiffTime,
            has_pro,
            getData,
            notify,
            ...toRefs(state),
            cancelClick,
            handleLabelSearchEdit,
            handleLabelSearchDelete,
            openSaveSearchModal,
        };
    },
}
</script>