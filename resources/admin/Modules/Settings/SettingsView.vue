<template>
    <div class="fs_tickets_view fs_settings_view">
        <!-- Mobile Drawer Overlay -->
        <div
            class="fs_settings_menu_overlay"
            :class="{ 'is-open': isMenuOpen }"
            @click.stop="closeMenu"
            v-show="isMenuOpen"
        ></div>

        <!-- Mobile Toggle Button -->
        <div class="fs_settings_menu_toggle" :class="{ 'is-hidden': isMenuOpen }">
            <el-button class="fs_outline_btn fs_settings_menu_toggle_btn" v-if="!isMenuOpen" @click="toggleMenu">
                <DynamicIcon name="Settings"/>
                {{ $t('Settings') }}
            </el-button>
        </div>

        <div class="fs_settings_wrapper">
            <!-- Desktop Sidebar (always visible on desktop) -->
            <div
                class="fs_inner_sidebar fs_desktop_sidebar"
                :class="{ 'is-collapsed': isSidebarCollapsed, 'is-hover-expanded': isHoverExpanded }"
                @mouseleave="isHovered = false"
            >
                <div class="fs_sidebar_topbar">
                    <button
                        type="button"
                        class="fs_sidebar_topbar_icon"
                        :title="collapseToggleTitle"
                        :aria-label="collapseToggleTitle"
                        :aria-expanded="!isSidebarCollapsed"
                        @click="toggleSidebarCollapse"
                    >
                        <IconPack icon-key="reports_nav_icon" :width="20" :height="20" />
                    </button>
                    <span class="fs_sidebar_topbar_title">{{ $t('Settings') }}</span>
                </div>
                <div class="fs_settings_sidebar_items" @mouseenter="isHovered = true">
                    <el-skeleton v-if="menu_loading" animated style="padding: 8px 12px;">
                        <template #template>
                            <div v-for="n in 8" :key="n" style="display: flex; align-items: center; gap: 10px; padding: 8px 0;">
                                <el-skeleton-item variant="rect" style="width: 20px; height: 20px; flex-shrink: 0;" />
                                <el-skeleton-item v-if="showSidebarLabels" variant="text" :style="{ width: (n % 3 === 0 ? 50 : 70) + '%' }" />
                            </div>
                        </template>
                    </el-skeleton>
                    <ul v-else>
                        <li v-for="(item, index) in settings_items" :key="index">
                            <!-- Group item with children -->
                            <template v-if="item.children && item.children.length">
                                <button
                                    class="fs_sidebar_group_toggle"
                                    :class="{ 'is-open': openGroups[index] && showSidebarLabels, 'is-active': !showSidebarLabels && isGroupActive(item) }"
                                    :aria-label="isSidebarCollapsed ? item.title : null"
                                    @click="onGroupClick(index)"
                                >
                                    <DynamicIcon :name="iconName(item.icon)" :fallback="appVars.asset_url + 'images/' + item.icon + '.svg'" class="fs-sidebar-icons"/>
                                    <span class="fs_sidebar_group_title fs_sidebar_label">{{ item.title }}</span>
                                    <svg class="fs_sidebar_chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </button>
                                <ul v-show="openGroups[index] && showSidebarLabels" class="fs_sidebar_children">
                                    <li v-for="(child, ci) in item.children" :key="ci">
                                        <router-link :to="{ name: child.route_name }">
                                            {{ child.title }}
                                        </router-link>
                                    </li>
                                </ul>
                            </template>

                            <!-- Regular flat item -->
                            <router-link
                                v-else
                                :to="{
                                    name: item.route_name,
                                    query: item.route_query,
                                }"
                                :aria-label="isSidebarCollapsed ? item.title : null"
                            >
                                <DynamicIcon :name="iconName(item.icon)" :fallback="appVars.asset_url + 'images/' + item.icon + '.svg'" class="fs-sidebar-icons"/>
                                <span class="fs_sidebar_label">{{ item.title }}</span>
                            </router-link>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Mobile Drawer Sidebar (only visible on mobile when toggled) -->
            <div class="fs_inner_sidebar fs_mobile_drawer" :class="{ 'is-open': isMenuOpen }">
                <ul>
                    <li v-for="(item, index) in settings_items" :key="index">
                        <!-- Group item with children -->
                        <template v-if="item.children && item.children.length">
                            <button
                                class="fs_sidebar_group_toggle"
                                :class="{ 'is-open': openGroups[index] }"
                                @click="toggleGroup(index)"
                            >
                                <DynamicIcon :name="iconName(item.icon)" :fallback="appVars.asset_url + 'images/' + item.icon + '.svg'" class="fs-sidebar-icons"/>
                                <span class="fs_sidebar_group_title">{{ item.title }}</span>
                                <svg class="fs_sidebar_chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <ul v-show="openGroups[index]" class="fs_sidebar_children">
                                <li v-for="(child, ci) in item.children" :key="ci">
                                    <router-link :to="{ name: child.route_name }" @click="closeMenu">
                                        {{ child.title }}
                                    </router-link>
                                </li>
                            </ul>
                        </template>

                        <!-- Regular flat item -->
                        <router-link
                            v-else
                            :to="{
                                name: item.route_name,
                                query: item.route_query,
                            }"
                            @click="closeMenu"
                        >
                            <DynamicIcon :name="iconName(item.icon)" :fallback="appVars.asset_url + 'images/' + item.icon + '.svg'" class="fs-sidebar-icons"/>
                            {{ item.title }}
                        </router-link>
                    </li>
                </ul>
            </div>

            <div class="fs_inner_body">
                <router-view key="products_view"></router-view>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import IconPack from "@/admin/Components/IconPack.vue";
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

