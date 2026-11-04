<template>
    <div class="fs_inside_menu_tab_header" :class="variantClass" @mouseleave="isHovered = false">
        <div class="fs_sidebar_topbar">
            <button
                type="button"
                class="fs_sidebar_topbar_icon"
                :title="toggleTitle"
                :aria-label="toggleTitle"
                :aria-expanded="!collapsed"
                @click="onToggle"
            >
                <IconPack icon-key="reports_nav_icon" :width="20" :height="20" />
            </button>
            <span class="fs_sidebar_topbar_title">{{ $t('Reports') }}</span>
        </div>
        <div class="fs_box_head" @mouseenter="isHovered = true">
            <div class="fs_reports_nav_group">
                <div class="fs-inside-menu-tabs">
                    <button
                        v-for="tab in tabs"
                        :key="tab.name"
                        :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === tab.name }]"
                        :aria-label="collapsed ? tab.label : null"
                        @click="$emit('select', tab.name)"
                    >
                        <IconPack :icon-key="tab.icon" :width="20" :height="20" class="fs_nav_tab_icon" />
                        <span class="fs_nav_tab_label">{{ tab.label }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import IconPack from "@/admin/Components/IconPack.vue";

export default {
    name: "ReportSidebar",
    components: {
        IconPack
    },
    props: {
        tabs: {
            type: Array,
            required: true
        },
        activeName: {
            type: String,
            default: ''
        },
        // 'desktop' renders the always-visible column, 'mobile' the drawer.
        // Both render the same list — this only picks the layout class.
        variant: {
            type: String,
            default: 'desktop'
        },
        isOpen: {
            type: Boolean,
            default: false
        },
        collapsed: {
            type: Boolean,
            default: false
        }
    },
    emits: ['select', 'toggle-collapse'],
    data() {
        return {
            isHovered: false
        };
    },
    computed: {
        variantClass() {
            if (this.variant === 'mobile') {
                return ['fs_mobile_drawer', { 'is-open': this.isOpen }];
            }
            return ['fs_desktop_sidebar', { 'is-collapsed': this.collapsed, 'is-hover-expanded': this.collapsed && this.isHovered }];
        },
        toggleTitle() {
            if (this.variant === 'mobile') {
                return this.$t('Close');
            }
            return this.collapsed ? this.$t('Expand Sidebar') : this.$t('Collapse Sidebar');
        }
    },
    methods: {
        // The pointer is still inside after the click, so clear hover until it re-enters the tabs
        onToggle() {
            this.isHovered = false;
            this.$emit('toggle-collapse');
        }
    }
};
</script>
