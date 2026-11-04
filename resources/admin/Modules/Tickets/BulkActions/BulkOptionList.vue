<template>
    <div class="fs_bb_options">
        <div v-if="showSearch" class="fs_bb_options_search">
            <input
                ref="search"
                v-model="query"
                class="fs_bb_field"
                type="text"
                autocomplete="off"
                :placeholder="placeholder || $t('Search')"
                :aria-label="placeholder || $t('Search')"
                @keydown.enter.prevent="toggleFirstMatch"
            />
        </div>
        <div class="fs_bb_options_list" role="listbox" :aria-multiselectable="multiple ? 'true' : 'false'">
            <template v-for="row in visibleRows" :key="row.key">
                <div v-if="row.heading" class="fs_bb_group_label">{{ row.heading }}</div>
                <label
                    v-else
                    class="fs_bb_option"
                    :class="{ 'is-disabled': row.item.disabled, 'is-checked': isChecked(row.item.id) }"
                >
                    <input
                        :type="multiple ? 'checkbox' : 'radio'"
                        :name="inputName"
                        :checked="isChecked(row.item.id)"
                        :disabled="row.item.disabled"
                        @change="toggle(row.item.id)"
                    />
                    <span class="fs_bb_option_label">{{ row.item.title }}</span>
                    <span v-if="row.item.note" class="fs_bb_option_note" :title="row.item.noteTitle || ''">{{ row.item.note }}</span>
                </label>
            </template>
            <div v-if="!items.length" class="fs_bb_empty">
                {{ emptyText || $t('Nothing to choose from yet') }}
            </div>
            <div v-else-if="!matchedItems.length" class="fs_bb_empty">
                <!-- translators: %s is the search text -->
                {{ $t('Nothing matches "%s"', query) }}
            </div>
            <div v-else-if="hiddenCount" class="fs_bb_more_hint">
                <!-- translators: %s is the number of matching options not shown in the list -->
                {{ $t('%s more. Search to narrow the list.', hiddenCount) }}
            </div>
        </div>
    </div>
</template>

<script>
/**
 * Searchable checkbox or radio list used by every bulk-bar panel.
 * Items with a `group` are listed under that heading, in first-seen order.
 * Values are compared as strings because option ids arrive as numbers and strings.
 */
let uid = 0;

export default {
    name: 'BulkOptionList',
    props: {
        items: { type: Array, default: () => [] },
        modelValue: { type: [Array, String, Number], default: null },
        multiple: { type: Boolean, default: false },
        placeholder: { type: String, default: '' },
        emptyText: { type: String, default: '' },
        // Show the search box only when the list is long enough to need it.
        searchThreshold: { type: Number, default: 7 },
        // Cap on rendered rows.
        limit: { type: Number, default: 200 }
    },
    emits: ['update:modelValue'],
    data() {
        return {
            query: '',
            inputName: 'fs_bb_opt_' + (++uid)
        };
    },
    computed: {
        showSearch() {
            return this.items.length > this.searchThreshold;
        },
        matchedItems() {
            // A query typed while the box was visible must not keep filtering once it is hidden.
            const q = this.showSearch ? this.query.trim().toLowerCase() : '';
            if (!q) {
                return this.items;
            }
            return this.items.filter(item => String(item.title).toLowerCase().includes(q));
        },
        visibleRows() {
            const rows = [];
            let lastGroup = null;
            this.matchedItems.slice(0, this.limit).forEach(item => {
                if (item.group && item.group !== lastGroup) {
                    rows.push({ key: 'g:' + item.group, heading: item.group });
                    lastGroup = item.group;
                }
                // An agent can be listed under more than one group.
                rows.push({ key: 'i:' + (item.group || '') + ':' + item.id, item });
            });
            return rows;
        },
        hiddenCount() {
            return Math.max(0, this.matchedItems.length - this.limit);
        },
        selectedKeys() {
            if (this.multiple) {
                return (this.modelValue || []).map(String);
            }
            return this.modelValue === null || this.modelValue === '' ? [] : [String(this.modelValue)];
        }
    },
    methods: {
        isChecked(id) {
            return this.selectedKeys.includes(String(id));
        },
        toggle(id) {
            if (!this.multiple) {
                this.$emit('update:modelValue', id);
                return;
            }
            const current = [...(this.modelValue || [])];
            const index = current.findIndex(value => String(value) === String(id));
            if (index === -1) {
                current.push(id);
            } else {
                current.splice(index, 1);
            }
            this.$emit('update:modelValue', current);
        },
        /** Enter in the search box picks the first enabled match. */
        toggleFirstMatch() {
            const first = this.matchedItems.find(item => !item.disabled);
            if (first) {
                this.toggle(first.id);
            }
        },
        focus() {
            const target = this.$refs.search || this.$el.querySelector('input:not([disabled])');
            if (target) {
                target.focus();
            }
        }
    }
};
</script>
