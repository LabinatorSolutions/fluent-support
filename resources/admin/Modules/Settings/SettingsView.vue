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
                <img :src="appVars.asset_url + 'images/settings.svg'" alt="Settings" />
                {{ $t('Settings') }}
            </el-button>
        </div>
        
        <div class="fs_settings_wrapper">
            <!-- Desktop Sidebar (always visible on desktop) -->
            <div class="fs_inner_sidebar fs_desktop_sidebar">
                <ul>
                    <li v-for="(settings_menu, settings_index) in settings_items" :key="settings_index">
                        <router-link
                            :to="{
                            name: settings_menu.route_name,
                            query: settings_menu.route_query,
                        }"
                        >
                            <img :src="appVars.asset_url + 'images/' + settings_menu.icon + '.svg'" class="fs-sidebar-icons" />
                            {{ settings_menu.title }}
                        </router-link>
                    </li>
                </ul>
            </div>
            
            <!-- Mobile Drawer Sidebar (only visible on mobile when toggled) -->
            <div class="fs_inner_sidebar fs_mobile_drawer" :class="{ 'is-open': isMenuOpen }">
                <ul>
                    <li v-for="(settings_menu, settings_index) in settings_items" :key="settings_index">
                        <router-link
                            :to="{
                            name: settings_menu.route_name,
                            query: settings_menu.route_query,
                        }"
                            @click="closeMenu"
                        >
                            <img :src="appVars.asset_url + 'images/' + settings_menu.icon + '.svg'" class="fs-sidebar-icons" />
                            {{ settings_menu.title }}
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
export default {
    name: "SettingsView",
    data() {
        return {
            settings_items: [],
            isMenuOpen: false
        }
    },
    methods: {
        fetchSettingsMenu() {
            this.$get("settings/settings-menu")
                .then((response) => {
                    this.settings_items = response;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
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
