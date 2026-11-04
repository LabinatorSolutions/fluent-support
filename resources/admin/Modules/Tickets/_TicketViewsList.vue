<template>
    <div class="fs_sidebar_items">
        <div v-for="group in groups" :key="group.key" class="fs_sidebar_group">
            <el-button
                v-if="!isCollapsed"
                text
                class="fs_sidebar_group_title"
                :aria-expanded="!closedGroups[group.key] ? 'true' : 'false'"
                @click="$emit('toggle-group', group.key)"
            >
                <span>{{ group.title }}</span>
                <el-icon class="fs_sidebar_group_arrow" :class="{ 'is-closed': closedGroups[group.key] }"><ArrowDown /></el-icon>
            </el-button>
            <ul v-show="isCollapsed || !closedGroups[group.key]">
                <li v-for="item in group.items" :key="item.key">
                    <a
                        href="#"
                        :class="{ fs_sidebar_active: item.active }"
                        :aria-current="item.active ? 'page' : null"
                        @click.prevent="select(item)"
                    >
                        <span v-tip.right="isCollapsed ? item.label : ''" class="fs_sidebar_icons">
                            <span v-if="item.dot" class="fs_sidebar_priority_dot" :class="'fs_sidebar_priority_' + item.dot"></span>
                            <IconPack
                                v-else
                                :icon-key="item.icon"
                                :width="20"
                                :height="20"
                                :fill="item.active ? 'var(--fs-text-primary)' : 'var(--fs-text-secondary)'"
                            />
                        </span>
                        <span v-if="!isCollapsed" class="fs_sidebar_label">{{ item.label }}</span>
                        <span
                            v-if="!isCollapsed && item.count !== null"
                            class="fs_sidebar_count"
                            :class="{ fs_sidebar_count_attention: item.attention && item.count > 0 }"
                        >{{ formatCount(item.count) }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <div v-if="!isCollapsed && (tagItems.length || tagSearch)" class="fs_sidebar_group">
            <el-button
                text
                class="fs_sidebar_group_title"
                :aria-expanded="!closedGroups.tags ? 'true' : 'false'"
                @click="$emit('toggle-group', 'tags')"
            >
                <span>{{ $t('Tags') }}</span>
                <el-icon class="fs_sidebar_group_arrow" :class="{ 'is-closed': closedGroups.tags }"><ArrowDown /></el-icon>
            </el-button>
            <div v-show="!closedGroups.tags">
                <el-input
                    v-if="showTagSearch"
                    :model-value="tagSearch"
                    @update:model-value="$emit('update:tagSearch', $event)"
                    class="fs_text_input fs_sidebar_tag_search"
                    :placeholder="$t('Filter tags')"
                    prefix-icon="Search"
                    clearable
                />
                <ul>
                    <li v-for="item in tagItems" :key="item.key">
                        <a
                            href="#"
                            :class="{ fs_sidebar_active: item.active }"
                            :aria-current="item.active ? 'page' : null"
                            @click.prevent="select(item)"
                        >
                            <span class="fs_sidebar_tag_dot"></span>
                            <span class="fs_sidebar_label">{{ item.label }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import IconPack from "@/admin/Components/IconPack.vue";

// The ticket views (my work, priority, status, saved filters, tags). Rendered by the
// filters sidebar on the list and by the view switcher on a single ticket.
export default {
    name: 'TicketViewsList',
    components: {
        IconPack
    },
    props: {
        groups: {
            type: Array,
            required: true
        },
        tagItems: {
            type: Array,
            default: () => []
        },
        tagSearch: {
            type: String,
            default: ''
        },
        showTagSearch: {
            type: Boolean,
            default: false
        },
        closedGroups: {
            type: Object,
            default: () => ({})
        },
        isCollapsed: {
            type: Boolean,
            default: false
        },
        // Reload the list in place (beside an open ticket) instead of going to the list page.
        openInPlace: {
            type: Boolean,
            default: false
        }
    },
    emits: ['toggle-group', 'update:tagSearch', 'selected'],
    methods: {
        select(item) {
            item.open(this.openInPlace);
            this.$emit('selected', item);
        },

        formatCount(count) {
            if (count < 1000) {
                return count;
            }

            try {
                return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(count);
            } catch (e) {
                return count;
            }
        }
    }
};
</script>
