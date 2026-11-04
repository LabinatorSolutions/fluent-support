<template>
            <div v-if="advancedOpen">
                <div class="fs_rich_container">
                    <div v-if="appReady" class="fs_rich_wrap">
                        <div
                            v-for="(
                                rich_filter, filterIndex
                            ) in advanced_filters"
                            :key="filterIndex"
                        >
                            <div class="fs_rich_filter">
                                <rich-filter
                                    @maybeRemove="
                                        maybeRemoveGroup(filterIndex)
                                    "
                                    :items="rich_filter"
                                />
                            </div>
                            <div class="fs_filter_separator" v-if="filterIndex + 1 != advanced_filters.length">
                                <div class="fs_condition_sign">{{ $t("OR") }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="fs_filter_separator" @click="addConditionGroup()">
                        <div class="fs_condition_sign fs_button">
                            <svg class="fs_add_and_icon fs_icon_primary" width="11" height="11" viewBox="0 0 11 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.5 4.5V0H6V4.5H10.5V6H6V10.5H4.5V6H0V4.5H4.5Z" fill="currentColor"/>
                            </svg>
                            {{ $t("OR") }}
                        </div>
                    </div>

                    <div class="fs_advanced_filter_actions">
                        <div class="fs_advanced_filter_actions_left">
                            <el-button
                                v-if="has_pro && countAdvancedFilterData(advanced_filters)"
                                type="primary"
                                @click="openSaveSearchModal"
                                class="fs_saved_search_btn"
                            >
                                <IconPack iconKey="labelSearch" :width="20" :height="20" />
                                <span class="fs_saved_search_btn_text">{{ label_search_id ? $t("Update Filter") : $t("Save Filter") }}</span>
                            </el-button>
                        </div>
                        <div class="fs_advanced_filter_actions_right">
                            <a
                                class="fs_filter_clear_btn"
                                @click="resetAdvancedFilters"
                            >
                                {{ $t("Reset Filters") }}
                            </a>
                            <el-button class="fs_filled_btn" type="primary" @click="applyAdvancedFilter()">
                                <DynamicIcon v-if="!has_pro" name="Crown" class="fs_pro_crown_icon"/>
                                {{ $t("Apply Filter") }}
                            </el-button>
                        </div>
                    </div>
                </div>
            </div>

    <el-dialog
        :title="$t('Save search')"
        v-model="isLabelSearchOpen"
        @close="isLabelSearchOpen = false"
        class="fs_dialog"
    >
        <el-form label-position="top">
            <el-form-item :label="$t('Search Name')">
                <el-input
                    type="text"
                    :placeholder="$t('Search Name')"
                    v-model="label_search_name"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="fs_dialog_footer">
                <el-button
                    class="fs_outline_btn"
                    @click="isLabelSearchOpen = false"
                    >{{ $t("Cancel") }}</el-button
                >
                <el-button
                    v-loading="saving"
                    :disabled="saving"
                    class="fs_filled_btn"
                    type="success"
                    @click="handleSaveSearch"
                    >{{ $t("Save") }}</el-button
                >
            </span>
        </template>
    </el-dialog>

    <el-drawer
        :with-header="false"
        :append-to-body="true"
        v-model="openLabelSearchDrawer"
        class="fs-el-drawer"
    >
        <label-search-drawer
            :labelSearchList="labelSearchList"
            :filtersValue="advanced_filters"
            @close="closeSavedSearchListModal"
            @getSavedSearch="handleAdvanceSearch"
            @setLabelSearchItem="handleLabelSearchEdit"
            @delete="handleLabelSearchDelete"
        />
        <template #footer>
            <div class="fs_text_right">
                <el-button class="fs_outline_btn" @click="closeSavedSearchListModal">{{
                    $t("Close")
                }}</el-button>
            </div>
        </template>
    </el-drawer>

    <el-dialog
        v-if="!has_pro"
        v-model="showAdvancedFilterPromo"
        :append-to-body="true"
        width="900px"
        class="fs_dialog fs_pro_showcase_dialog"
    >
        <template #header>
            <span class="fs_pro_showcase_dialog_title">
                {{ $t('Advanced filters') }}
                <span class="fs_pro_badge">{{ $t('Pro') }}</span>
            </span>
        </template>
        <ProShowcase
            hide-eyebrow
            :heading="$t('Find the right tickets faster')"
            :description="$t('Create custom filters to find the exact tickets you need.')"
            :specifics="advancedFilterPromoSpecifics"
            utm-content="feature_lock_advanced_filters"
            docs-url="https://docs.fluentsupport.com/advanced-filter-fluent-support"
        >
            <template #preview>
                <div class="fs_pro_preview">
                    <div class="fs_pro_preview_card fs_pro_preview_fields">
                        <div class="fs_pro_preview_title">
                            <span class="fs_pro_preview_checkbox"></span>
                            {{ $t('Advanced filter') }}
                        </div>
                        <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                            <span class="fs_pro_preview_row_label">{{ $t('Status') }}</span>
                            <span class="fs_pro_preview_badge fs_pro_preview_badge_success">{{ $t('Active') }}</span>
                        </div>
                        <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                            <span class="fs_pro_preview_row_label">{{ $t('Priority') }}</span>
                            <span class="fs_pro_preview_chip fs_pro_preview_chip_purple fs_pro_preview_chip_plain">{{ $t('Critical') }}</span>
                        </div>
                        <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                            <span class="fs_pro_preview_row_label">{{ $t('Results') }}</span>
                            <!-- translators: %s: number of tickets -->
                            <span class="fs_pro_preview_badge fs_pro_preview_badge_info">{{ $_n('%s matching ticket', '%s matching tickets', 12) }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </ProShowcase>
        <template #footer>
            <span class="fs_dialog_footer">
                <el-button class="fs_outline_btn" @click="showAdvancedFilterPromo = false">{{ $t('Not now') }}</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script type="text/babel">
import RichFilter from './RichFilters/RichFilter';
import LabelSearchDrawer from './_LabelSearchDrawer';
import ProShowcase from '@/admin/Components/ProShowcase.vue';
import IconPack from '@/admin/Components/IconPack.vue';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import { mapState, mapActions, mapWritableState } from 'pinia';
import { useTicketListStore } from '@/admin/stores/ticketList.store';

/**
 * The advanced filter builder (Pro; free sees it as a preview and gets the
 * upgrade dialog on Apply), saving a filter and the saved filters drawer.
 * Emits fetch; AllTickets.vue reloads.
 */
export default {
    name: 'AdvancedTicketFilter',
    components: { RichFilter, LabelSearchDrawer, ProShowcase, IconPack, DynamicIcon },
    props: ['advancedOpen'],
    emits: ['fetch'],
    data() {
        return {
            openLabelSearchDrawer: false,
            isLabelSearchOpen: false,
            saving: false,
            showAdvancedFilterPromo: false
        };
    },
    computed: {
        ...mapState(useTicketListStore, ['appReady', 'labelSearchList']),
        ...mapWritableState(useTicketListStore, ['advanced_filters', 'label_search_id', 'label_search_name']),
        advancedFilterPromoSpecifics() {
            return [
                this.$t('Filter by status, priority, inbox, product, tags, and date'),
                this.$t('Combine AND/OR conditions to narrow your results'),
                this.$t('Save filters for quick access anytime')
            ];
        }
    },
    methods: {
        ...mapActions(useTicketListStore, {
            storeResetAdvancedFilters: 'resetAdvancedFilters',
            storeHandleSaveSearch: 'handleSaveSearch',
            storeFetchLabelSearches: 'fetchLabelSearches',
            storeHandleLabelSearchDelete: 'handleLabelSearchDelete',
            storeHandleAdvanceSearch: 'handleAdvanceSearch',
            storeHandleLabelSearchEdit: 'handleLabelSearchEdit',
        }),

        addConditionGroup() {
            this.advanced_filters.push([]);
        },

        maybeRemoveGroup(index) {
            if (this.advanced_filters.length > 1) {
                this.advanced_filters.splice(index, 1);
            }
        },

        countAdvancedFilterData(filters) {
            return filters.reduce((total, innerArray) => {
                if (Array.isArray(innerArray)) {
                    const validEntries = innerArray.filter((item) => {
                        if (typeof item.value === 'string') {
                            return item.value.trim() !== "";
                        }

                        if (Array.isArray(item.value)) {
                            return item.value.some(val => typeof val === 'string' && val.trim() !== "");
                        }

                        return false;
                    });
                    return total + validEntries.length;
                }
                return total;
            }, 0);
        },

        applyAdvancedFilter() {
            if (!this.has_pro) {
                this.showAdvancedFilterPromo = true;
                return;
            }
            this.$emit('fetch');
        },

        resetAdvancedFilters() {
            // Free only clears the preview builder; nothing was applied or saved.
            if (!this.has_pro) {
                useTicketListStore().advanced_filters = [[]];
                return;
            }
            this.storeResetAdvancedFilters();
            this.removeData('tickets_advanced_filters');
            this.removeData('tickets_filter_type');
            this.removeData('tickets_pref');
            this.$emit('fetch');
        },

        removeData(key) {
            let existingData = this.$getData('__fluentsupport_data');
            if (!existingData) {
                return;
            }
            existingData = JSON.parse(existingData);
            delete existingData[key];
            this.$saveData('__fluentsupport_data', JSON.stringify(existingData));
        },

        openSaveSearchModal() {
            this.isLabelSearchOpen = true;
            this.label_search_name = this.label_search_id ? this.label_search_name : '';
        },

        handleSaveSearch() {
            const store = useTicketListStore();
            if (!store.label_search_name || (store.filter_type === "advanced" && !store.advanced_filters.length)) {
                this.$notify({
                    message: this.$t("Please provide a valid name or filter criteria."),
                    type: "warning",
                    position: "bottom-right",
                });
                return;
            }
            this.isLabelSearchOpen = false;
            store.ticket_selections = [];
            store.loading = true;

            let query = {
                filter_type: store.filter_type,
                label_search_name: store.label_search_name,
            };

            if (store.filter_type === "advanced" && this.has_pro) {
                query.advanced_filters = JSON.stringify(store.advanced_filters);
            }

            if (store.label_search_id) {
                query.id = store.label_search_id;
            }

            this.storeHandleSaveSearch(query)
                .then((response) => {
                    this.$notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    store.label_search_id = null;
                    store.loading = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {});
        },

        fetchLabelSearch() {
            this.openLabelSearchDrawer = true;
            this.storeFetchLabelSearches()
                .always(() => {
                    const store = useTicketListStore();
                    store.loading = false;
                })
                .catch((error) => {
                    this.$handleError(error);
                });
        },

        closeSavedSearchListModal() {
            this.openLabelSearchDrawer = false;
        },

        handleAdvanceSearch(item) {
            this.storeHandleAdvanceSearch(item);
            this.openLabelSearchDrawer = false;
            this.$emit('fetch');
        },

        handleLabelSearchEdit(item) {
            this.storeHandleLabelSearchEdit(item);
            this.openLabelSearchDrawer = false;
        },

        handleLabelSearchDelete(id) {
            this.storeHandleLabelSearchDelete(id)
                .then((response) => {
                    if (response) {
                        this.$notify({
                            message: response.message,
                            type: "success",
                            position: "bottom-right",
                        });
                    }
                })
                .catch((error) => {
                    this.$handleError(error);
                });
        }
    }
};
</script>
