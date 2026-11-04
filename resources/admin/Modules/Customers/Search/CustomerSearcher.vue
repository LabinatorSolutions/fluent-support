<template>
    <div class="fs_customer_searcher">
        <el-popover
            :visible="slash.open"
            placement="bottom-start"
            :width="320"
            :show-arrow="false"
            :offset="4"
            :teleported="true"
            popper-class="fs_customer_search_slash_popper"
            :persistent="false"
        >
            <template #reference>
                <div class="fs_customer_searcher_input">
                    <el-input
                        ref="input"
                        v-model="model"
                        clearable
                        :placeholder="$t('Search name, email or #ID. Type / for fields')"
                        role="combobox"
                        :aria-expanded="String(slash.open)"
                        :aria-controls="menuId"
                        :aria-activedescendant="slash.open && slashResults.length ? menuId + '-' + slash.index : null"
                        aria-autocomplete="list"
                        @keyup.enter="fire"
                        @keydown="onKeydown"
                        @input="updateSlash"
                        @click="updateSlash"
                        @blur="closeSlash"
                    >
                        <template #prefix>
                            <el-icon><Search /></el-icon>
                        </template>
                        <template #suffix>
                            <el-tooltip :content="$t('Search tips')" placement="bottom">
                                <button
                                    type="button"
                                    class="fs_customer_searcher_help"
                                    :aria-label="$t('Search tips')"
                                    @click="openHelp"
                                >
                                    <el-icon><QuestionFilled /></el-icon>
                                </button>
                            </el-tooltip>
                        </template>
                    </el-input>
                </div>
            </template>

            <div :id="menuId" class="fs_customer_search_slash" role="listbox" :aria-label="$t('Search by field')" @mousedown.prevent>
                <div class="fs_customer_search_slash_label">{{ $t('Search by') }}</div>
                <div v-if="slashResults.length" class="fs_customer_search_slash_list">
                    <div
                        v-for="(field, index) in slashResults"
                        :id="menuId + '-' + index"
                        :key="field.key"
                        role="option"
                        :aria-selected="index === slash.index"
                        class="fs_customer_search_slash_item"
                        :class="{ is_active: index === slash.index }"
                        @mouseenter="slash.index = index"
                        @click="selectField(field)"
                    >
                        <span class="fs_customer_search_slash_name">{{ field.label }}</span>
                        <code class="fs_customer_search_slash_key">{{ field.key }}:</code>
                    </div>
                </div>
                <div v-else class="fs_customer_search_slash_empty">{{ $t('No matching field') }}</div>
                <div class="fs_customer_search_slash_footer">{{ $t('Up and down to move, Enter to pick, Esc to close') }}</div>
            </div>
        </el-popover>

        <customer-search-help
            v-if="helpLoaded"
            v-model="helpVisible"
            @apply="applyQuery"
            @insert="insertKey"
        />
    </div>
</template>

<script>
import { QuestionFilled } from '@element-plus/icons-vue';
import CustomerSearchHelp from './CustomerSearchHelp.vue';
import { getSearchFields, findSlashToken, filterSearchFields } from './customerSearchFields';

let searcherUid = 0;

/**
 * Customer search box for the CustomerSearch syntax: `/` opens a field menu, ? opens the tips.
 * Searches on Enter (and when cleared) by emitting `search`. Ported from FluentCRM's Searcher.vue.
 */