export default {
    name: "SettingsView",
    components: {
        DynamicIcon,
        IconPack
    },
    data() {
        return {
            settings_items: [],
            menu_loading: true,
            isMenuOpen: false,
            openGroups: {},
            isSidebarCollapsed: this.$getData('settingsMenuCollapsed') === 'yes',
            isHovered: false
        }
    },
    computed: {
        isHoverExpanded() {
            return this.isSidebarCollapsed && this.isHovered;
        },
        showSidebarLabels() {
            return !this.isSidebarCollapsed || this.isHoverExpanded;
        },
        collapseToggleTitle() {
            return this.isSidebarCollapsed ? this.$t('Expand Sidebar') : this.$t('Collapse Sidebar');
        }
    },
    watch: {
        $route() {
            this.autoOpenActiveGroup();
        },
        settings_items() {
            this.autoOpenActiveGroup();
        },
    },
    methods: {
        // Menu icon keys are camelCase ('ticketTag'); icon components are PascalCase.
        iconName(icon) {
            return icon ? icon.charAt(0).toUpperCase() + icon.slice(1) : '';
        },
        fetchSettingsMenu() {
            this.$get("settings/settings-menu")
                .then((response) => {
                    this.settings_items = response;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.menu_loading = false;
                });
        },
        toggleGroup(index) {
            this.openGroups = {
                ...this.openGroups,
                [index]: !this.openGroups[index],
            };
        },
        // Without the hover flyout (keyboard, screen reader) the children are
        // hidden, so a group click expands the sidebar and opens that group.
        onGroupClick(index) {
            if (!this.showSidebarLabels) {
                this.toggleSidebarCollapse();
                this.openGroups = { ...this.openGroups, [index]: true };
                return;
            }
            this.toggleGroup(index);
        },
        isGroupActive(item) {
            return item.children.some(c => c.route_name === this.$route.name);
        },
        toggleSidebarCollapse() {
            this.isSidebarCollapsed = !this.isSidebarCollapsed;
            this.isHovered = false;
            this.$saveData('settingsMenuCollapsed', this.isSidebarCollapsed ? 'yes' : 'no');
        },
        autoOpenActiveGroup() {
            const currentRoute = this.$route.name;
            this.settings_items.forEach((item, index) => {
                if (item.children && item.children.length) {
                    const hasActive = item.children.some(c => c.route_name === currentRoute);
                    if (hasActive) {
                        this.openGroups = { ...this.openGroups, [index]: true };
                    }
                }
            });
        },
        toggleMenu() {
            this.isMenuOpen = !this.isMenuOpen;
            if (this.isMenuOpen) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        },
        closeMenu() {
            this.isMenuOpen = false;
            document.body.style.overflow = '';
        }
    },
    mounted() {
        this.fetchSettingsMenu();
    },
    beforeUnmount() {
        document.body.style.overflow = '';
    }
};
</script>
