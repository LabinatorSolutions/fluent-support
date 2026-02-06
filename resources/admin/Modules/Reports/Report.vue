<template>
    <div class="fs_reports_wrapper">
        <!-- Mobile Drawer Overlay -->
        <div 
            class="fs_reports_menu_overlay" 
            :class="{ 'is-open': isMenuOpen }" 
            @click.stop="closeMenu"
            v-show="isMenuOpen"
        ></div>
        
        <!-- Mobile Toggle Button -->
        <div class="fs_reports_menu_toggle" :class="{ 'is-hidden': isMenuOpen }">
            <el-button class="fs_outline_btn fs_reports_menu_toggle_btn" v-if="!isMenuOpen" @click="toggleMenu">
                <el-icon><Menu /></el-icon>
                {{ $t('Reports') }}
            </el-button>
        </div>
        
        <div class="fs_reports_layout">
            <!-- Desktop Sidebar (always visible on desktop) -->
            <div class="fs_inside_menu_tab_header fs_desktop_sidebar">
                <div class="fs_box_head">
                    <div class="fs-inside-menu-tabs">
                        <!-- <button
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'overview' }]"
                            @click="setActiveTab('overview')"
                        >
                            {{ $t('Overview') }}
                        </button> -->
                        <button
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'personal-reports' }]"
                            @click="setActiveTab('personal-reports')"
                        >
                            {{ $t('Personal Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'agent-reports' }]"
                            @click="setActiveTab('agent-reports')"
                        >
                            {{ $t('Agents Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'product-reports' }]"
                            @click="setActiveTab('product-reports')"
                        >
                            {{ $t('Products Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'business-box-reports' }]"
                            @click="setActiveTab('business-box-reports')"
                        >
                            {{ $t('Business Boxes Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'activity-reports' }]"
                            @click="setActiveTab('activity-reports')"
                        >
                            {{ $t('Activity Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1 && appVars.agent_time_tracking === 'yes' && has_pro"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'time-sheet' }]"
                            @click="setActiveTab('time-sheet')"
                        >
                            {{ $t('Time Sheet') }}
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Drawer Sidebar (only visible on mobile when toggled) -->
            <div class="fs_inside_menu_tab_header fs_mobile_drawer" :class="{ 'is-open': isMenuOpen }">
                <div class="fs_box_head">
                    <div class="fs-inside-menu-tabs">
                        <!-- <button
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'overview' }]"
                            @click="setActiveTab('overview')"
                        >
                            {{ $t('Overview') }}
                        </button> -->
                        <button
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'personal-reports' }]"
                            @click="setActiveTab('personal-reports')"
                        >
                            {{ $t('Personal Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'agent-reports' }]"
                            @click="setActiveTab('agent-reports')"
                        >
                            {{ $t('Agents Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'product-reports' }]"
                            @click="setActiveTab('product-reports')"
                        >
                            {{ $t('Products Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'business-box-reports' }]"
                            @click="setActiveTab('business-box-reports')"
                        >
                            {{ $t('Business Boxes Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'activity-reports' }]"
                            @click="setActiveTab('activity-reports')"
                        >
                            {{ $t('Activity Reports') }}
                        </button>
                        <button
                            v-if="me.permissions.indexOf('fst_sensitive_data') != -1 && appVars.agent_time_tracking === 'yes' && has_pro"
                            :class="['fs_nav_tab', { 'fs_nav_tab_active': activeName === 'time-sheet' }]"
                            @click="setActiveTab('time-sheet')"
                        >
                            {{ $t('Time Sheet') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="fs_box_wrapper">
                <div class="fs_reports_content">
                    <div class="fs_component_dashboard">
                        <keep-alive>
                            <component :is="activeComponent" :url="activeComponentUrl" :key="activeName" :date_range="date_range" @date-change="handleDateChange" />
                        </keep-alive>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import Overview from "./Overview";
import Reports from "./Reports";
import PersonalReports from "./PersonalReports";
import ProductReports from "./ProductReports";
import BusinessBoxReports from "./BusinessBoxReports";
import ActivityByTimeOfDay from "./ActivityByTimeOfDay";
import TimeSheet from "./TimeSheet/TimeSheet.vue";
import { getDefaultDateRange } from "./Utils/reportHelpers";
import { Menu } from '@element-plus/icons-vue';

export default {
    name: 'Report',
    components:{
        Overview,
        Reports,
        PersonalReports,
        ProductReports,
        BusinessBoxReports,
        ActivityByTimeOfDay,
        TimeSheet,
        Menu
    },

    data() {
        const tabFromUrl = this.getTabFromUrl() || 'personal-reports';
        const dateRange = this.getDateRangeFromUrl() || getDefaultDateRange();
        return {
            activeName: tabFromUrl,
            me: this.appVars.me,
            date_range: dateRange,
            isMenuOpen: false
        }
    },

    computed: {
        activeComponent() {
            const componentMap = {
                'overview': 'Overview',
                'personal-reports': 'PersonalReports',
                'agent-reports': 'Reports',
                'product-reports': 'ProductReports',
                'business-box-reports': 'BusinessBoxReports',
                'activity-reports': 'ActivityByTimeOfDay',
                'time-sheet': 'TimeSheet'
            };
            return componentMap[this.activeName] || 'PersonalReports';
        },
        activeComponentUrl() {
            const urlMap = {
                'overview': 'overview',
                'personal-reports': 'reports/stats',
                'product-reports': 'product-reports',
                'business-box-reports': 'mailbox-reports',
                'activity-reports': 'activity-by-time-of-day',
                'time-sheet': 'time-sheet'
            };
            return urlMap[this.activeName] || '';
        }
    },

    methods: {
        getHashParams() {
            const hash = window.location.hash;
            const queryIndex = hash.indexOf('?');
            if (queryIndex !== -1) {
                return new URLSearchParams(hash.substring(queryIndex + 1));
            }
            return new URLSearchParams();
        },

        getTabFromUrl() {
            const urlParams = this.getHashParams();
            const tab = urlParams.get('tab');
            const validTabs = ['personal-reports', 'agent-reports', 'product-reports', 'business-box-reports', 'activity-reports', 'time-sheet'];
            return validTabs.includes(tab) ? tab : null;
        },

        getDateRangeFromUrl() {
            const urlParams = this.getHashParams();
            const from = urlParams.get('from');
            const to = urlParams.get('to');
            if (from && to) {
                return [from, to];
            }
            return null;
        },

        setActiveTab(tabName) {
            this.activeName = tabName;
            this.updateUrl();
            this.closeMenu();
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
        },

        updateUrl() {
            const hash = window.location.hash;
            const hashPath = hash.split('?')[0] || '#/reports';
            const params = new URLSearchParams();
            
            params.set('tab', this.activeName);
            // Avoid pushing date params when in Time Sheet tab
            if (this.activeName !== 'time-sheet' && this.date_range && this.date_range.length === 2) {
                params.set('from', this.date_range[0]);
                params.set('to', this.date_range[1]);
            }
            
            const newHash = hashPath + '?' + params.toString();
            window.history.pushState({}, '', window.location.pathname + window.location.search + newHash);
        },

        handleDateChange(newDateRange) {
            this.date_range = newDateRange;
            this.updateUrl();
        }
    },

    mounted() {
        // Handle browser back/forward navigation
        window.addEventListener('popstate', () => {
            const tab = this.getTabFromUrl();
            if (tab) {
                this.activeName = tab;
            }
            const dateRange = this.getDateRangeFromUrl();
            if (dateRange) {
                this.date_range = dateRange;
            }
        });
    },

    beforeUnmount() {
        document.body.style.overflow = '';
    }
}
</script>