export default {
    name: 'CustomerSearcher',
    components: {
        CustomerSearchHelp,
        QuestionFilled
    },
    props: {
        modelValue: {
            type: String,
            default: ''
        }
    },
    emits: ['update:modelValue', 'search'],
    data() {
        return {
            model: this.modelValue || '',
            helpVisible: false,
            // Mount the dialog on first open only; most visits never open it.
            helpLoaded: false,
            // `/` menu: the token being typed spans [start, end) in the model.
            slash: { open: false, query: '', start: -1, end: -1, index: 0 },
            // Enter picked a field on keydown; the keyup must not also search.
            suppressEnter: false,
            menuId: 'fs_customer_search_slash_' + (++searcherUid)
        };
    },
    computed: {
        searchFields() {
            return getSearchFields(this.$t);
        },
        slashResults() {
            return filterSearchFields(this.searchFields, this.slash.query);
        }
    },
    watch: {
        modelValue(newValue) {
            this.model = newValue || '';
        },
        model(newValue, oldValue) {
            this.$emit('update:modelValue', newValue || '');

            // Emptying the box (clear button or deleting the text) shows the full list again.
            if ((oldValue || '').trim() && !(newValue || '').trim()) {
                this.$emit('search', '');
            }
        }
    },
    methods: {
        focus() {
            this.$refs.input && this.$refs.input.focus();
        },
        fire() {
            if (this.suppressEnter) {
                this.suppressEnter = false;
                return;
            }
            this.$emit('search', this.model);
        },
        nativeInput() {
            return this.$refs.input && this.$refs.input.input;
        },
        /**
         * Open, filter or close the `/` menu from the text before the cursor.
         */
        updateSlash() {
            const el = this.nativeInput();
            const value = el ? el.value : (this.model || '');
            const caret = el && typeof el.selectionStart === 'number' ? el.selectionStart : value.length;
            const token = findSlashToken(value, caret);
            if (!token) {
                this.closeSlash();
                return;
            }
            if (!this.slash.open || token.query !== this.slash.query) {
                this.slash.index = 0;
            }
            this.slash.open = true;
            this.slash.query = token.query;
            this.slash.start = token.start;
            this.slash.end = token.end;
        },
        closeSlash() {
            this.slash.open = false;
        },
        onKeydown(event) {
            if (!this.slash.open) return;
            // Enter/arrows during IME composition belong to the IME.
            if (event.isComposing || event.keyCode === 229) return;
            const count = this.slashResults.length;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (count) {
                    const step = event.key === 'ArrowDown' ? 1 : -1;
                    this.slash.index = (this.slash.index + step + count) % count;
                    this.$nextTick(() => {
                        const item = document.getElementById(this.menuId + '-' + this.slash.index);
                        item && item.scrollIntoView({ block: 'nearest' });
                    });
                }
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                if (count) {
                    event.preventDefault();
                    this.suppressEnter = event.key === 'Enter';
                    this.selectField(this.slashResults[this.slash.index] || this.slashResults[0]);
                } else {
                    this.closeSlash();
                }
            } else if (event.key === 'Escape') {
                // Close the menu only; leave the typed `/` as text.
                event.preventDefault();
                event.stopPropagation();
                this.closeSlash();
            }
        },
        /**
         * Replace the `/word` being typed with `key:` and put the cursor after it.
         */
        selectField(field) {
            const value = this.model || '';
            const start = this.slash.start >= 0 ? this.slash.start : value.length;
            const end = this.slash.end >= 0 ? this.slash.end : value.length;
            const insert = field.key + ':';
            this.model = value.slice(0, start) + insert + value.slice(end);
            this.closeSlash();
            const caret = start + insert.length;
            this.$nextTick(() => {
                const el = this.nativeInput();
                if (el) {
                    el.focus();
                    el.setSelectionRange(caret, caret);
                }
            });
        },
        openHelp() {
            this.helpLoaded = true;
            this.helpVisible = true;
        },
        /**
         * Run a full example query picked from the tips dialog.
         */
        applyQuery(query) {
            this.helpVisible = false;
            this.model = query;
            this.fire();
        },
        /** Append a `field:` key and put the cursor after it. */
        insertKey(key) {
            this.helpVisible = false;
            const current = (this.model || '').trim();
            this.model = current ? current + ' ' + key : key;
            this.$nextTick(() => this.focus());
        }
    }
};
</script>
